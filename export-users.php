<?php
/**
 * Export Users - All Formats (Excel, PDF, CSV)
 * Compatible with Azure, cPanel, Local XAMPP
 * Version: 2.0 - Production Ready
 * Author: JagoNugas Admin System
 */

// ✅ Configuration
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 Security: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    exit('Unauthorized Access');
}

// ✅ Get & validate parameters
$format = $_GET['format'] ?? 'excel';
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$validFormats = ['excel', 'pdf', 'csv'];
if (!in_array($format, $validFormats)) {
    ob_end_clean();
    http_response_code(400);
    exit('Invalid format. Use: excel, pdf, or csv');
}

// Initialize
$users = [];
$globalStats = [
    'total' => 0,
    'students' => 0,
    'mentors' => 0,
    'subscribed' => 0
];
$filteredStats = [
    'total' => 0,
    'students' => 0,
    'mentors' => 0,
    'subscribed' => 0
];

// ✅ Helper: Load vendor autoload
function loadVendor() {
    $vendorPaths = [
        __DIR__ . '/vendor/autoload.php',
        '/home/site/wwwroot/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];
    
    foreach ($vendorPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
}

// ✅ Helper functions
function formatDate($date) {
    if (!$date) return '-';
    return date('d M Y, H:i', strtotime($date));
}

function formatDateShort($date) {
    if (!$date) return '-';
    return date('d-m-Y', strtotime($date));
}

function getStatusLabel($user) {
    if ($user['role'] === 'mentor') {
        return ($user['is_verified'] == 1) ? 'Active' : 'Pending';
    }
    return $user['membership_name'] ? 'Active' : 'Free';
}

function getMembershipLabel($user) {
    if ($user['role'] === 'mentor') {
        return '-';
    }
    return $user['membership_name'] ?? 'Free';
}

function formatSemester($semester) {
    if (!$semester || $semester === '' || $semester === '0') {
        return '-';
    }
    return is_numeric($semester) ? 'Sem ' . $semester : $semester;
}

// =======================================
// ✅ QUERY DATA
// =======================================
try {
    // Main query with filters
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.role,
            u.program_studi,
            u.semester,
            u.created_at,
            u.is_verified,
            m.status as membership_status,
            m.start_date as membership_start,
            m.end_date as membership_end,
            gp.name as membership_name,
            gp.code as membership_code
        FROM users u
        LEFT JOIN memberships m ON u.id = m.user_id 
            AND m.status = 'active' 
            AND m.end_date >= NOW()
        LEFT JOIN gem_packages gp ON m.membership_id = gp.id
        WHERE u.role IN ('student', 'mentor')
    ";

    $params = [];

    // Apply filters
    if ($filter === 'subscribed') {
        $sql .= " AND m.id IS NOT NULL";
    } elseif ($filter === 'free') {
        $sql .= " AND m.id IS NULL AND u.role = 'student'";
    } elseif ($filter === 'students') {
        $sql .= " AND u.role = 'student'";
    } elseif ($filter === 'mentors') {
        $sql .= " AND u.role = 'mentor'";
    } elseif ($filter === 'pending') {
        $sql .= " AND u.role = 'mentor' AND u.is_verified = 0";
    }

    // Apply search
    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.program_studi LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= " ORDER BY u.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Global statistics (all users)
    $globalStats['students'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $globalStats['mentors'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor'")->fetchColumn();
    $globalStats['total'] = $globalStats['students'] + $globalStats['mentors'];
    $globalStats['subscribed'] = (int) $pdo->query("
        SELECT COUNT(DISTINCT u.id) FROM users u
        JOIN memberships m ON u.id = m.user_id
        WHERE m.status = 'active' AND m.end_date >= NOW()
    ")->fetchColumn();

    // ✅ Filtered statistics (dari hasil query)
    $filteredStats['total'] = count($users);
    foreach ($users as $user) {
        if ($user['role'] === 'student') {
            $filteredStats['students']++;
            if ($user['membership_name']) {
                $filteredStats['subscribed']++;
            }
        } elseif ($user['role'] === 'mentor') {
            $filteredStats['mentors']++;
        }
    }

} catch (PDOException $e) {
    error_log("Export users error: " . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    exit('Database error occurred');
}

// =======================================
// EXPORT EXCEL
// =======================================
if ($format === 'excel') {
    if (loadVendor()) {
        try {
            // ✅ Check ZIP extension
            if (!extension_loaded('zip')) {
                throw new Exception('ZIP extension not loaded. Enable in php.ini');
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Users Data');

            // ✅ Column widths
            $widths = ['A' => 6, 'B' => 25, 'C' => 30, 'D' => 12, 'E' => 20, 'F' => 8, 'G' => 15, 'H' => 12, 'I' => 15, 'J' => 15, 'K' => 18];
            foreach ($widths as $col => $width) {
                $sheet->getColumnDimension($col)->setWidth($width);
            }

            // 🎨 Header Section
            $sheet->setCellValue('A1', 'DATA PENGGUNA JAGONUGAS');
            $sheet->mergeCells('A1:K1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF667EEA');
            $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');

            $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d M Y H:i:s') . ' WIB');
            $sheet->mergeCells('A2:K2');
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // ✅ Statistics (Filtered vs Global)
            $infoRow = 3;
            $statsText = 'Filtered: ' . $filteredStats['total'] . ' users | Students: ' . $filteredStats['students'] . ' | Mentors: ' . $filteredStats['mentors'] . ' | Subscribed: ' . $filteredStats['subscribed'];
            
            if ($filter !== 'all' || !empty($search)) {
                $statsText .= ' || GLOBAL: ' . $globalStats['total'] . ' total | ' . $globalStats['students'] . ' students | ' . $globalStats['mentors'] . ' mentors';
            }
            
            $sheet->setCellValue('A' . $infoRow, $statsText);
            $sheet->mergeCells('A' . $infoRow . ':K' . $infoRow);
            $sheet->getStyle('A' . $infoRow)->getFont()->setSize(9)->setBold(true);
            $infoRow++;

            // Filter info
            if (!empty($search)) {
                $sheet->setCellValue('A' . $infoRow, 'Pencarian: ' . $search);
                $sheet->mergeCells('A' . $infoRow . ':K' . $infoRow);
                $infoRow++;
            }
            if ($filter !== 'all') {
                $sheet->setCellValue('A' . $infoRow, 'Filter: ' . ucfirst($filter));
                $sheet->mergeCells('A' . $infoRow . ':K' . $infoRow);
                $infoRow++;
            }

            // Column headers
            $headerRow = $infoRow + 1;
            $headers = ['No', 'Nama', 'Email', 'Role', 'Program Studi', 'Sem', 'Membership', 'Status', 'Mulai', 'Berakhir', 'Tgl Daftar'];
            $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];
            
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

            foreach ($users as $user) {
                $sheet->setCellValue('A' . $row, $no);
                $sheet->setCellValue('B' . $row, $user['name']);
                $sheet->setCellValue('C' . $row, $user['email']);
                $sheet->setCellValue('D' . $row, ucfirst($user['role']));
                $sheet->setCellValue('E' . $row, $user['program_studi'] ?? '-');
                $sheet->setCellValue('F' . $row, formatSemester($user['semester']));
                $sheet->setCellValue('G' . $row, getMembershipLabel($user));
                $sheet->setCellValue('H' . $row, getStatusLabel($user));
                $sheet->setCellValue('I' . $row, formatDateShort($user['membership_start']));
                $sheet->setCellValue('J' . $row, formatDateShort($user['membership_end']));
                $sheet->setCellValue('K' . $row, date('d-m-Y H:i', strtotime($user['created_at'])));

                // ✅ Styling
                $cellRange = 'A' . $row . ':K' . $row;
                $sheet->getStyle($cellRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                
                // Alternate colors
                if ($no % 2 == 0) {
                    $sheet->getStyle($cellRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
                }

                // Status color
                $status = getStatusLabel($user);
                $statusColor = ($status === 'Active') ? 'FFD1FAE5' : (($status === 'Pending') ? 'FFFEF3C7' : 'FFF1F5F9');
                $sheet->getStyle('H' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($statusColor);

                $row++;
                $no++;
            }

            // ✅ No data message
            if (empty($users)) {
                $sheet->setCellValue('A' . $row, 'Tidak ada data ditemukan');
                $sheet->mergeCells('A' . $row . ':K' . $row);
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->getColor()->setARGB('FF999999');
            }

            // Freeze header
            $sheet->freezePane('A' . ($headerRow + 1));

            // ✅ Clear buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="Users_JagoNugas_' . date('Ymd_His') . '.xlsx"');
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
        // ✅ CSV Fallback
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Users_JagoNugas_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, ['No', 'Nama', 'Email', 'Role', 'Program Studi', 'Semester', 'Membership', 'Status', 'Tgl Mulai', 'Tgl Berakhir', 'Tgl Daftar']);
        
        $no = 1;
        foreach ($users as $user) {
            fputcsv($output, [
                $no++,
                $user['name'],
                $user['email'],
                ucfirst($user['role']),
                $user['program_studi'] ?? '-',
                formatSemester($user['semester']),
                getMembershipLabel($user),
                getStatusLabel($user),
                formatDateShort($user['membership_start']),
                formatDateShort($user['membership_end']),
                date('d-m-Y H:i', strtotime($user['created_at']))
            ]);
        }
        
        fclose($output);
        exit;
    }
}

// =======================================
// EXPORT PDF
// =======================================
if ($format === 'pdf') {
    if (loadVendor() && class_exists('\Dompdf\Dompdf')) {
        try {
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
                    .stats { background: #f0f0f0; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 9px; }
                    .stats strong { color: #667eea; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    thead { background: #667eea; color: white; }
                    th { padding: 8px 4px; text-align: left; font-size: 9px; border: 1px solid #5568d3; }
                    td { padding: 6px 4px; border: 1px solid #e0e0e0; font-size: 8px; }
                    tbody tr:nth-child(even) { background: #f9f9f9; }
                    .status-active { background: #d1fae5; color: #059669; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 7px; }
                    .status-pending { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 7px; }
                    .status-free { background: #e2e3e5; color: #383d41; padding: 2px 6px; border-radius: 4px; font-size: 7px; }
                    .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e0e0e0; font-size: 8px; color: #666; text-align: center; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>📊 DATA PENGGUNA JAGONUGAS</h1>
                        <p class="subtitle">Laporan Lengkap Pengguna Terdaftar</p>
                        <p style="font-size: 9px; margin-top: 5px;">Dicetak: ' . date('d M Y H:i:s') . ' WIB</p>
                    </div>
                    <div class="stats">
                        <strong>Filtered:</strong> ' . $filteredStats['total'] . ' users | 
                        <strong>Students:</strong> ' . $filteredStats['students'] . ' | 
                        <strong>Mentors:</strong> ' . $filteredStats['mentors'] . ' | 
                        <strong>Subscribed:</strong> ' . $filteredStats['subscribed'];
            
            if ($filter !== 'all' || !empty($search)) {
                $html .= '<br><strong>GLOBAL:</strong> ' . $globalStats['total'] . ' total | ' . $globalStats['students'] . ' students | ' . $globalStats['mentors'] . ' mentors | ' . $globalStats['subscribed'] . ' subscribed';
            }
            
            if (!empty($search)) {
                $html .= '<br><strong>Pencarian:</strong> ' . htmlspecialchars($search);
            }
            if ($filter !== 'all') {
                $html .= ' | <strong>Filter:</strong> ' . ucfirst($filter);
            }
            
            $html .= '</div><table><thead><tr>
                        <th style="width: 4%;">#</th>
                        <th style="width: 18%;">Nama</th>
                        <th style="width: 20%;">Email</th>
                        <th style="width: 8%;">Role</th>
                        <th style="width: 15%;">Prodi</th>
                        <th style="width: 5%;">Sem</th>
                        <th style="width: 10%;">Membership</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 10%;">Tgl Daftar</th>
                    </tr></thead><tbody>';

            $no = 1;
            foreach ($users as $user) {
                $status = getStatusLabel($user);
                $statusClass = ($status === 'Active') ? 'status-active' : (($status === 'Pending') ? 'status-pending' : 'status-free');
                
                $html .= '<tr>
                    <td style="text-align: center;">' . $no++ . '</td>
                    <td><strong>' . htmlspecialchars($user['name']) . '</strong></td>
                    <td>' . htmlspecialchars($user['email']) . '</td>
                    <td>' . ucfirst($user['role']) . '</td>
                    <td>' . htmlspecialchars($user['program_studi'] ?? '-') . '</td>
                    <td style="text-align: center;">' . formatSemester($user['semester']) . '</td>
                    <td>' . getMembershipLabel($user) . '</td>
                    <td><span class="' . $statusClass . '">' . $status . '</span></td>
                    <td>' . formatDateShort($user['created_at']) . '</td>
                </tr>';
            }

            if (empty($users)) {
                $html .= '<tr><td colspan="9" style="text-align: center; padding: 20px; color: #999;">Tidak ada data ditemukan</td></tr>';
            }

            $html .= '</tbody></table>
                    <div class="footer">
                        <p><strong>© ' . date('Y') . ' JagoNugas Admin System</strong></p>
                        <p>Laporan dibuat otomatis oleh sistem</p>
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
            header('Content-Disposition: attachment; filename="Users_JagoNugas_' . date('Ymd_His') . '.pdf"');
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
        // ✅ HTML Print Fallback
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Export Users - JagoNugas</title>
            <style>
                @media print { .no-print { display: none !important; } }
                body { font-family: Arial, sans-serif; padding: 20px; }
                .toolbar { margin-bottom: 20px; }
                .btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px; }
                .btn:hover { background: #5568d3; }
                h1 { color: #667eea; }
                .stats { background: #f0f0f0; padding: 10px; border-radius: 5px; margin: 15px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
                th { background: #667eea; color: white; }
                tr:nth-child(even) { background: #f9f9f9; }
                .status-active { background: #d1fae5; color: #059669; padding: 2px 8px; border-radius: 4px; font-weight: bold; }
                .status-pending { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-weight: bold; }
                .status-free { background: #e2e3e5; color: #383d41; padding: 2px 8px; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="toolbar no-print">
                <button class="btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
                <button class="btn" onclick="window.close()" style="background: #999;">❌ Close</button>
            </div>
            
            <h1>📊 Data Pengguna JagoNugas</h1>
            
            <div class="stats">
                <strong>Filtered:</strong> <?= $filteredStats['total'] ?> users | 
                <strong>Students:</strong> <?= $filteredStats['students'] ?> | 
                <strong>Mentors:</strong> <?= $filteredStats['mentors'] ?> | 
                <strong>Subscribed:</strong> <?= $filteredStats['subscribed'] ?>
                <?php if ($filter !== 'all' || !empty($search)): ?>
                <br><strong>GLOBAL:</strong> <?= $globalStats['total'] ?> total | <?= $globalStats['students'] ?> students | <?= $globalStats['mentors'] ?> mentors
                <?php endif; ?>
                <br><strong>Dicetak:</strong> <?= date('d M Y H:i:s') ?>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Nama</th><th>Email</th><th>Role</th><th>Prodi</th>
                        <th>Sem</th><th>Membership</th><th>Status</th><th>Tgl Daftar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($users as $user): 
                        $status = getStatusLabel($user);
                        $statusClass = ($status === 'Active') ? 'status-active' : (($status === 'Pending') ? 'status-pending' : 'status-free');
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= ucfirst($user['role']) ?></td>
                        <td><?= htmlspecialchars($user['program_studi'] ?? '-') ?></td>
                        <td><?= formatSemester($user['semester']) ?></td>
                        <td><?= getMembershipLabel($user) ?></td>
                        <td><span class="<?= $statusClass ?>"><?= $status ?></span></td>
                        <td><?= formatDateShort($user['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($users)): ?>
                    <tr><td colspan="9" style="text-align: center; color: #999;">Tidak ada data ditemukan</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
}

// Default: Invalid format
ob_end_clean();
http_response_code(400);
exit('Invalid export format. Use: ?format=excel, pdf, or csv');
