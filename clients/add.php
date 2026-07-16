<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/DocumentClassifier.php';
require_login();
block_guest();

$db = Database::getConnection();
$errors = [];

$values = [
    'first_name'            => '',
    'middle_name'           => '',
    'last_name'             => '',
    'suffix'                => '',
    'region'                => '',
    'province'              => '',
    'city'                  => '',
    'barangay'              => '',
    'residence'             => '',
    'street'                => '',
    'birth_date'            => '',
    'birth_place'           => '',
    'sex'                   => '',
    'civil_status'          => '',
    'religion'              => '',
    'nationality'           => 'Filipino',
    'address'               => '',
    'contact_number'        => '',
    'email'                 => '',
    'fb_messenger_name'     => '',
    'ethnic_origin'         => '',
    'language_spoken'       => '',
    'osca_id_no'            => '',
    'gsis_sss_no'           => '',
    'tin'                   => '',
    'philhealth_no'         => '',
    'sc_association_id_no'  => '',
    'other_govt_id_no'      => '',
    'employment_business'   => '',
    'has_pension'           => '',
    'capability_to_travel'  => '',
    'id_type'               => '',
    'id_number'              => '',
    'notes'                 => '',
];

// If there's a pending OCR scan waiting in the session, pre-fill the form
// fields from it. This is what makes "Scan Instead" -> extract -> land here
// with fields already filled.
//
// NOTE: unlike before, we no longer unset($_SESSION['ocr_pending']) here.
// It's kept around (read-only on GET, untouched on a failed POST) so that
// "Change Document Type" can bounce the user out to documents/review.php
// and back without losing the scan. It's only cleared once the client
// record is actually saved (see the success path below).
$fromOcrImage = '';
$pendingMeta = $_SESSION['ocr_pending'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $pendingMeta !== null) {
    if (isset($pendingMeta['fields']) && is_array($pendingMeta['fields'])) {
        foreach ($pendingMeta['fields'] as $key => $val) {
            if (array_key_exists($key, $values) && $val !== '') {
                $values[$key] = $val;
            }
        }
    }
    $fromOcrImage = $pendingMeta['image'] ?? '';
}

// --- Type-aware field display -------------------------------------------
// Which fields are typically found on the detected document type. Empty
// array means "show every field" (manual add, or no scan on record).
$docType = $pendingMeta['doc_type'] ?? null;
$primaryFields = ($docType && DocumentClassifier::isPersonType($docType))
    ? DocumentClassifier::primaryFieldsForClientForm($docType)
    : [];

/** Returns the class string for a form-group div — hides fields that aren't
 *  typical for the detected document type until "Show All Fields" is toggled. */
function fgclass(string $key, array $primaryFields, string $extra = ''): string
{
    $classes = ['form-group'];
    if (!empty($primaryFields) && !in_array($key, $primaryFields, true)) {
        $classes[] = 'field-secondary';
    }
    if ($extra !== '') {
        $classes[] = $extra;
    }
    return implode(' ', $classes);
}
// --------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($values as $key => $default) {
        $values[$key] = clean($_POST[$key] ?? '');
    }
    $fromOcrImage = clean($_POST['from_ocr_image'] ?? '');

    if ($values['first_name'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($values['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }
    if ($values['sex'] !== '' && !in_array($values['sex'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Invalid sex value.';
    }
    if ($values['civil_status'] !== '' && !in_array($values['civil_status'], ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'], true)) {
        $errors[] = 'Invalid civil status value.';
    }
    if ($values['has_pension'] !== '' && !in_array($values['has_pension'], ['Yes', 'No'], true)) {
        $errors[] = 'Invalid pension value.';
    }
    if ($values['capability_to_travel'] !== '' && !in_array($values['capability_to_travel'], ['Yes', 'No'], true)) {
        $errors[] = 'Invalid capability to travel value.';
    }
    $birthDate = null;
    if ($values['birth_date'] !== '') {
        $ts = strtotime($values['birth_date']);
        if ($ts === false) {
            $errors[] = 'Invalid birth date.';
        } else {
            $birthDate = date('Y-m-d', $ts);
        }
    }

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
                     birth_date, birth_place, sex, civil_status, religion,
                     nationality, address, contact_number, email, fb_messenger_name,
                     ethnic_origin, language_spoken, osca_id_no, gsis_sss_no, tin,
                     philhealth_no, sc_association_id_no, other_govt_id_no,
                     employment_business, has_pension, capability_to_travel,
                     id_type, id_number, document_image, notes, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $values['first_name'],
                $values['middle_name'] ?: null,
                $values['last_name'],
                $values['suffix'] ?: null,
                $values['region'] ?: null,
                $values['province'] ?: null,
                $values['city'] ?: null,
                $values['barangay'] ?: null,
                $values['residence'] ?: null,
                $values['street'] ?: null,
                $birthDate,
                $values['birth_place'] ?: null,
                $values['sex'] ?: null,
                $values['civil_status'] ?: null,
                $values['religion'] ?: null,
                $values['nationality'] ?: null,
                $values['address'] ?: null,
                $values['contact_number'] ?: null,
                $values['email'] ?: null,
                $values['fb_messenger_name'] ?: null,
                $values['ethnic_origin'] ?: null,
                $values['language_spoken'] ?: null,
                $values['osca_id_no'] ?: null,
                $values['gsis_sss_no'] ?: null,
                $values['tin'] ?: null,
                $values['philhealth_no'] ?: null,
                $values['sc_association_id_no'] ?: null,
                $values['other_govt_id_no'] ?: null,
                $values['employment_business'] ?: null,
                $values['has_pension'] ?: null,
                $values['capability_to_travel'] ?: null,
                $values['id_type'] ?: null,
                $values['id_number'] ?: null,
                $documentImage,
                $values['notes'] ?: null,
                $_SESSION['user_id'],
                $_SESSION['user_id'],
            ]);

            $newClientId = (int)$db->lastInsertId();

            if ($documentImage !== null) {
                $db->prepare('UPDATE ocr_scans SET client_id = ? WHERE stored_filename = ?')
                   ->execute([$newClientId, $documentImage]);
            }

            log_activity(
                $_SESSION['user_id'],
                'CREATE_CLIENT',
                "Added client #$newClientId ({$values['first_name']} {$values['last_name']})"
            );

            // Now that the record is safely saved, the pending scan data can go.
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
        <h3>Add Client Manually</h3>
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
            &nbsp;·&nbsp; Confidence: <strong><?= h((string)$pendingMeta['doc_confidence']) ?>%</strong>
        </p>
        <form method="GET" action="<?= BASE_URL ?>documents/review.php" class="filter-bar" style="margin-bottom:16px">
            <select name="override_type">
                <?php foreach (DocumentClassifier::allTypes() as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $key === $pendingMeta['doc_type'] ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Change Document Type</button>
        </form>
    <?php endif; ?>

    <?php if (!empty($primaryFields)): ?>
        <div class="form-toggle-row" style="margin:0 0 18px; display:flex; align-items:center; gap:10px;">
            <button type="button" id="toggleAllFieldsBtn" class="btn btn-outline btn-sm">Show All Fields</button>
            <span class="text-muted small">Only fields typically found on a <?= h($pendingMeta['doc_label']) ?> are shown by default.</span>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="add.php" id="addClientForm">
        <?= csrf_field() ?>
        <input type="hidden" name="from_ocr_image" value="<?= h($fromOcrImage) ?>">

        <h4>1. Name</h4>
        <div class="form-grid">
            <div class="<?= fgclass('last_name', $primaryFields) ?>">
                <label for="last_name">Last Name (Apelyido) *</label>
                <input type="text" name="last_name" id="last_name" required value="<?= h($values['last_name']) ?>">
            </div>
            <div class="<?= fgclass('first_name', $primaryFields) ?>">
                <label for="first_name">First Name (Pangalan) *</label>
                <input type="text" name="first_name" id="first_name" required value="<?= h($values['first_name']) ?>">
            </div>
            <div class="<?= fgclass('middle_name', $primaryFields) ?>">
                <label for="middle_name">Middle Name (Gitnang Pangalan)</label>
                <input type="text" name="middle_name" id="middle_name" value="<?= h($values['middle_name']) ?>">
            </div>
            <div class="<?= fgclass('suffix', $primaryFields) ?>">
                <label for="suffix">Extension</label>
                <input type="text" name="suffix" id="suffix" placeholder="Jr., Sr., III" value="<?= h($values['suffix']) ?>">
            </div>
        </div>

        <h4 style="margin-top:20px">2. Address</h4>
        <div class="form-grid">
            <div class="<?= fgclass('region', $primaryFields) ?>">
                <label for="region">Region</label>
                <input type="text" name="region" id="region" value="<?= h($values['region']) ?>">
            </div>
            <div class="<?= fgclass('province', $primaryFields) ?>">
                <label for="province">Province</label>
                <input type="text" name="province" id="province" value="<?= h($values['province']) ?>">
            </div>
            <div class="<?= fgclass('city', $primaryFields) ?>">
                <label for="city">City</label>
                <input type="text" name="city" id="city" value="<?= h($values['city']) ?>">
            </div>
            <div class="<?= fgclass('barangay', $primaryFields) ?>">
                <label for="barangay">Barangay</label>
                <input type="text" name="barangay" id="barangay" value="<?= h($values['barangay']) ?>">
            </div>
            <div class="<?= fgclass('residence', $primaryFields) ?>">
                <label for="residence">Residence (House No./Block/Lot)</label>
                <input type="text" name="residence" id="residence" value="<?= h($values['residence']) ?>">
            </div>
            <div class="<?= fgclass('street', $primaryFields) ?>">
                <label for="street">Street (Zone/Purok/Sitio)</label>
                <input type="text" name="street" id="street" value="<?= h($values['street']) ?>">
            </div>
            <div class="<?= fgclass('address', $primaryFields, 'form-group-full') ?>">
                <label for="address">Full Address (freeform — also used by OCR scans)</label>
                <textarea name="address" id="address" rows="2"><?= h($values['address']) ?></textarea>
            </div>
        </div>

        <h4 style="margin-top:20px">3–7. Birth &amp; Personal Info</h4>
        <div class="form-grid">
            <div class="<?= fgclass('birth_date', $primaryFields) ?>">
                <label for="birth_date">Birth Date</label>
                <input type="date" name="birth_date" id="birth_date" value="<?= h($values['birth_date']) ?>">
            </div>
            <div class="<?= fgclass('birth_place', $primaryFields) ?>">
                <label for="birth_place">Birth Place</label>
                <input type="text" name="birth_place" id="birth_place" value="<?= h($values['birth_place']) ?>">
            </div>
            <div class="<?= fgclass('civil_status', $primaryFields) ?>">
                <label for="civil_status">Marital Status</label>
                <select name="civil_status" id="civil_status">
                    <option value="">-- Select --</option>
                    <?php foreach (['Single', 'Married', 'Widowed', 'Separated', 'Divorced'] as $cs): ?>
                        <option value="<?= $cs ?>" <?= $values['civil_status'] === $cs ? 'selected' : '' ?>><?= $cs ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="<?= fgclass('religion', $primaryFields) ?>">
                <label for="religion">Religion</label>
                <input type="text" name="religion" id="religion" value="<?= h($values['religion']) ?>">
            </div>
            <div class="<?= fgclass('sex', $primaryFields) ?>">
                <label for="sex">Sex at Birth</label>
                <select name="sex" id="sex">
                    <option value="">-- Select --</option>
                    <option value="Male" <?= $values['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $values['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $values['sex'] === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="<?= fgclass('nationality', $primaryFields) ?>">
                <label for="nationality">Nationality</label>
                <input type="text" name="nationality" id="nationality" value="<?= h($values['nationality']) ?>">
            </div>
        </div>

        <h4 style="margin-top:20px">8–11. Contact &amp; Background</h4>
        <div class="form-grid">
            <div class="<?= fgclass('contact_number', $primaryFields) ?>">
                <label for="contact_number">Contact Number</label>
                <input type="text" name="contact_number" id="contact_number" value="<?= h($values['contact_number']) ?>">
            </div>
            <div class="<?= fgclass('email', $primaryFields) ?>">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" value="<?= h($values['email']) ?>">
            </div>
            <div class="<?= fgclass('fb_messenger_name', $primaryFields) ?>">
                <label for="fb_messenger_name">FB Messenger Name</label>
                <input type="text" name="fb_messenger_name" id="fb_messenger_name" value="<?= h($values['fb_messenger_name']) ?>">
            </div>
            <div class="<?= fgclass('ethnic_origin', $primaryFields) ?>">
                <label for="ethnic_origin">Ethnic Origin</label>
                <input type="text" name="ethnic_origin" id="ethnic_origin" value="<?= h($values['ethnic_origin']) ?>">
            </div>
            <div class="<?= fgclass('language_spoken', $primaryFields) ?>">
                <label for="language_spoken">Language Spoken</label>
                <input type="text" name="language_spoken" id="language_spoken" value="<?= h($values['language_spoken']) ?>">
            </div>
        </div>

        <h4 style="margin-top:20px">12–17. Government IDs</h4>
        <div class="form-grid">
            <div class="<?= fgclass('osca_id_no', $primaryFields) ?>">
                <label for="osca_id_no">OSCA ID No.</label>
                <input type="text" name="osca_id_no" id="osca_id_no" value="<?= h($values['osca_id_no']) ?>">
            </div>
            <div class="<?= fgclass('gsis_sss_no', $primaryFields) ?>">
                <label for="gsis_sss_no">GSIS/SSS No.</label>
                <input type="text" name="gsis_sss_no" id="gsis_sss_no" value="<?= h($values['gsis_sss_no']) ?>">
            </div>
            <div class="<?= fgclass('tin', $primaryFields) ?>">
                <label for="tin">TIN</label>
                <input type="text" name="tin" id="tin" value="<?= h($values['tin']) ?>">
            </div>
            <div class="<?= fgclass('philhealth_no', $primaryFields) ?>">
                <label for="philhealth_no">PhilHealth No.</label>
                <input type="text" name="philhealth_no" id="philhealth_no" value="<?= h($values['philhealth_no']) ?>">
            </div>
            <div class="<?= fgclass('sc_association_id_no', $primaryFields) ?>">
                <label for="sc_association_id_no">SC Association ID No.</label>
                <input type="text" name="sc_association_id_no" id="sc_association_id_no" value="<?= h($values['sc_association_id_no']) ?>">
            </div>
            <div class="<?= fgclass('other_govt_id_no', $primaryFields) ?>">
                <label for="other_govt_id_no">Other Gov't ID No.</label>
                <input type="text" name="other_govt_id_no" id="other_govt_id_no" value="<?= h($values['other_govt_id_no']) ?>">
            </div>
            <div class="<?= fgclass('id_type', $primaryFields) ?>">
                <label for="id_type">ID Type (from scanned document)</label>
                <input type="text" name="id_type" id="id_type" value="<?= h($values['id_type']) ?>">
            </div>
            <div class="<?= fgclass('id_number', $primaryFields) ?>">
                <label for="id_number">ID Number (from scanned document)</label>
                <input type="text" name="id_number" id="id_number" value="<?= h($values['id_number']) ?>">
            </div>
        </div>

        <h4 style="margin-top:20px">18–20. Employment &amp; Capability</h4>
        <div class="form-grid">
            <div class="<?= fgclass('employment_business', $primaryFields) ?>">
                <label for="employment_business">Employment / Business</label>
                <input type="text" name="employment_business" id="employment_business" value="<?= h($values['employment_business']) ?>">
            </div>
            <div class="<?= fgclass('has_pension', $primaryFields) ?>">
                <label for="has_pension">Has Pension</label>
                <select name="has_pension" id="has_pension">
                    <option value="">-- Select --</option>
                    <option value="Yes" <?= $values['has_pension'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No" <?= $values['has_pension'] === 'No' ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            <div class="<?= fgclass('capability_to_travel', $primaryFields) ?>">
                <label for="capability_to_travel">Capability to Travel</label>
                <select name="capability_to_travel" id="capability_to_travel">
                    <option value="">-- Select --</option>
                    <option value="Yes" <?= $values['capability_to_travel'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No" <?= $values['capability_to_travel'] === 'No' ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        </div>

        <h4 style="margin-top:20px">Notes</h4>
        <div class="form-grid">
            <div class="<?= fgclass('notes', $primaryFields, 'form-group-full') ?>">
                <textarea name="notes" id="notes" rows="2"><?= h($values['notes']) ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Client Record</button>
            <a href="<?= BASE_URL ?>clients/list.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php if (!empty($primaryFields)): ?>
<script>
(function () {
    var btn = document.getElementById('toggleAllFieldsBtn');
    var form = document.getElementById('addClientForm');
    if (btn && form) {
        btn.addEventListener('click', function () {
            form.classList.toggle('show-all-fields');
            btn.textContent = form.classList.contains('show-all-fields')
                ? 'Show Only Detected Fields'
                : 'Show All Fields';
        });
    }
})();
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>