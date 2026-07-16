<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/ocr.php';
require_once dirname(__DIR__) . '/includes/DocumentClassifier.php';
require_once dirname(__DIR__) . '/includes/DocumentFieldExtractor.php';
require_login();
block_guest();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    header('Content-Type: application/json');
    verify_csrf();

    $response = ['success' => false, 'message' => '', 'fields' => null, 'image' => null];

    try {
        $file = $_FILES['document'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed (error code ' . $file['error'] . ').');
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('File is too large. Max size is ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
            throw new RuntimeException('Unsupported file type. Please upload a JPG, PNG, or WEBP image.');
        }

        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
            throw new RuntimeException('Server upload directory is not writable.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => 'bin',
        };
        $storedName = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destination = UPLOAD_DIR . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }

        $ocrResult = OcrEngine::extractTextAndConfidence($destination);
        $rawText = $ocrResult['text'];
        $words = $ocrResult['words'] ?? [];
        $overallConfidence = $ocrResult['confidence'] ?? null;

        // --- classify, then extract only the fields relevant to that type ---
        $classification = DocumentClassifier::classify($rawText);
        $docType = $classification['type'];
        $fields = DocumentFieldExtractor::extract($docType, $rawText);

        $fieldConfidences = OcrEngine::computeFieldConfidence($fields, $words);
        $suggestions = OcrEngine::suggestCorrections($fields);

        $parsed = [];
        foreach ($fields as $k => $v) {
            $parsed[$k] = [
                'value' => $v,
                'confidence' => $fieldConfidences[$k] ?? null,
                'suggestion' => $suggestions[$k] ?? null,
            ];
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO ocr_scans (scanned_by, original_filename, stored_filename, raw_text, parsed_json, confidence, document_type, classification_confidence)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'], $file['name'], $storedName, $rawText,
            json_encode($parsed, JSON_UNESCAPED_UNICODE), $overallConfidence,
            $docType, $classification['confidence'],
        ]);
        $scanId = (int)$db->lastInsertId();

        log_activity($_SESSION['user_id'], 'OCR_SCAN', "Scanned document: {$file['name']} (detected: {$classification['label']}, {$classification['confidence']}%)");

        // Kept in session until whichever save handler consumes it (not on mere page view),
        // so "Change Document Type" can bounce between add.php and review.php without losing data.
        $_SESSION['ocr_pending'] = [
            'scan_id'   => $scanId,
            'doc_type'  => $docType,
            'doc_label' => $classification['label'],
            'doc_confidence' => $classification['confidence'],
            'raw_text'  => $rawText,
            'fields'    => array_combine(array_keys($parsed), array_map(fn($p) => $p['value'], $parsed)),
            'field_meta' => $parsed,
            'image'     => $storedName,
        ];

        $response['success'] = true;
        $response['document_type'] = $docType;
        $response['document_label'] = $classification['label'];
        $response['confidence'] = $classification['confidence'];
        $response['redirect'] = DocumentClassifier::isPersonType($docType)
            ? BASE_URL . 'clients/add.php'
            : BASE_URL . 'documents/review.php';
    } catch (Throwable $e) {
        $response['message'] = $e->getMessage();
        error_log('OCR scan error: ' . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

$pageTitle = 'Scan Document';
$extraScripts = ['assets/js/scan.js?v=3'];
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <h3>Scan a Document</h3>
    <p class="text-muted">Upload a clear photo of an ID, business card, receipt, or invoice. We'll detect the document type automatically and show only the fields that matter.</p>
    <form id="scanForm" method="POST" action="scan.php" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="scan-grid">
            <div class="scan-upload-box" id="dropZone">
                <input type="file" id="documentInput" accept="image/jpeg,image/png,image/webp" hidden>
                <div id="uploadPrompt">
                    <div class="upload-icon">📷</div>
                    <p><strong>Click to upload</strong> or drag & drop</p>
                    <p class="text-muted small">JPG, PNG, WEBP — up to 8MB</p>
                </div>
                <img id="previewImage" class="scan-preview" style="display:none" alt="Document preview">
            </div>
            <div class="scan-status" id="scanStatus"></div>
        </div>
        <button id="scanButton" class="btn btn-primary" disabled>Extract Information</button>
    </form>
</div>
<div class="card" id="extractedFormCard" style="display:none">
    <h3 id="detectedTypeHeading">✅ Information Extracted</h3>
    <p class="text-muted" id="detectedTypeSub">Continue to review and complete the form.</p>
    <a href="<?= BASE_URL ?>clients/add.php" id="continueToReview" class="btn btn-primary">Continue to Review →</a>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>