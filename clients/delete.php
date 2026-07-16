<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
block_guest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('clients/list.php');
}

verify_csrf();
$id = (int)($_POST['id'] ?? 0);
$db = Database::getConnection();

$stmt = $db->prepare('SELECT first_name, last_name, document_image FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    set_flash('error', 'Client record not found.');
    redirect('clients/list.php');
}

try {
    $db->beginTransaction();

    $db->prepare('UPDATE ocr_scans SET client_id = NULL WHERE client_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);

    $db->commit();

    if (!empty($client['document_image'])) {
        $path = UPLOAD_DIR . basename($client['document_image']);
        if (is_file($path)) @unlink($path);
    }

    log_activity($_SESSION['user_id'], 'DELETE_CLIENT', "Deleted client #$id ({$client['first_name']} {$client['last_name']})");
    set_flash('success', 'Client record deleted.');
} catch (Throwable $e) {
    $db->rollBack();
    error_log('Delete client error: ' . $e->getMessage());
    set_flash('error', 'Could not delete this record. Please try again.');
}

redirect('clients/list.php');