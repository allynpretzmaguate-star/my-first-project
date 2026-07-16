<?php
// documents/view.php
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$tables = ['business_card' => 'business_cards', 'receipt' => 'receipts', 'invoice' => 'invoices'];
if (!isset($tables[$type])) { redirect('documents/list.php'); }

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM {$tables[$type]} WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();
if (!$record) { set_flash('error', 'Record not found.'); redirect('documents/list.php?type=' . $type); }

$pageTitle = 'View Document';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <div class="card-header-row">
        <h3>Document Details</h3>
        <a href="list.php?type=<?= h($type) ?>" class="btn btn-outline btn-sm">← Back to List</a>
    </div>
    <div class="detail-grid">
        <div>
            <dl class="detail-list">
                <?php foreach ($record as $k => $v): if (in_array($k, ['id', 'document_image', 'ocr_scan_id', 'scanned_by'])) continue; ?>
                    <dt><?= h(ucwords(str_replace('_', ' ', $k))) ?></dt>
                    <dd><?= $v !== null && $v !== '' ? h((string)$v) : '—' ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>
        <div>
            <?php if (!empty($record['document_image']) && is_file(UPLOAD_DIR . basename($record['document_image']))): ?>
                <img class="document-preview lightbox-trigger" src="<?= BASE_URL ?>uploads/documents/<?= h(basename($record['document_image'])) ?>" alt="Scanned document">
            <?php else: ?>
                <p class="text-muted">No image on file.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>