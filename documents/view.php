<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/ocr.php';
require_once dirname(__DIR__) . '/includes/DocumentClassifier.php';
require_once dirname(__DIR__) . '/includes/DocumentFieldExtractor.php';
require_login();
block_guest();

if (!isset($_SESSION['ocr_pending'])) {
    set_flash('error', 'No scanned document to review. Please scan a document first.');
    redirect('clients/scan.php');
}
$pending = $_SESSION['ocr_pending'];
$docType = $pending['doc_type'];
$errors = [];

// "Change Document Type": re-extract fields for the newly chosen type from
// the raw OCR text already in session — no re-scan needed.
if (isset($_GET['override_type']) && in_array($_GET['override_type'], array_keys(DocumentClassifier::allTypes()), true)) {
    $newType = $_GET['override_type'];
    if (DocumentClassifier::isPersonType($newType)) {
        $pending['doc_type'] = $newType;
        $pending['fields'] = DocumentFieldExtractor::extract($newType, $pending['raw_text']);
        $_SESSION['ocr_pending'] = $pending;
        redirect('clients/add.php');
    }
    $docType = $newType;
    $pending['doc_type'] = $docType;
    $pending['fields'] = DocumentFieldExtractor::extract($docType, $pending['raw_text']);
    $pending['doc_label'] = DocumentClassifier::allTypes()[$docType];
    $_SESSION['ocr_pending'] = $pending;
}

$fieldKeys = DocumentFieldExtractor::fieldsForType($docType);
$fieldMeta = $pending['field_meta'] ?? [];
$values = $pending['fields'] ?? [];

$fieldLabels = [
    'full_name' => 'Full Name', 'company' => 'Company', 'position' => 'Position',
    'phone_number' => 'Phone Number', 'email' => 'Email', 'website' => 'Website',
    'company_address' => 'Company Address', 'store_name' => 'Store Name',
    'receipt_number' => 'Receipt Number', 'receipt_date' => 'Date', 'total_amount' => 'Total Amount',
    'tax_amount' => 'Tax', 'payment_method' => 'Payment Method', 'invoice_number' => 'Invoice Number',
    'company_name' => 'Company Name', 'customer_name' => 'Customer Name', 'invoice_date' => 'Invoice Date',
    'due_date' => 'Due Date', 'raw_text' => 'Extracted Text (manual mapping needed)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $db = Database::getConnection();
    $imagePath = $pending['image'] ?? null;
    $scanId = $pending['scan_id'] ?? null;

    $post = [];
    foreach ($fieldKeys as $key) {
        $post[$key] = clean($_POST[$key] ?? '');
    }

    try {
        if ($docType === 'business_card') {
            $stmt = $db->prepare(
                'INSERT INTO business_cards (full_name, company, position, phone_number, email, website, company_address, document_image, ocr_scan_id, scanned_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$post['full_name'] ?: null, $post['company'] ?: null, $post['position'] ?: null,
                $post['phone_number'] ?: null, $post['email'] ?: null, $post['website'] ?: null,
                $post['company_address'] ?: null, $imagePath, $scanId, $_SESSION['user_id']]);
            $newId = (int)$db->lastInsertId();
            log_activity($_SESSION['user_id'], 'CREATE_BUSINESS_CARD', "Added business card #$newId");
            unset($_SESSION['ocr_pending']);
            set_flash('success', 'Business card saved.');
            redirect('documents/list.php?type=business_card');
        } elseif ($docType === 'receipt') {
            $stmt = $db->prepare(
                'INSERT INTO receipts (store_name, receipt_number, receipt_date, total_amount, tax_amount, payment_method, document_image, ocr_scan_id, scanned_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$post['store_name'] ?: null, $post['receipt_number'] ?: null,
                $post['receipt_date'] ?: null, $post['total_amount'] !== '' ? $post['total_amount'] : null,
                $post['tax_amount'] !== '' ? $post['tax_amount'] : null, $post['payment_method'] ?: null,
                $imagePath, $scanId, $_SESSION['user_id']]);
            $newId = (int)$db->lastInsertId();
            log_activity($_SESSION['user_id'], 'CREATE_RECEIPT', "Added receipt #$newId");
            unset($_SESSION['ocr_pending']);
            set_flash('success', 'Receipt saved.');
            redirect('documents/list.php?type=receipt');
        } elseif ($docType === 'invoice') {
            $stmt = $db->prepare(
                'INSERT INTO invoices (invoice_number, company_name, customer_name, invoice_date, due_date, total_amount, document_image, ocr_scan_id, scanned_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$post['invoice_number'] ?: null, $post['company_name'] ?: null,
                $post['customer_name'] ?: null, $post['invoice_date'] ?: null, $post['due_date'] ?: null,
                $post['total_amount'] !== '' ? $post['total_amount'] : null, $imagePath, $scanId, $_SESSION['user_id']]);
            $newId = (int)$db->lastInsertId();
            log_activity($_SESSION['user_id'], 'CREATE_INVOICE', "Added invoice #$newId");
            unset($_SESSION['ocr_pending']);
            set_flash('success', 'Invoice saved.');
            redirect('documents/list.php?type=invoice');
        } else {
            $errors[] = 'This document type could not be auto-classified. Use "Change Document Type" above to pick the correct type, or add the client manually.';
        }
    } catch (Throwable $e) {
        error_log('Document review save error: ' . $e->getMessage());
        $errors[] = 'Something went wrong while saving. Please try again.';
    }
}

$pageTitle = 'Review Extracted Document';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <div class="card-header-row">
        <h3>Review Extracted Information</h3>
    </div>

    <p>
        Detected Document: <strong><?= h($pending['doc_label']) ?></strong>
        &nbsp;·&nbsp; Confidence: <strong><?= h((string)$pending['doc_confidence']) ?>%</strong>
    </p>

    <form method="GET" action="review.php" class="filter-bar" style="margin-bottom:16px">
        <select name="override_type">
            <?php foreach (DocumentClassifier::allTypes() as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $key === $docType ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Change Document Type</button>
    </form>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php $scanImage = $pending['image'] ?? ''; ?>
    <?php if ($docType === 'other'): ?>
        <div class="alert alert-error">We couldn't confidently classify this document. Below is the raw extracted text — please choose the correct type above, or map it manually.</div>
        <?php if ($scanImage !== '' && is_file(UPLOAD_DIR . basename($scanImage))): ?>
            <img class="document-preview lightbox-trigger" style="margin-bottom:16px"
                 src="<?= BASE_URL ?>uploads/documents/<?= h(basename($scanImage)) ?>" alt="Scanned document preview">
        <?php endif; ?>
        <pre style="white-space:pre-wrap;background:var(--bg);padding:12px;border-radius:8px;"><?= h($pending['raw_text']) ?></pre>
    <?php else: ?>
        <div class="<?= ($scanImage !== '' && is_file(UPLOAD_DIR . basename($scanImage))) ? 'detail-grid' : '' ?>">
            <div>
                <form method="POST" action="review.php">
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <?php foreach ($fieldKeys as $key): ?>
                            <?php
                                $meta = $fieldMeta[$key] ?? null;
                                $conf = $meta['confidence'] ?? null;
                                $confBadge = '';
                                if ($conf !== null) {
                                    $confBadge = $conf >= 85 ? "✔ {$conf}%" : "⚠ {$conf}%";
                                }
                            ?>
                            <div class="form-group">
                                <label for="<?= h($key) ?>"><?= h($fieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key))) ?>
                                    <?php if ($confBadge): ?><span class="text-muted small"> (<?= h($confBadge) ?>)</span><?php endif; ?>
                                </label>
                                <input type="text" name="<?= h($key) ?>" id="<?= h($key) ?>" value="<?= h($values[$key] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Document</button>
                        <a href="<?= BASE_URL ?>clients/scan.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>

            <?php if ($scanImage !== '' && is_file(UPLOAD_DIR . basename($scanImage))): ?>
                <div>
                    <h4>Scanned Document</h4>
                    <img class="document-preview lightbox-trigger"
                         src="<?= BASE_URL ?>uploads/documents/<?= h(basename($scanImage)) ?>"
                         alt="Scanned document preview">
                    <p class="text-muted small" style="margin-top:6px">Compare the extracted fields against the original. Click the image to enlarge.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>