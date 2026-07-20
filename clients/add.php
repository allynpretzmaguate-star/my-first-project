<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/DocumentClassifier.php';
require_once dirname(__DIR__) . '/includes/DocumentFieldConfig.php';
require_once dirname(__DIR__) . '/includes/dynamic_form_helpers.php';
require_login();
block_guest();

$db = Database::getConnection();
$errors = [];

// Matches your VERIFIED live `clients` table (via DESCRIBE clients) —
// no time_of_birth / date_of_registration / issuing_authority / restrictions,
// since those columns don't actually exist in your database.
$allColumns = [
    'first_name', 'middle_name', 'last_name', 'suffix',
    'region', 'province', 'city', 'barangay', 'residence', 'street',
    'birth_date', 'birth_place', 'father_name', 'mother_name',
    'sex', 'civil_status', 'religion', 'nationality', 'address',
    'contact_number', 'email', 'fb_messenger_name',
    'ethnic_origin', 'language_spoken', 'osca_id_no', 'gsis_sss_no', 'tin',
    'philhealth_no', 'sc_association_id_no', 'other_govt_id_no',
    'employment_business', 'has_pension', 'capability_to_travel',
    'id_type', 'id_number', 'id_issue_date', 'id_expiration_date', 'id_class',
    'blood_type', 'school', 'course', 'year_level', 'notes',
];
$values = array_fill_keys($allColumns, '');

$pendingMeta = $_SESSION['ocr_pending'] ?? null;
$fromOcrImage = '';
$fieldMeta = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $pendingMeta !== null) {
    foreach (($pendingMeta['fields'] ?? []) as $key => $val) {
        if (array_key_exists($key, $values) && $val !== '') {
            $values[$key] = $val;
        }
    }
    $fromOcrImage = $pendingMeta['image'] ?? '';
    $fieldMeta = $pendingMeta['field_meta'] ?? [];
}

$docType = $_POST['doc_type'] ?? ($pendingMeta['doc_type'] ?? null);
$docLabel = $pendingMeta['doc_label'] ?? ($docType ? (DocumentClassifier::allTypes()[$docType] ?? $docType) : null);
$isDynamic = $docType && DocumentFieldConfig::has($docType);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($allColumns as $key) {
        $values[$key] = clean($_POST[$key] ?? '');
    }
    $fromOcrImage = clean($_POST['from_ocr_image'] ?? '');

    if (!$isDynamic) {
        $errors[] = 'This document type could not be mapped to a known field set. Please choose a valid document type.';
    } else {
        $errors = array_merge($errors, validate_dynamic_fields($docType, $_POST));

        $idTypeLabels = [
            'national_id' => 'National ID (PhilSys)',
            'drivers_license' => "Driver's License",
            'passport' => 'Passport',
            'birth_certificate' => 'Birth Certificate',
            'student_id' => 'Student ID',
        ];
        if (empty($values['id_type']) && isset($idTypeLabels[$docType])) {
            $values['id_type'] = $idTypeLabels[$docType];
        }
    }

    $birthDate = $values['birth_date'] !== '' && strtotime($values['birth_date']) !== false
        ? date('Y-m-d', strtotime($values['birth_date'])) : null;
    $idIssueDate = $values['id_issue_date'] !== '' && strtotime($values['id_issue_date']) !== false
        ? date('Y-m-d', strtotime($values['id_issue_date'])) : null;
    $idExpirationDate = $values['id_expiration_date'] !== '' && strtotime($values['id_expiration_date']) !== false
        ? date('Y-m-d', strtotime($values['id_expiration_date'])) : null;

    $documentImage = null;
    if ($fromOcrImage !== '' && is_file(UPLOAD_DIR . basename($fromOcrImage))) {
        $documentImage = basename($fromOcrImage);
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare(
                'INSERT INTO clients
                    (first_name, middle_name, last_name, suffix,
                     region, province, city, barangay, residence, street,
                     birth_date, birth_place, father_name, mother_name,
                     sex, civil_status, religion, nationality, address,
                     contact_number, email, fb_messenger_name,
                     ethnic_origin, language_spoken, osca_id_no, gsis_sss_no, tin,
                     philhealth_no, sc_association_id_no, other_govt_id_no,
                     employment_business, has_pension, capability_to_travel,
                     id_type, id_number, id_issue_date, id_expiration_date, id_class,
                     blood_type, school, course, year_level,
                     document_image, notes, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $values['first_name'], $values['middle_name'] ?: null, $values['last_name'], $values['suffix'] ?: null,
                $values['region'] ?: null, $values['province'] ?: null, $values['city'] ?: null, $values['barangay'] ?: null,
                $values['residence'] ?: null, $values['street'] ?: null,
                $birthDate, $values['birth_place'] ?: null, $values['father_name'] ?: null, $values['mother_name'] ?: null,
                $values['sex'] ?: null, $values['civil_status'] ?: null, $values['religion'] ?: null,
                $values['nationality'] ?: null, $values['address'] ?: null,
                $values['contact_number'] ?: null, $values['email'] ?: null, $values['fb_messenger_name'] ?: null,
                $values['ethnic_origin'] ?: null, $values['language_spoken'] ?: null, $values['osca_id_no'] ?: null,
                $values['gsis_sss_no'] ?: null, $values['tin'] ?: null,
                $values['philhealth_no'] ?: null, $values['sc_association_id_no'] ?: null, $values['other_govt_id_no'] ?: null,
                $values['employment_business'] ?: null, $values['has_pension'] ?: null, $values['capability_to_travel'] ?: null,
                $values['id_type'] ?: null, $values['id_number'] ?: null, $idIssueDate, $idExpirationDate, $values['id_class'] ?: null,
                $values['blood_type'] ?: null, $values['school'] ?: null, $values['course'] ?: null, $values['year_level'] ?: null,
                $documentImage, $values['notes'] ?: null,
                $_SESSION['user_id'], $_SESSION['user_id'],
            ]);

            $newClientId = (int)$db->lastInsertId();

            if ($documentImage !== null) {
                $db->prepare('UPDATE ocr_scans SET client_id = ? WHERE stored_filename = ?')
                   ->execute([$newClientId, $documentImage]);
            }

            log_activity($_SESSION['user_id'], 'CREATE_CLIENT', "Added client #$newClientId ({$values['first_name']} {$values['last_name']})");
            unset($_SESSION['ocr_pending']);
            set_flash('success', 'Client record saved successfully.');
            redirect('clients/view.php?id=' . $newClientId);
        } catch (Throwable $e) {
            error_log('Add client error: ' . $e->getMessage());
            $errors[] = 'Something went wrong while saving. Please try again.';
        }
    }
}

$pageTitle = 'Add Client';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="card">
    <div class="card-header-row">
        <h3>Add Client<?= $docLabel ? ' — ' . h($docLabel) : ' Manually' ?></h3>
        <div class="header-actions">
            <a href="scan.php" class="btn btn-outline btn-sm">📷 Scan Instead</a>
        </div>
    </div>

    <?php if ($fromOcrImage !== ''): ?>
        <div class="alert alert-info">📷 Fields below were auto-filled from your scanned document. Please review for accuracy before saving.</div>
    <?php endif; ?>

    <?php if ($pendingMeta !== null): ?>
        <p>
            Detected Document: <strong><?= h($pendingMeta['doc_label']) ?></strong>
            &nbsp;·&nbsp; OCR Confidence: <strong><?= h((string)$pendingMeta['doc_confidence']) ?>%</strong>
        </p>
        <form method="GET" action="<?= BASE_URL ?>documents/review.php" class="filter-bar" style="margin-bottom:16px">
            <select name="override_type">
                <?php foreach (DocumentClassifier::allTypes() as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $key === $docType ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Change Document Type</button>
        </form>
    <?php elseif (!$docType): ?>
        <div class="filter-bar" style="margin-bottom:16px">
            <label for="doc_type_picker" class="text-muted small" style="align-self:center;">No document scanned — pick a type to enter manually:</label>
            <select id="doc_type_picker" onchange="window.location.href='add.php?doc_type='+this.value">
                <option value="">-- Select Document Type --</option>
                <?php foreach (['national_id','drivers_license','passport','birth_certificate','student_id'] as $key): ?>
                    <option value="<?= h($key) ?>" <?= $docType === $key ? 'selected' : '' ?>>
                        <?= h(DocumentClassifier::allTypes()[$key]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php if (!$docType): ?>
        <p class="text-muted">Select a document type above, or use <a href="scan.php">Scan Document</a> to detect it automatically.</p>
    <?php else: ?>
        <div class="<?= $fromOcrImage !== '' ? 'detail-grid' : '' ?>">
            <div>
                <form method="POST" action="add.php" id="addClientForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="doc_type" value="<?= h($docType) ?>">
                    <input type="hidden" name="from_ocr_image" value="<?= h($fromOcrImage) ?>">

                    <?php render_dynamic_form_fields($docType, $values, $fieldMeta); ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Client Record</button>
                        <a href="<?= BASE_URL ?>clients/list.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>

            <?php if ($fromOcrImage !== '' && is_file(UPLOAD_DIR . basename($fromOcrImage))): ?>
                <div>
                    <h4>Scanned Document</h4>
                    <img class="document-preview lightbox-trigger"
                         src="<?= BASE_URL ?>uploads/documents/<?= h(basename($fromOcrImage)) ?>"
                         alt="Scanned document preview">
                    <p class="text-muted small" style="margin-top:6px">Compare the extracted fields against the original. Click the image to enlarge.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>