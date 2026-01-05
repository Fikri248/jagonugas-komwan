<?php
/**
 * Export Users - All Formats (Excel, PDF, CSV)
 * Compatible with Azure, cPanel, Local XAMPP
 * Author: JagoNugas Admin System
 */

// ✅ Start output buffering immediately
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    http_response_code(403);
    exit('Unauthorized Access');
}

// Get parameters
$format = $_GET['format'] ?? 'excel'; // excel, pdf, csv
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Initialize variables
$users = [];
$totalUsers = 0;
$countStudents = 0;
$countMentors = 0;
$countSubscribed = 0;

// ✅ Query data users
try {
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
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $sql .= " ORDER BY u.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics
    $totalUsers = count($users);
    
    $stmtStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $countStudents = (int) $stmtStudents->fetchColumn();
    
    $stmtMentors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor'");
    $countMentors = (int) $stmtMentors->fetchColumn();
    
    $stmtSubscribed = $pdo->query("
        SELECT COUNT(DISTINCT u.id) FROM users u
        JOIN memberships m ON u.id = m.user_id
        WHERE m.status = 'active' AND m.end_date >= NOW()
    ");
    $countSubscribed = (int) $stmtSubscribed->fetchColumn();

} catch (PDOException $e) {
    error_log("Export users error: " . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
}

// Helper functions
function formatDate($date) {
    if (!$date) return '-';
    return date('d M Y, H:i', strtotime($date));
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

// =======================================
// EXPORT EXCEL
// =======================================
if ($format === 'excel') {
    // Check vendor paths (multi-environment support)
    $vendorPaths = [
        __DIR__ . '/vendor/autoload.php',
        '/home/site/wwwroot/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];

    $vendorFound = false;
    foreach ($vendorPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $vendorFound = true;
            break;
        }
    }

    if ($vendorFound) {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Users Data');

            // Column widths
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(30);
            $sheet->getColumnDimension('D')->setWidth(12);
            $sheet->getColumnDimension('E')->setWidth(20);
            $sheet->getColumnDimension('F')->setWidth(10);
            $sheet->getColumnDimension('G')->setWidth(15);
            $sheet->getColumnDimension('H')->setWidth(12);
            $sheet->getColumnDimension('I')->setWidth(18);
            $sheet->getColumnDimension('J')->setWidth(18);
            $sheet->getColumnDimension('K')->setWidth(18);

            // 🎨 Header Section
            $sheet->setCellValue('A1', 'DATA PENGGUNA JAGONUGAS');
            $sheet->mergeCells('A1:K1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF667EEA');
            $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');

            $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d M Y H:i:s'));
            $sheet->mergeCells('A2:K2');
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);

            $sheet->setCellValue('A3', 'Total: ' . $totalUsers . ' users | Students: ' . $countStudents . ' | Mentors: ' . $countMentors . ' | Subscribed: ' . $countSubscribed);
            $sheet->mergeCells('A3:K3');
            $sheet->getStyle('A3')->getFont()->setSize(9);

            // Filter info
            $infoRow = 4;
            if (!empty($search)) {
                $sheet->setCellValue('A' . $infoRow, 'Filter Pencarian: ' . $search);
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

            // Data rows
            $row = $headerRow + 1;
            $no = 1;

            foreach ($users as $user) {
                $sheet->setCellValue('A' . $row, $no);
                $sheet->setCellValue('B' . $row, $user['name']);
                $sheet->setCellValue('C' . $row, $user['email']);
                $sheet->setCellValue('D' . $row, ucfirst($user['role']));
                $sheet->setCellValue('E' . $row, $user['program_studi'] ?? '-');
                $sheet->setCellValue('F' . $row, $user['semester'] ?? '-');
                $sheet->setCellValue('G' . $row, getMembershipLabel($user));
                $sheet->setCellValue('H' . $row, getStatusLabel($user));
                $sheet->setCellValue('I' . $row, $user['membership_start'] ? date('d-m-Y', strtotime($user['membership_start'])) : '-');
                $sheet->setCellValue('J' . $row, $user['membership_end'] ? date('d-m-Y', strtotime($user['membership_end'])) : '-');
                $sheet->setCellValue('K' . $row, date('d-m-Y H:i', strtotime($user['created_at'])));

                // Styling
                $cellRange = 'A' . $row . ':K' . $row;
                $sheet->getStyle($cellRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                
                // Alternate colors
                if ($no % 2 == 0) {
                    $sheet->getStyle($cellRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
                }

                // Status color
                $statusColor = 'FFF1F5F9';
                $status = getStatusLabel($user);
                if ($status === 'Active') {
                    $statusColor = 'FFD1FAE5';
                } elseif ($status === 'Pending') {
                    $statusColor = 'FFFEF3C7';
                }
                $sheet->getStyle('H' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($statusColor);

                $row++;
                $no++;
            }

            // Freeze header
            $sheet->freezePane('A' . ($headerRow + 1));

            // ✅ Clear ALL output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Download headers
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="Users_JagoNugas_' . date('Y-m-d_H-i') . '.xlsx"');
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
        // CSV Fallback
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Users_JagoNugas_' . date('Y-m-d_H-i') . '.csv"');
        header('Pragma: no-cache');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        
        fputcsv($output, ['No', 'Nama', 'Email', 'Role', 'Program Studi', 'Semester', 'Membership', 'Status', 'Tgl Mulai', 'Tgl Berakhir', 'Tgl Daftar']);
        
        $no = 1;
        foreach ($users as $user) {
            fputcsv($output, [
                $no++,
                $user['name'],
                $user['email'],
                ucfirst($user['role']),
                $user['program_studi'] ?? '-',
                $user['semester'] ?? '-',
                getMembershipLabel($user),
                getStatusLabel($user),
                $user['membership_start'] ? date('d-m-Y', strtotime($user['membership_start'])) : '-',
                $user['membership_end'] ? date('d-m-Y', strtotime($user['membership_end'])) : '-',
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
    // Check vendor paths
    $vendorPaths = [
        __DIR__ . '/vendor/autoload.php',
        '/home/site/wwwroot/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];

    $vendorFound = false;
    foreach ($vendorPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $vendorFound = true;
            break;
        }
    }

    if ($vendorFound && class_exists('\Dompdf\Dompdf')) {
        try {
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);
            $options->set('defaultFont', 'Arial');

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
                    .stats { background: #f0f0f0; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
                    .stats strong { color: #667eea; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    thead { background: #667eea; color: white; }
                    th { padding: 8px 5px; text-align: left; font-size: 9px; border: 1px solid #5568d3; }
                    td { padding: 6px 5px; border: 1px solid #e0e0e0; font-size: 8px; }
                    tbody tr:nth-child(even) { background: #f9f9f9; }
                    .status-active { background: #d1fae5; color: #059669; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
                    .status-pending { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
                    .status-free { background: #e2e3e5; color: #383d41; padding: 2px 6px; border-radius: 4px; }
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
                        <strong>Total Pengguna:</strong> ' . $totalUsers . ' | 
                        <strong>Students:</strong> ' . $countStudents . ' | 
                        <strong>Mentors:</strong> ' . $countMentors . ' | 
                        <strong>Subscribed:</strong> ' . $countSubscribed;
            
            if (!empty($search)) {
                $html .= ' | <strong>Pencarian:</strong> ' . htmlspecialchars($search);
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
                    <td style="text-align: center;">' . ($user['semester'] ?? '-') . '</td>
                    <td>' . getMembershipLabel($user) . '</td>
                    <td><span class="' . $statusClass . '">' . $status . '</span></td>
                    <td>' . date('d-m-Y', strtotime($user['created_at'])) . '</td>
                </tr>';
            }

            if (empty($users)) {
                $html .= '<tr><td colspan="9" style="text-align: center; padding: 20px; color: #999;">Tidak ada data</td></tr>';
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

            // ✅ Clear buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="Users_JagoNugas_' . date('Y-m-d_H-i') . '.pdf"');
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
        // HTML Print Fallback
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
                .btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #667eea; color: white; }
                tr:nth-child(even) { background: #f9f9f9; }
            </style>
        </head>
        <body>
            <div class="toolbar no-print">
                <button class="btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
                <button class="btn" onclick="window.close()" style="background: #999;">❌ Close</button>
            </div>
            <h1>📊 Data Pengguna JagoNugas</h1>
            <p><strong>Total:</strong> <?= $totalUsers ?> users | <strong>Dicetak:</strong> <?= date('d M Y H:i') ?></p>
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
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= ucfirst($user['role']) ?></td>
                        <td><?= htmlspecialchars($user['program_studi'] ?? '-') ?></td>
                        <td><?= $user['semester'] ?? '-' ?></td>
                        <td><?= getMembershipLabel($user) ?></td>
                        <td><?= getStatusLabel($user) ?></td>
                        <td><?= date('d-m-Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
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
exit('Invalid export format. Use: ?format=excel or ?format=pdf');
?>
