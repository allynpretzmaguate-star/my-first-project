<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_once dirname(__DIR__) . '/vendor/simplexlsxgen/src/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

$db = Database::getConnection();

$search = clean($_GET['q'] ?? '');
$statusFilter = clean($_GET['status'] ?? 'active');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR id_number LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if (in_array($statusFilter, ['active', 'archived'], true)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare(
    "SELECT first_name, middle_name, last_name, suffix, birth_date, sex, civil_status, nationality,
            address, contact_number, email, id_type, id_number, status, created_at
     FROM clients $whereSql ORDER BY last_name"
);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$rows = [];
$rows[] = ['First Name', 'Middle Name', 'Last Name', 'Suffix', 'Birth Date', 'Sex', 'Civil Status',
           'Nationality', 'Address', 'Contact Number', 'Email', 'ID Type', 'ID Number', 'Status', 'Date Added'];

foreach ($clients as $c) {
    $rows[] = [
        $c['first_name'], $c['middle_name'], $c['last_name'], $c['suffix'],
        $c['birth_date'] ? date('Y-m-d', strtotime($c['birth_date'])) : '',
        $c['sex'], $c['civil_status'], $c['nationality'], $c['address'],
        $c['contact_number'], $c['email'], $c['id_type'], $c['id_number'],
        ucfirst($c['status']), date('Y-m-d', strtotime($c['created_at'])),
    ];
}

log_activity($_SESSION['user_id'], 'EXPORT_EXCEL', 'Exported client list to Excel (' . count($clients) . ' records)');

$xlsx = SimpleXLSXGen::fromArray($rows);
$xlsx->downloadAs('client_records_' . date('Ymd_His') . '.xlsx');