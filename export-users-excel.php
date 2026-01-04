<?php
// export-users-excel.php - Professional Excel Export with Styling
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only admin can export
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

try {
    // Build query
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.role,
            u.program_studi,
            u.semester,
            u.created_at,
            u.gems_balance,
            u.gems,
            u.is_approved,
            u.total_rating,
            u.review_count,
            CASE 
                WHEN u.review_count > 0 THEN ROUND(u.total_rating / u.review_count, 1)
                ELSE 0 
            END as avg_rating,
            m.id as has_membership,
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

    if ($filter === 'subscribed') {
        $sql .= " AND m.id IS NOT NULL";
    } elseif ($filter === 'free') {
        $sql .= " AND m.id IS NULL AND u.role = 'student'";
    } elseif ($filter === 'students') {
        $sql .= " AND u.role = 'student'";
    } elseif ($filter === 'mentors') {
        $sql .= " AND u.role = 'mentor'";
    } elseif ($filter === 'pending') {
        $sql .= " AND u.role = 'mentor' AND u.is_approved = 0";
    }

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

    // Create Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Users Data');

    // ===== TITLE ROW =====
    $sheet->mergeCells('A1:L1');
    $sheet->setCellValue('A1', 'JagoNugas - Data Pengguna');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 18,
            'color' => ['rgb' => '667EEA']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // ===== INFO ROW =====
    $filterName = ucfirst($filter);
    $sheet->mergeCells('A2:L2');
    $infoText = "Filter: {$filterName} | Exported: " . date('d/m/Y H:i:s') . " | By: " . ($_SESSION['name'] ?? 'Admin');
    $sheet->setCellValue('A2', $infoText);
    $sheet->getStyle('A2')->applyFromArray([
        'font' => ['size' => 10, 'italic' => true, 'color' => ['rgb' => '64748B']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // ===== HEADER ROW =====
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '667EEA']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'FFFFFF']
            ]
        ]
    ];

    $headers = ['ID', 'Nama', 'Email', 'Role', 'Program Studi', 'Semester', 'Membership', 'Status', 'Gems', 'Rating', 'Review', 'Tanggal Daftar'];
    $sheet->fromArray($headers, null, 'A4');
    $sheet->getStyle('A4:L4')->applyFromArray($headerStyle);
    $sheet->getRowDimension(4)->setRowHeight(25);

    // Freeze panes (header tetap visible saat scroll)
    $sheet->freezePane('A5');

    // ===== COLUMN WIDTHS =====
    $columnWidths = [
        'A' => 8,   // ID
        'B' => 25,  // Nama
        'C' => 30,  // Email
        'D' => 12,  // Role
        'E' => 25,  // Program Studi
        'F' => 10,  // Semester
        'G' => 15,  // Membership
        'H' => 12,  // Status
        'I' => 12,  // Gems
        'J' => 10,  // Rating
        'K' => 10,  // Review
        'L' => 20   // Tanggal
    ];

    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    // ===== DATA ROWS =====
    $row = 5;
    foreach ($users as $user) {
        // Determine status
        if ($user['role'] === 'mentor') {
            $isApproved = $user['is_approved'] ?? 0;
            if ($isApproved == 1) {
                $status = 'Active';
                $statusColor = 'D1FAE5'; // Green
                $statusTextColor = '059669';
            } elseif ($isApproved == 2) {
                $status = 'Rejected';
                $statusColor = 'FEE2E2'; // Red
                $statusTextColor = 'DC2626';
            } else {
                $status = 'Pending';
                $statusColor = 'FEF3C7'; // Yellow
                $statusTextColor = '92400E';
            }
        } else {
            if ($user['has_membership']) {
                if ($user['membership_end'] && strtotime($user['membership_end']) < time()) {
                    $status = 'Expired';
                    $statusColor = 'FEE2E2';
                    $statusTextColor = 'DC2626';
                } else {
                    $status = 'Active';
                    $statusColor = 'D1FAE5';
                    $statusTextColor = '059669';
                }
            } else {
                $status = 'Free';
                $statusColor = 'F1F5F9';
                $statusTextColor = '64748B';
            }
        }

        $membership = $user['role'] === 'student' 
            ? ($user['membership_name'] ?? 'Free') 
            : '-';

        // Membership color
        if ($membership === 'Pro') {
            $membershipColor = 'EDE9FE'; // Purple
            $membershipTextColor = '7C3AED';
        } elseif ($membership === 'Basic') {
            $membershipColor = 'DBEAFE'; // Blue
            $membershipTextColor = '2563EB';
        } elseif ($membership === 'Plus') {
            $membershipColor = 'FEF3C7'; // Yellow
            $membershipTextColor = '92400E';
        } else {
            $membershipColor = 'F1F5F9'; // Gray
            $membershipTextColor = '64748B';
        }

        $rating = $user['role'] === 'mentor' && $user['review_count'] > 0
            ? number_format($user['avg_rating'], 1)
            : '-';

        $reviewCount = $user['role'] === 'mentor'
            ? $user['review_count']
            : '-';

        $data = [
            $user['id'],
            $user['name'],
            $user['email'],
            ucfirst($user['role']),
            $user['program_studi'] ?? '-',
            $user['semester'] ?? '-',
            $membership,
            $status,
            number_format($user['gems_balance'] ?? $user['gems'] ?? 0),
            $rating,
            $reviewCount,
            date('d/m/Y H:i', strtotime($user['created_at']))
        ];

        $sheet->fromArray($data, null, 'A' . $row);
        
        // Apply Membership styling (Column G)
        $sheet->getStyle('G' . $row)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $membershipColor]
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $membershipTextColor]
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Apply Status styling (Column H)
        $sheet->getStyle('H' . $row)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $statusColor]
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $statusTextColor]
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Alternate row colors
        if ($row % 2 == 0) {
            $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC']
                ]
            ]);
        }

        // Center alignment for specific columns
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
    }

    // ===== BORDERS FOR ALL DATA =====
    $sheet->getStyle('A4:L' . ($row - 1))->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E2E8F0']
            ]
        ]
    ]);

    // ===== SUMMARY SECTION =====
    $row += 2;
    $sheet->setCellValue('A' . $row, 'SUMMARY');
    $sheet->getStyle('A' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '667EEA']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EEF2FF']
        ]
    ]);
    $sheet->mergeCells('A' . $row . ':B' . $row);
    
    $row++;
    $summaryData = [
        ['Total Users', count($users)],
        ['Filter', ucfirst($filter)],
        ['Search Query', !empty($search) ? $search : '-'],
        ['Exported At', date('d/m/Y H:i:s')],
        ['Exported By', $_SESSION['name'] ?? 'Admin']
    ];

    foreach ($summaryData as $summaryRow) {
        $sheet->setCellValue('A' . $row, $summaryRow[0]);
        $sheet->setCellValue('B' . $row, $summaryRow[1]);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
    }

    // Generate filename
    $timestamp = date('Y-m-d_His');
    $filterName = ucfirst($filter);
    $filename = "JagoNugas_Users_{$filterName}_{$timestamp}.xlsx";

    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Export users Excel error: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal export data: ' . $e->getMessage();
    header("Location: admin-users.php");
    exit;
}
?>
