<?php
// ✅ Start output buffering immediately - PENTING!
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    exit('Unauthorized');
}

$format = $_GET['format'] ?? 'excel';
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Initialize variables
$transactions = [];
$totalRevenue = 0;
$countSettlement = 0;

// ✅ Query data transaksi
try {
    $sql = "
        SELECT 
            gt.id,
            gt.order_id,
            gt.user_id,
            COALESCE(u.name, 'User Deleted') as user_name,
            COALESCE(u.email, '-') as user_email,
            gt.package,
            gt.amount,
            gt.gems,
            gt.transaction_status,
            gt.payment_type,
            gt.created_at,
            gt.paid_at
        FROM gem_transactions gt
        LEFT JOIN users u ON gt.user_id = u.id
        WHERE 1=1
    ";

    $params = [];

    if ($filter !== 'all') {
        $sql .= " AND gt.transaction_status = ?";
        $params[] = $filter;
    }

    if (!empty($search)) {
        $sql .= " AND (gt.order_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($dateFrom)) {
        $sql .= " AND DATE(gt.created_at) >= ?";
        $params[] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $sql .= " AND DATE(gt.created_at) <= ?";
        $params[] = $dateTo;
    }

    $sql .= " ORDER BY gt.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total revenue dan count settlement
    $stmtRevenue = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) 
        FROM gem_transactions 
        WHERE transaction_status = 'settlement'
    ");
    $totalRevenue = (int) $stmtRevenue->fetchColumn();

    $stmtCount = $pdo->query("
        SELECT COUNT(*) 
        FROM gem_transactions 
        WHERE transaction_status = 'settlement'
    ");
    $countSettlement = (int) $stmtCount->fetchColumn();

} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    $transactions = [];
    $totalRevenue = 0;
    $countSettlement = 0;
}

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatDate($date) {
    if (!$date) return '-';
    return date('d M Y, H:i', strtotime($date));
}

function getStatusLabel($status) {
    $labels = [
        'settlement' => 'Success',
        'pending' => 'Pending',
        'expire' => 'Expired',
        'cancel' => 'Cancelled'
    ];
    return $labels[$status] ?? ucfirst($status);
}

function getPackageLabel($package) {
    $labels = [
        'basic' => 'Basic',
        'pro' => 'Pro',
        'plus' => 'Plus'
    ];
    return $labels[$package] ?? ucfirst($package);
}

// =======================================
// EXPORT EXCEL
// =======================================
if ($format === 'excel') {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        try {
            require __DIR__ . '/vendor/autoload.php';
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Transactions');

            // 🎨 Header styling
            $sheet->setCellValue('A1', 'LAPORAN TRANSAKSI GEMS - JAGONUGAS');
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF667EEA');
            $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');

            $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d M Y H:i'));
            $sheet->mergeCells('A2:H2');
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Filter info
            $infoRow = 3;
            if (!empty($search)) {
                $sheet->setCellValue('A' . $infoRow, 'Filter Pencarian: ' . $search);
                $sheet->mergeCells('A' . $infoRow . ':H' . $infoRow);
                $infoRow++;
            }
            if ($filter !== 'all') {
                $sheet->setCellValue('A' . $infoRow, 'Filter Status: ' . ucfirst($filter));
                $sheet->mergeCells('A' . $infoRow . ':H' . $infoRow);
                $infoRow++;
            }
            if (!empty($dateFrom) || !empty($dateTo)) {
                $dateRange = 'Periode: ' . ($dateFrom ?: 'Awal') . ' s/d ' . ($dateTo ?: 'Sekarang');
                $sheet->setCellValue('A' . $infoRow, $dateRange);
                $sheet->mergeCells('A' . $infoRow . ':H' . $infoRow);
                $infoRow++;
            }

            // Column headers
            $headerRow = $infoRow + 1;
            $headers = ['Order ID', 'User Name', 'Email', 'Package', 'Gems', 'Amount (Rp)', 'Status', 'Date'];
            $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
            
            foreach ($columns as $index => $col) {
                $cell = $col . $headerRow;
                $sheet->setCellValue($cell, $headers[$index]);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF667EEA');
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            }

            // Data rows
            $row = $headerRow + 1;
            foreach ($transactions as $trx) {
                $sheet->setCellValue('A' . $row, '#' . substr($trx['order_id'], -8));
                $sheet->setCellValue('B' . $row, $trx['user_name']);
                $sheet->setCellValue('C' . $row, $trx['user_email']);
                $sheet->setCellValue('D' . $row, getPackageLabel($trx['package']));
                
                // ✅ FIX: Set as NUMBER dengan format
                $sheet->setCellValue('E' . $row, (int)$trx['gems']);
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
                
                $sheet->setCellValue('F' . $row, (int)$trx['amount']);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
                
                $sheet->setCellValue('G' . $row, getStatusLabel($trx['transaction_status']));
                $sheet->setCellValue('H' . $row, formatDate($trx['created_at']));

                // Status color coding
                $statusColor = 'FFF1F5F9';
                if ($trx['transaction_status'] === 'settlement') {
                    $statusColor = 'FFD1FAE5';
                } elseif ($trx['transaction_status'] === 'pending') {
                    $statusColor = 'FFFEF3C7';
                } elseif (in_array($trx['transaction_status'], ['expire', 'cancel'])) {
                    $statusColor = 'FFFEE2E2';
                }
                $sheet->getStyle('G' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($statusColor);

                // Alternating row colors
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
                }

                // Borders
                $sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $row++;
            }

            // Total row
            $totalRow = $row + 1;
            $sheet->mergeCells('A' . $totalRow . ':E' . $totalRow);
            $sheet->setCellValue('A' . $totalRow, 'TOTAL PENDAPATAN (SETTLEMENT)');
            
            // ✅ FIX: Set total as NUMBER
            $sheet->setCellValue('F' . $totalRow, (int)$totalRevenue);
            $sheet->getStyle('F' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
            
            $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEDE9FE');
            $sheet->getStyle('A' . $totalRow . ':H' . $totalRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
            $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Column widths
            $sheet->getColumnDimension('A')->setWidth(18);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(30);
            $sheet->getColumnDimension('D')->setWidth(12);
            $sheet->getColumnDimension('E')->setWidth(12);
            $sheet->getColumnDimension('F')->setWidth(18);
            $sheet->getColumnDimension('G')->setWidth(14);
            $sheet->getColumnDimension('H')->setWidth(22);

            // ✅ Clear ALL output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Headers untuk download Excel
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="Transaksi_JagoNugas_' . date('Y-m-d_H-i') . '.xlsx"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: cache, must-revalidate');
            header('Pragma: public');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            ob_end_clean();
            error_log("Excel export error: " . $e->getMessage());
            http_response_code(500);
            exit('Error generating Excel file: ' . $e->getMessage());
        }

    } else {
        // CSV fallback
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Transaksi_JagoNugas_' . date('Y-m-d_H-i') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['Order ID', 'User Name', 'Email', 'Package', 'Gems', 'Amount (Rp)', 'Status', 'Date']);
        
        foreach ($transactions as $trx) {
            fputcsv($output, [
                '#' . substr($trx['order_id'], -8),
                $trx['user_name'],
                $trx['user_email'],
                getPackageLabel($trx['package']),
                $trx['gems'],
                number_format($trx['amount'], 0, ',', '.'),
                getStatusLabel($trx['transaction_status']),
                formatDate($trx['created_at'])
            ]);
        }
        
        fputcsv($output, ['', '', '', '', 'TOTAL', number_format($totalRevenue, 0, ',', '.'), '', '']);
        fclose($output);
        exit;
    }
}

// =======================================
// EXPORT PDF
// =======================================
if ($format === 'pdf') {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        try {
            require __DIR__ . '/vendor/autoload.php';
            
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);
            $options->set('defaultFont', 'Arial');
            $options->set('isRemoteEnabled', false);

            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: Arial, sans-serif; color: #333; font-size: 10px; }
                    .container { padding: 30px; }
                    .header-section { background: #667eea; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
                    h1 { font-size: 22px; font-weight: bold; margin-bottom: 5px; }
                    .subtitle { font-size: 12px; margin-bottom: 10px; }
                    .info-grid { width: 100%; margin: 15px 0; border: 1px solid #e0e0e0; border-radius: 8px; background: #f9f9f9; }
                    .info-row { border-bottom: 1px solid #e0e0e0; padding: 8px 12px; }
                    .info-label { font-weight: bold; display: inline-block; width: 30%; }
                    .info-value { display: inline-block; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    thead { background: #667eea; color: white; }
                    th { padding: 10px 6px; text-align: left; font-size: 10px; font-weight: bold; border: 1px solid #5568d3; }
                    td { padding: 8px 6px; border: 1px solid #e0e0e0; font-size: 9px; }
                    tbody tr:nth-child(even) { background: #f9f9f9; }
                    .total-row { background: #667eea; color: white; font-weight: bold; font-size: 11px; }
                    .total-row td { border: 1px solid #5568d3; padding: 12px 6px; }
                    .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #e0e0e0; font-size: 9px; color: #666; text-align: center; }
                    .status-badge { padding: 3px 8px; border-radius: 10px; font-weight: bold; font-size: 8px; display: inline-block; }
                    .status-success { background: #d1fae5; color: #059669; }
                    .status-pending { background: #fef3c7; color: #92400e; }
                    .status-danger { background: #fee2e2; color: #dc2626; }
                    .package-badge { padding: 2px 6px; border-radius: 6px; font-size: 8px; font-weight: bold; }
                    .package-basic { background: #dbeafe; color: #2563eb; }
                    .package-pro { background: #ede9fe; color: #7c3aed; }
                    .package-plus { background: #fef3c7; color: #92400e; }
                    .user-deleted { color: #dc2626; font-style: italic; font-weight: bold; }
                    small { font-size: 8px; color: #666; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header-section">
                        <h1>LAPORAN TRANSAKSI GEMS</h1>
                        <p class="subtitle">JagoNugas E-Learning Platform</p>
                        <p style="font-size: 10px;">Dicetak: ' . date('d M Y H:i:s') . ' WIB</p>
                    </div>
                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label">Total Transaksi:</span>
                            <span class="info-value"><strong>' . count($transactions) . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total Pendapatan:</span>
                            <span class="info-value"><strong>Rp ' . number_format($totalRevenue, 0, ',', '.') . '</strong></span>
                        </div>';

            if (!empty($search)) {
                $html .= '<div class="info-row"><span class="info-label">Filter Pencarian:</span><span class="info-value">' . htmlspecialchars($search) . '</span></div>';
            }
            if ($filter !== 'all') {
                $html .= '<div class="info-row"><span class="info-label">Filter Status:</span><span class="info-value">' . ucfirst($filter) . '</span></div>';
            }
            if (!empty($dateFrom) || !empty($dateTo)) {
                $dateRange = ($dateFrom ?: 'Awal') . ' s/d ' . ($dateTo ?: 'Sekarang');
                $html .= '<div class="info-row"><span class="info-label">Periode:</span><span class="info-value">' . $dateRange . '</span></div>';
            }

            $html .= '</div><table><thead><tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 12%;">Order ID</th>
                        <th style="width: 20%;">User</th>
                        <th style="width: 10%;">Package</th>
                        <th style="width: 8%;">Gems</th>
                        <th style="width: 13%;">Amount</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 22%;">Date</th>
                    </tr></thead><tbody>';

            $no = 1;
            foreach ($transactions as $trx) {
                $statusClass = $trx['transaction_status'] === 'settlement' ? 'status-success' : 
                              ($trx['transaction_status'] === 'pending' ? 'status-pending' : 'status-danger');
                $packageClass = 'package-' . $trx['package'];
                $isDeleted = ($trx['user_name'] === 'User Deleted');
                
                $html .= '<tr><td style="text-align: center;">' . $no++ . '</td>
                         <td><strong>#' . htmlspecialchars(substr($trx['order_id'], -8)) . '</strong></td><td>';
                
                if ($isDeleted) {
                    $html .= '<span class="user-deleted">User Deleted</span><br><small>ID: ' . htmlspecialchars($trx['user_id']) . '</small>';
                } else {
                    $html .= '<strong>' . htmlspecialchars($trx['user_name']) . '</strong><br><small>' . htmlspecialchars($trx['user_email']) . '</small>';
                }
                
                $html .= '</td><td><span class="package-badge ' . $packageClass . '">' . getPackageLabel($trx['package']) . '</span></td>
                         <td style="text-align: center;"><strong>' . number_format($trx['gems']) . '</strong></td>
                         <td><strong>Rp ' . number_format($trx['amount'], 0, ',', '.') . '</strong></td>
                         <td><span class="status-badge ' . $statusClass . '">' . getStatusLabel($trx['transaction_status']) . '</span></td>
                         <td><small>' . formatDate($trx['created_at']) . '</small>';
                
                if ($trx['paid_at'] && $trx['transaction_status'] === 'settlement') {
                    $html .= '<br><small style="color: #10b981;">Paid: ' . formatDate($trx['paid_at']) . '</small>';
                }
                
                $html .= '</td></tr>';
            }

            if (empty($transactions)) {
                $html .= '<tr><td colspan="8" style="text-align: center; padding: 30px; color: #999;">Tidak ada transaksi ditemukan</td></tr>';
            }

            $html .= '<tr class="total-row">
                        <td colspan="5" style="text-align: right;"><strong>TOTAL PENDAPATAN (SETTLEMENT):</strong></td>
                        <td><strong>Rp ' . number_format($totalRevenue, 0, ',', '.') . '</strong></td>
                        <td colspan="2" style="text-align: center;"><strong>' . $countSettlement . ' Transaksi</strong></td>
                    </tr></tbody></table>
                    <div class="footer">
                        <p><strong>Laporan ini dibuat secara otomatis oleh sistem JagoNugas</strong></p>
                        <p>&copy; ' . date('Y') . ' JagoNugas Admin System - All Rights Reserved</p>
                        <p style="margin-top: 5px; font-size: 8px;">Catatan: Data yang ditampilkan adalah data aktual per tanggal cetak. Transaksi dengan status "settlement" adalah transaksi berhasil yang sudah terkonfirmasi pembayaran.</p>
                    </div>
                </div>
            </body>
            </html>';

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $canvas = $dompdf->getCanvas();
            $canvas->page_text(720, 560, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", null, 8, array(0, 0, 0));

            // ✅ Clear ALL output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Headers untuk download PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="Laporan_Transaksi_JagoNugas_' . date('Y-m-d_H-i') . '.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $dompdf->output();
            exit;

        } catch (Exception $e) {
            ob_end_clean();
            error_log("PDF export error: " . $e->getMessage());
            http_response_code(500);
            exit('Error generating PDF: ' . $e->getMessage());
        }

    } else {
        ob_end_clean();
        http_response_code(503);
        exit('Dompdf library not installed. Run: composer require dompdf/dompdf');
    }
}

// Default: error
ob_end_clean();
http_response_code(400);
exit('Invalid export format');
