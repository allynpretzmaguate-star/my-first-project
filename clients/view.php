<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/DocumentClassifier.php';
require_login();

$db = Database::getConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare(
    'SELECT c.*, cu.full_name AS created_by_name, uu.full_name AS updated_by_name
     FROM clients c
     LEFT JOIN users cu ON cu.id = c.created_by
     LEFT JOIN users uu ON uu.id = c.updated_by
     WHERE c.id = ?'
);
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    set_flash('error', 'Client record not found.');
    redirect('clients/list.php');
}

$scanStmt = $db->prepare('SELECT * FROM ocr_scans WHERE client_id = ? ORDER BY created_at DESC LIMIT 1');
$scanStmt->execute([$id]);
$scan = $scanStmt->fetch();

/** Small helper so every <dd> falls back to an em dash consistently */
function dv(?string $val): string
{
    return $val !== null && $val !== '' ? h($val) : '—';
}

// --- Document-type-aware field display ------------------------------------
// If this record came from a scanned, classifiable person-identity document
// (National ID, Driver's License, Passport, Birth Certificate, Student ID),
// only show the fields typical for that document type by default. Every
// other field is still there — just tucked behind "Show All Details" —
// since manually-entered data or a second document might have filled them.
$docType = $scan['document_type'] ?? null;
$isPersonDoc = $docType && DocumentClassifier::isPersonType($docType);
$primaryFields = $isPersonDoc ? DocumentClassifier::primaryFieldsForClientForm($docType) : [];
$docLabel = $docType ? (DocumentClassifier::allTypes()[$docType] ?? null) : null;

/** Wraps a dt/dd pair; hides it behind the toggle if not typical for the detected doc type */
function drow(string $key, array $primaryFields): string
{
    $classes = ['detail-row'];
    if (!empty($primaryFields) && !in_array($key, $primaryFields, true)) {
        $classes[] = 'secondary';
    }
    return implode(' ', $classes);
}
// ---------------------------------------------------------------------------

$fullAddressParts = array_filter([
    $client['residence'] ?? '',
    $client['street'] ?? '',
    $client['barangay'] ?? '',
    $client['city'] ?? '',
    $client['province'] ?? '',
    $client['region'] ?? '',
]);

$pageTitle = 'View Client';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="card">
    <div class="card-header-row">
        <h3><?= h(trim("{$client['last_name']}, {$client['first_name']} {$client['middle_name']} {$client['suffix']}")) ?>
            <span class="badge badge-<?= $client['status'] === 'active' ? 'success' : 'muted' ?>"><?= h(ucfirst($client['status'])) ?></span>
            <?php if ($docLabel): ?>
                <span class="badge badge-primary">📇 <?= h($docLabel) ?></span>
            <?php endif; ?>
        </h3>
        <div class="header-actions">
            <a href="edit.php?id=<?= $id ?>" class="btn btn-outline btn-sm">✏ Edit</a>
            <a href="<?= BASE_URL ?>export/pdf.php?id=<?= $id ?>" class="btn btn-outline btn-sm">⬇ PDF</a>
            <a href="list.php" class="btn btn-outline btn-sm">← Back to List</a>
        </div>
    </div>

    <?php if (!empty($primaryFields)): ?>
        <div class="form-toggle-row" style="margin:0 0 18px; display:flex; align-items:center; gap:10px;">
            <button type="button" id="toggleAllDetailsBtn" class="btn btn-outline btn-sm">Show All Details</button>
            <span class="text-muted small">Only fields typically found on a <?= h($docLabel) ?> are shown by default.</span>
        </div>
    <?php endif; ?>

    <div class="detail-grid">
        <div id="clientDetails">
            <h4>1. Name</h4>
            <dl class="detail-list">
                <dt>Full Name</dt>
                <dd><?= h(trim("{$client['first_name']} {$client['middle_name']} {$client['last_name']} {$client['suffix']}")) ?></dd>
            </dl>

            <h4 style="margin-top:18px">2. Address</h4>
            <dl class="detail-list">
                <div class="<?= drow('region', $primaryFields) ?>">
                    <dt>Region</dt>
                    <dd><?= dv($client['region']) ?></dd>
                </div>
                <div class="<?= drow('province', $primaryFields) ?>">
                    <dt>Province</dt>
                    <dd><?= dv($client['province']) ?></dd>
                </div>
                <div class="<?= drow('city', $primaryFields) ?>">
                    <dt>City</dt>
                    <dd><?= dv($client['city']) ?></dd>
                </div>
                <div class="<?= drow('barangay', $primaryFields) ?>">
                    <dt>Barangay</dt>
                    <dd><?= dv($client['barangay']) ?></dd>
                </div>
                <div class="<?= drow('residence', $primaryFields) ?>">
                    <dt>Residence</dt>
                    <dd><?= dv($client['residence']) ?></dd>
                </div>
                <div class="<?= drow('street', $primaryFields) ?>">
                    <dt>Street</dt>
                    <dd><?= dv($client['street']) ?></dd>
                </div>
                <div class="<?= drow('address', $primaryFields) ?>">
                    <dt>Full Address</dt>
                    <dd><?= $client['address'] ? h($client['address']) : ($fullAddressParts ? h(implode(', ', $fullAddressParts)) : '—') ?></dd>
                </div>
            </dl>

            <h4 style="margin-top:18px">3–7. Birth &amp; Personal Info</h4>
            <dl class="detail-list">
                <div class="<?= drow('birth_date', $primaryFields) ?>">
                    <dt>Birth Date</dt>
                    <dd><?= $client['birth_date'] ? format_date($client['birth_date'], 'F d, Y') : '—' ?></dd>
                </div>
                <div class="<?= drow('birth_place', $primaryFields) ?>">
                    <dt>Birth Place</dt>
                    <dd><?= dv($client['birth_place']) ?></dd>
                </div>
                <div class="<?= drow('civil_status', $primaryFields) ?>">
                    <dt>Marital Status</dt>
                    <dd><?= dv($client['civil_status']) ?></dd>
                </div>
                <div class="<?= drow('religion', $primaryFields) ?>">
                    <dt>Religion</dt>
                    <dd><?= dv($client['religion']) ?></dd>
                </div>
                <div class="<?= drow('sex', $primaryFields) ?>">
                    <dt>Sex</dt>
                    <dd><?= dv($client['sex']) ?></dd>
                </div>
                <div class="<?= drow('nationality', $primaryFields) ?>">
                    <dt>Nationality</dt>
                    <dd><?= dv($client['nationality']) ?></dd>
                </div>
            </dl>

            <h4 style="margin-top:18px">8–11. Contact &amp; Background</h4>
            <dl class="detail-list">
                <div class="<?= drow('contact_number', $primaryFields) ?>">
                    <dt>Contact Number</dt>
                    <dd><?= dv($client['contact_number']) ?></dd>
                </div>
                <div class="<?= drow('email', $primaryFields) ?>">
                    <dt>Email</dt>
                    <dd><?= dv($client['email']) ?></dd>
                </div>
                <div class="<?= drow('fb_messenger_name', $primaryFields) ?>">
                    <dt>FB Messenger Name</dt>
                    <dd><?= dv($client['fb_messenger_name']) ?></dd>
                </div>
                <div class="<?= drow('ethnic_origin', $primaryFields) ?>">
                    <dt>Ethnic Origin</dt>
                    <dd><?= dv($client['ethnic_origin']) ?></dd>
                </div>
                <div class="<?= drow('language_spoken', $primaryFields) ?>">
                    <dt>Language Spoken</dt>
                    <dd><?= dv($client['language_spoken']) ?></dd>
                </div>
            </dl>

            <h4 style="margin-top:18px">12–17. Government IDs</h4>
            <dl class="detail-list">
                <div class="<?= drow('osca_id_no', $primaryFields) ?>">
                    <dt>OSCA ID No.</dt>
                    <dd><?= dv($client['osca_id_no']) ?></dd>
                </div>
                <div class="<?= drow('gsis_sss_no', $primaryFields) ?>">
                    <dt>GSIS/SSS No.</dt>
                    <dd><?= dv($client['gsis_sss_no']) ?></dd>
                </div>
                <div class="<?= drow('tin', $primaryFields) ?>">
                    <dt>TIN</dt>
                    <dd><?= dv($client['tin']) ?></dd>
                </div>
                <div class="<?= drow('philhealth_no', $primaryFields) ?>">
                    <dt>PhilHealth No.</dt>
                    <dd><?= dv($client['philhealth_no']) ?></dd>
                </div>
                <div class="<?= drow('sc_association_id_no', $primaryFields) ?>">
                    <dt>SC Association ID No.</dt>
                    <dd><?= dv($client['sc_association_id_no']) ?></dd>
                </div>
                <div class="<?= drow('other_govt_id_no', $primaryFields) ?>">
                    <dt>Other Gov't ID No.</dt>
                    <dd><?= dv($client['other_govt_id_no']) ?></dd>
                </div>
                <div class="<?= drow('id_type', $primaryFields) ?>">
                    <dt>ID Type</dt>
                    <dd><?= dv($client['id_type']) ?></dd>
                </div>
                <div class="<?= drow('id_number', $primaryFields) ?>">
                    <dt>ID Number</dt>
                    <dd><?= dv($client['id_number']) ?></dd>
                </div>
            </dl>

            <h4 style="margin-top:18px">18–20. Employment &amp; Capability</h4>
            <dl class="detail-list">
                <div class="<?= drow('employment_business', $primaryFields) ?>">
                    <dt>Employment / Business</dt>
                    <dd><?= dv($client['employment_business']) ?></dd>
                </div>
                <div class="<?= drow('has_pension', $primaryFields) ?>">
                    <dt>Has Pension</dt>
                    <dd><?= dv($client['has_pension']) ?></dd>
                </div>
                <div class="<?= drow('capability_to_travel', $primaryFields) ?>">
                    <dt>Capability to Travel</dt>
                    <dd><?= dv($client['capability_to_travel']) ?></dd>
                </div>
            </dl>

            <h4 style="margin-top:18px">Notes &amp; Record Info</h4>
            <dl class="detail-list">
                <dt>Notes</dt>
                <dd><?= dv($client['notes']) ?></dd>
                <dt>Added By</dt>
                <dd><?= h($client['created_by_name'] ?: 'Unknown') ?> · <?= format_date($client['created_at']) ?></dd>
                <?php if ($client['updated_by_name']): ?>
                    <dt>Last Updated</dt>
                    <dd><?= h($client['updated_by_name']) ?> · <?= format_date($client['updated_at']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <div>
            <h4>Scanned Document</h4>
            <?php if (!empty($client['document_image']) && is_file(UPLOAD_DIR . basename($client['document_image']))): ?>
                <img class="document-preview lightbox-trigger"
                     src="<?= BASE_URL ?>uploads/documents/<?= h(basename($client['document_image'])) ?>"
                     alt="Scanned document for <?= h($client['first_name']) ?>">
                <p class="text-muted small" style="margin-top:6px">Click the image to view full size.</p>
            <?php else: ?>
                <p class="text-muted">No scanned document on file. This record was likely added manually.</p>
            <?php endif; ?>

            <?php if ($scan): ?>
                <p class="text-muted small" style="margin-top:10px">
                    <?php if ($docLabel): ?>Detected type: <strong><?= h($docLabel) ?></strong> (<?= h((string)$scan['classification_confidence']) ?>% confidence)<br><?php endif; ?>
                    OCR confidence: <?= $scan['confidence'] !== null ? round((float)$scan['confidence'], 1) . '%' : 'N/A' ?><br>
                    Scanned: <?= format_date($scan['created_at']) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($primaryFields)): ?>
<script>
(function () {
    var btn = document.getElementById('toggleAllDetailsBtn');
    var container = document.getElementById('clientDetails');
    if (btn && container) {
        btn.addEventListener('click', function () {
            container.classList.toggle('show-all-details');
            btn.textContent = container.classList.contains('show-all-details')
                ? 'Show Only Detected Fields'
                : 'Show All Details';
        });
    }
})();
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>