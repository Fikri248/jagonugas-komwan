<?php
/**
 * Export Transactions - All Formats (Excel, PDF, CSV)
 * Production Ready - Optimized & Secure
 * Version: 2.0
 * Author: JagoNugas Admin System
 */

// ✅ Output buffering + error reporting
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 Security: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    exit('Unauthorized');
}

// 📊 Get & validate parameters
$format = $_GET['format'] ?? 'excel';
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// 🎯 Validate format
$validFormats = ['excel', 'pdf', 'csv'];
if (!in_array($format, $validFormats)) {
    ob_end_clean();
    http_response_code(400);
    exit('Invalid format. Use: excel, pdf, or csv');
}

// ⚙️ Increase memory & execution time untuk large data
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

// Initialize
$transactions = [];
$totalRevenue = 0;
$countSettlement = 0;
$filteredRevenue = 0;
$filteredCount = 0;

// =======================================
// ✅ QUERY DATA DENGAN FILTER
// =======================================
try {
    // Main query
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

    // Apply filters
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

    // ✅ Total revenue GLOBAL (semua transaksi settlement)
    $stmtGlobalRevenue = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total,
            COUNT(*) as count
        FROM gem_transactions 
        WHERE transaction_status = 'settlement'
    ");
    $stmtGlobalRevenue->execute();
    $globalStats = $stmtGlobalRevenue->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = (int) $globalStats['total'];
    $countSettlement = (int) $globalStats['count'];

    // ✅ Revenue TER-FILTER (sesuai filter user)
    $sqlFiltered = "
        SELECT 
            COALESCE(SUM(gt.amount), 0) as total,
            COUNT(*) as count
        FROM gem_transactions gt
        LEFT JOIN users u ON gt.user_id = u.id
        WHERE gt.transaction_status = 'settlement'
    ";
    
    $paramsFiltered = [];
    
    if (!empty($search)) {
        $sqlFiltered .= " AND (gt.order_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $paramsFiltered[] = "%$search%";
        $paramsFiltered[] = "%$search%";
        $paramsFiltered[] = "%$search%";
    }
    
    if (!empty($dateFrom)) {
        $sqlFiltered .= " AND DATE(gt.created_at) >= ?";
        $paramsFiltered[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $sqlFiltered .= " AND DATE(gt.created_at) <= ?";
        $paramsFiltered[] = $dateTo;
    }
    
    $stmtFiltered = $pdo->prepare($sqlFiltered);
    $stmtFiltered->execute($paramsFiltered);
    $filteredStats = $stmtFiltered->fetch(PDO::FETCH_ASSOC);
    $filteredRevenue = (int) $filteredStats['total'];
    $filteredCount = (int) $filteredStats['count'];

} catch (PDOException $e) {
    error_log("Export query error: " . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    exit('Database error occurred');
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

            // ✅ Check ZIP extension
            if (!extension_loaded('zip')) {
                throw new Exception('ZIP extension not enabled. Enable in php.ini: extension=zip');
            }
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Transactions');

            // 🎨 Header section
            $sheet->setCellValue('A1', 'LAPORAN TRANSAKSI GEMS - JAGONUGAS');
            $sheet->mergeCells('A1:I1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF667EEA');
            $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');

            $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d M Y H:i') . ' WIB');
            $sheet->mergeCells('A2:I2');
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // ✅ Filter info
            $infoRow = 3;
            $sheet->setCellValue('A' . $infoRow, 'Total Data: ' . count($transactions) . ' transaksi');
            $sheet->mergeCells('A' . $infoRow . ':I' . $infoRow);
            $infoRow++;

            if (!empty($search)) {
                $sheet->setCellValue('A' . $infoRow, 'Pencarian: ' . $search);
                $sheet->mergeCells('A' . $infoRow . ':I' . $infoRow);
                $infoRow++;
            }
            
            if ($filter !== 'all') {
                $sheet->setCellValue('A' . $infoRow, 'Filter Status: ' . ucfirst($filter));
                $sheet->mergeCells('A' . $infoRow . ':I' . $infoRow);
                $infoRow++;
            }
            
            if (!empty($dateFrom) || !empty($dateTo)) {
                $dateRange = 'Periode: ' . ($dateFrom ?: 'Awal') . ' s/d ' . ($dateTo ?: 'Sekarang');
                $sheet->setCellValue('A' . $infoRow, $dateRange);
                $sheet->mergeCells('A' . $infoRow . ':I' . $infoRow);
                $infoRow++;
            }

            // Column headers
            $headerRow = $infoRow + 1;
            $headers = ['No', 'Order ID', 'User Name', 'Email', 'Package', 'Gems', 'Amount (Rp)', 'Status', 'Date'];
            $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
            
            foreach ($columns as $index => $col) {
                $cell = $col . $headerRow;
                $sheet->setCellValue($cell, $headers[$index]);
                $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF667EEA');
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            }

            // ✅ Data rows
            $row = $headerRow + 1;
            $no = 1;
            
            foreach ($transactions as $trx) {
                $sheet->setCellValue('A' . $row, $no);
                $sheet->setCellValue('B' . $row, '#' . substr($trx['order_id'], -8));
                $sheet->setCellValue('C' . $row, $trx['user_name']);
                $sheet->setCellValue('D' . $row, $trx['user_email']);
                $sheet->setCellValue('E' . $row, getPackageLabel($trx['package']));
                
                // ✅ Numbers as integers with formatting
                $sheet->setCellValue('F' . $row, (int)$trx['gems']);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
                
                $sheet->setCellValue('G' . $row, (int)$trx['amount']);
                $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0');
                
                $sheet->setCellValue('H' . $row, getStatusLabel($trx['transaction_status']));
                $sheet->setCellValue('I' . $row, formatDate($trx['created_at']));

                // ✅ Status color
                $statusColor = 'FFF1F5F9';
                if ($trx['transaction_status'] === 'settlement') {
                    $statusColor = 'FFD1FAE5';
                } elseif ($trx['transaction_status'] === 'pending') {
                    $statusColor = 'FFFEF3C7';
                } elseif (in_array($trx['transaction_status'], ['expire', 'cancel'])) {
                    $statusColor = 'FFFEE2E2';
                }
                $sheet->getStyle('H' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($statusColor);

                // ✅ Alternating rows
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':I' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
                }

                // ✅ Borders
                $sheet->getStyle('A' . $row . ':I' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $row++;
                $no++;
            }

            // ✅ Total row (FILTERED data)
            $totalRow = $row + 1;
            $sheet->mergeCells('A' . $totalRow . ':F' . $totalRow);
            $sheet->setCellValue('A' . $totalRow, 'TOTAL FILTERED (SETTLEMENT)');
            
            $sheet->setCellValue('G' . $totalRow, (int)$filteredRevenue);
            $sheet->getStyle('G' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
            
            $sheet->setCellValue('H' . $totalRow, $filteredCount . ' trx');
            
            $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEDE9FE');
            $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
            $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // ✅ Global total (SEMUA settlement)
            if ($filteredRevenue !== $totalRevenue) {
                $globalRow = $totalRow + 1;
                $sheet->mergeCells('A' . $globalRow . ':F' . $globalRow);
                $sheet->setCellValue('A' . $globalRow, 'TOTAL GLOBAL (ALL TIME)');
                
                $sheet->setCellValue('G' . $globalRow, (int)$totalRevenue);
                $sheet->getStyle('G' . $globalRow)->getNumberFormat()->setFormatCode('#,##0');
                
                $sheet->setCellValue('H' . $globalRow, $countSettlement . ' trx');
                
                $sheet->getStyle('A' . $globalRow . ':I' . $globalRow)->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A' . $globalRow . ':I' . $globalRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFD1FAE5');
                $sheet->getStyle('A' . $globalRow . ':I' . $globalRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
            }

            // ✅ Column widths
            $sheet->getColumnDimension('A')->setWidth(6);
            $sheet->getColumnDimension('B')->setWidth(16);
            $sheet->getColumnDimension('C')->setWidth(22);
            $sheet->getColumnDimension('D')->setWidth(28);
            $sheet->getColumnDimension('E')->setWidth(12);
            $sheet->getColumnDimension('F')->setWidth(10);
            $sheet->getColumnDimension('G')->setWidth(16);
            $sheet->getColumnDimension('H')->setWidth(14);
            $sheet->getColumnDimension('I')->setWidth(20);

            // ✅ Freeze header
            $sheet->freezePane('A' . ($headerRow + 1));

            // ✅ Clear all buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Headers
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="Transaksi_JagoNugas_' . date('Ymd_His') . '.xlsx"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            ob_end_clean();
            error_log("Excel export error: " . $e->getMessage());
            http_response_code(500);
            exit('Error generating Excel: ' . $e->getMessage());
        }

    } else {
        // ✅ CSV fallback
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Transaksi_JagoNugas_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, ['No', 'Order ID', 'User Name', 'Email', 'Package', 'Gems', 'Amount (Rp)', 'Status', 'Date']);
        
        $no = 1;
        foreach ($transactions as $trx) {
            fputcsv($output, [
                $no++,
                '#' . substr($trx['order_id'], -8),
                $trx['user_name'],
                $trx['user_email'],
                getPackageLabel($trx['package']),
                number_format($trx['gems']),
                number_format($trx['amount'], 0, ',', '.'),
                getStatusLabel($trx['transaction_status']),
                formatDate($trx['created_at'])
            ]);
        }
        
        fputcsv($output, ['', '', '', '', '', 'TOTAL FILTERED', number_format($filteredRevenue, 0, ',', '.'), $filteredCount . ' trx', '']);
        
        if ($filteredRevenue !== $totalRevenue) {
            fputcsv($output, ['', '', '', '', '', 'TOTAL GLOBAL', number_format($totalRevenue, 0, ',', '.'), $countSettlement . ' trx', '']);
        }
        
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
                    body { font-family: Arial, sans-serif; color: #333; font-size: 9px; }
                    .container { padding: 20px; }
                    .header { background: #667eea; color: white; padding: 15px; text-align: center; border-radius: 8px; margin-bottom: 15px; }
                    h1 { font-size: 20px; margin-bottom: 5px; }
                    .subtitle { font-size: 10px; }
                    .info { background: #f0f0f0; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 9px; }
                    .info strong { color: #667eea; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    thead { background: #667eea; color: white; }
                    th { padding: 8px 4px; text-align: left; font-size: 9px; border: 1px solid #5568d3; }
                    td { padding: 6px 4px; border: 1px solid #e0e0e0; font-size: 8px; }
                    tbody tr:nth-child(even) { background: #f9f9f9; }
                    .total-row { background: #ede9fe; font-weight: bold; font-size: 9px; }
                    .total-row td { border: 1px solid #c4b5fd; padding: 10px 4px; }
                    .global-row { background: #d1fae5; font-weight: bold; font-size: 9px; }
                    .global-row td { border: 1px solid #a7f3d0; padding: 10px 4px; }
                    .status-success { background: #d1fae5; color: #059669; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 7px; }
                    .status-pending { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 7px; }
                    .status-danger { background: #fee2e2; color: #dc2626; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 7px; }
                    .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e0e0e0; font-size: 8px; color: #666; text-align: center; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>📊 LAPORAN TRANSAKSI GEMS</h1>
                        <p class="subtitle">JagoNugas E-Learning Platform</p>
                        <p style="font-size: 9px; margin-top: 5px;">Dicetak: ' . date('d M Y H:i:s') . ' WIB</p>
                    </div>
                    <div class="info">
                        <strong>Total Data:</strong> ' . count($transactions) . ' transaksi';
            
            if (!empty($search)) {
                $html .= ' | <strong>Pencarian:</strong> ' . htmlspecialchars($search);
            }
            if ($filter !== 'all') {
                $html .= ' | <strong>Filter:</strong> ' . ucfirst($filter);
            }
            if (!empty($dateFrom) || !empty($dateTo)) {
                $dateRange = ($dateFrom ?: 'Awal') . ' s/d ' . ($dateTo ?: 'Sekarang');
                $html .= ' | <strong>Periode:</strong> ' . $dateRange;
            }
            
            $html .= '</div><table><thead><tr>
                        <th style="width: 4%;">#</th>
                        <th style="width: 12%;">Order ID</th>
                        <th style="width: 18%;">User</th>
                        <th style="width: 10%;">Package</th>
                        <th style="width: 8%;">Gems</th>
                        <th style="width: 13%;">Amount</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 15%;">Date</th>
                    </tr></thead><tbody>';

            $no = 1;
            foreach ($transactions as $trx) {
                $status = getStatusLabel($trx['transaction_status']);
                $statusClass = ($trx['transaction_status'] === 'settlement') ? 'status-success' : 
                              (($trx['transaction_status'] === 'pending') ? 'status-pending' : 'status-danger');
                
                $html .= '<tr>
                    <td style="text-align: center;">' . $no++ . '</td>
                    <td><strong>#' . htmlspecialchars(substr($trx['order_id'], -8)) . '</strong></td>
                    <td><strong>' . htmlspecialchars($trx['user_name']) . '</strong><br><small>' . htmlspecialchars($trx['user_email']) . '</small></td>
                    <td>' . getPackageLabel($trx['package']) . '</td>
                    <td style="text-align: center;"><strong>' . number_format($trx['gems']) . '</strong></td>
                    <td><strong>Rp ' . number_format($trx['amount'], 0, ',', '.') . '</strong></td>
                    <td><span class="' . $statusClass . '">' . $status . '</span></td>
                    <td><small>' . formatDate($trx['created_at']) . '</small></td>
                </tr>';
            }

            if (empty($transactions)) {
                $html .= '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #999;">Tidak ada transaksi ditemukan</td></tr>';
            }

            // ✅ TOTAL FILTERED ROW
            $html .= '<tr class="total-row">
                        <td colspan="5" style="text-align: right;"><strong>TOTAL FILTERED (SETTLEMENT):</strong></td>
                        <td><strong>Rp ' . number_format($filteredRevenue, 0, ',', '.') . '</strong></td>
                        <td colspan="2" style="text-align: center;"><strong>' . $filteredCount . ' Transaksi</strong></td>
                    </tr>';
            
            // ✅ TOTAL GLOBAL ROW (jika berbeda)
            if ($filteredRevenue !== $totalRevenue) {
                $html .= '<tr class="global-row">
                            <td colspan="5" style="text-align: right;"><strong>TOTAL GLOBAL (ALL TIME):</strong></td>
                            <td><strong>Rp ' . number_format($totalRevenue, 0, ',', '.') . '</strong></td>
                            <td colspan="2" style="text-align: center;"><strong>' . $countSettlement . ' Transaksi</strong></td>
                        </tr>';
            }

            $html .= '</tbody></table>
                    <div class="footer">
                        <p><strong>© ' . date('Y') . ' JagoNugas Admin System - All Rights Reserved</strong></p>
                        <p>Laporan dibuat otomatis oleh sistem. Data settlement adalah transaksi yang sudah terkonfirmasi.</p>
                    </div>
                </div>
            </body>
            </html>';

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            // Page numbers
            $canvas = $dompdf->getCanvas();
            $canvas->page_text(720, 560, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", null, 8, array(0, 0, 0));

            // ✅ Clear buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="Laporan_Transaksi_JagoNugas_' . date('Ymd_His') . '.pdf"');
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

// Default: Invalid format
ob_end_clean();
http_response_code(400);
exit('Invalid export format');
