<?php
require_once dirname(__DIR__) . '/config/config.php';
require_login();
require_once dirname(__DIR__) . '/vendor/tcpdf/tcpdf.php';

$db = Database::getConnection();
$id = (int)($_GET['id'] ?? 0);

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(APP_NAME);
$pdf->SetAuthor($_SESSION['full_name'] ?? 'System');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

if ($id > 0) {
    // ---------- Single client record ----------
    $stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    $c = $stmt->fetch();

    if (!$c) {
        die('Client record not found.');
    }

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Client Information Record', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Generated: ' . date('F d, Y h:i A'), 0, 1);
    $pdf->Ln(4);

    $rows = [
        ['Full Name', trim("{$c['last_name']}, {$c['first_name']} {$c['middle_name']} {$c['suffix']}")],
        ['Birth Date', $c['birth_date'] ? date('F d, Y', strtotime($c['birth_date'])) : '-'],
        ['Sex', $c['sex'] ?: '-'],
        ['Civil Status', $c['civil_status'] ?: '-'],
        ['Nationality', $c['nationality'] ?: '-'],
        ['Address', $c['address'] ?: '-'],
        ['Contact Number', $c['contact_number'] ?: '-'],
        ['Email', $c['email'] ?: '-'],
        ['ID Type', $c['id_type'] ?: '-'],
        ['ID Number', $c['id_number'] ?: '-'],
        ['Notes', $c['notes'] ?: '-'],
        ['Status', ucfirst($c['status'])],
    ];

    $pdf->SetFont('helvetica', '', 11);
    foreach ($rows as [$label, $value]) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(45, 8, $label, 0, 0);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 8, $value, 0, 'L');
    }

    log_activity($_SESSION['user_id'], 'EXPORT_PDF', "Exported client #$id to PDF");
    $filename = 'client_' . $id . '_' . date('Ymd') . '.pdf';
} else {
    // ---------- Full filtered list export ----------
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

    $stmt = $db->prepare("SELECT first_name, middle_name, last_name, contact_number, id_type, id_number, status, created_at FROM clients $whereSql ORDER BY last_name");
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Client Records Export', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Generated: ' . date('F d, Y h:i A') . '  |  Total records: ' . count($clients), 0, 1);
    $pdf->Ln(4);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(30, 64, 175);
    $pdf->SetTextColor(255, 255, 255);
    $widths = [55, 30, 45, 30, 20];
    $headers = ['Name', 'Contact', 'ID Type / Number', 'Status', 'Date'];
    foreach ($headers as $i => $head) {
        $pdf->Cell($widths[$i], 8, $head, 1, 0, 'L', true);
    }
    $pdf->Ln();

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    foreach ($clients as $c) {
        $pdf->Cell($widths[0], 7, trim("{$c['last_name']}, {$c['first_name']}"), 1);
        $pdf->Cell($widths[1], 7, $c['contact_number'] ?: '-', 1);
        $pdf->Cell($widths[2], 7, trim(($c['id_type'] ?: '-') . ' ' . ($c['id_number'] ? "({$c['id_number']})" : '')), 1);
        $pdf->Cell($widths[3], 7, ucfirst($c['status']), 1);
        $pdf->Cell($widths[4], 7, date('m/d/y', strtotime($c['created_at'])), 1);
        $pdf->Ln();
    }

    log_activity($_SESSION['user_id'], 'EXPORT_PDF', 'Exported client list to PDF (' . count($clients) . ' records)');
    $filename = 'client_records_' . date('Ymd_His') . '.pdf';
}

$pdf->Output($filename, 'D'); // force download