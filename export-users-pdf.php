<?php
// export-users-pdf.php - Professional PDF with VISIBLE HEADER
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

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

    $filterName = ucfirst($filter);
    $exportDate = date('d/m/Y H:i:s');
    $exportedBy = $_SESSION['name'] ?? 'Admin';
    $totalUsers = count($users);

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 20px; }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: "DejaVu Sans", Arial, sans-serif; 
            color: #1a1a1a; 
            background: white;
            font-size: 10px;
        }
        
        .header { 
            background: #667eea;
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .header h1 { 
            font-size: 20px; 
            margin-bottom: 5px; 
        }
        
        .header p { 
            font-size: 11px; 
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        
        .info-row { 
            margin: 4px 0;
            font-size: 10px;
        }
        
        .info-label { 
            font-weight: bold; 
            color: #333;
            display: inline-block;
            width: 110px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        /* ✅ HEADER TABLE DENGAN BACKGROUND GELAP */
        thead tr {
            background-color: #667eea !important;
        }
        
        thead th {
            background-color: #667eea !important;
            color: #ffffff !important;
            padding: 12px 6px !important;
            text-align: center !important;
            font-weight: bold !important;
            font-size: 9px !important;
            text-transform: uppercase !important;
            border: 1px solid #ffffff !important;
            letter-spacing: 0.3px;
        }
        
        tbody td {
            padding: 8px 6px;
            border: 1px solid #cccccc;
            font-size: 9px;
            color: #1a1a1a;
            vertical-align: middle;
        }
        
        tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .text-center { 
            text-align: center !important; 
        }
        
        .text-bold { 
            font-weight: bold !important; 
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: bold;
        }
        
        .badge-student { background: #d0e7ff; color: #0055cc; }
        .badge-mentor { background: #e9d5ff; color: #7c3aed; }
        .badge-free { background: #e5e5e5; color: #666666; }
        .badge-basic { background: #d0e7ff; color: #0055cc; }
        .badge-pro { background: #e9d5ff; color: #7c3aed; }
        .badge-plus { background: #fff3cd; color: #856404; }
        
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 8px;
            font-weight: bold;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-free { background: #e5e5e5; color: #666666; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-expired { background: #f8d7da; color: #721c24; }
        
        .summary {
            background: #f8f9fa;
            padding: 15px;
            border: 2px solid #667eea;
            margin-top: 20px;
        }
        
        .summary-title { 
            font-weight: bold; 
            color: #667eea; 
            margin-bottom: 10px; 
            font-size: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
        }
        
        .summary-row { 
            margin: 5px 0;
            font-size: 10px;
        }
        
        .summary-label { 
            font-weight: bold; 
            color: #333;
            display: inline-block;
            width: 130px;
        }
        
        .footer {
            margin-top: 25px;
            padding-top: 12px;
            border-top: 2px solid #667eea;
            text-align: center;
            font-size: 9px;
            color: #666666;
        }
    </style>
</head>
<body>';

    // HEADER
    $html .= '
    <div class="header">
        <h1>JagoNugas - Data Pengguna</h1>
        <p>Administrator Report System</p>
    </div>';

    // INFO SECTION
    $html .= '
    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Filter:</span>
            <span>' . htmlspecialchars($filterName) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Generated:</span>
            <span>' . $exportDate . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Exported By:</span>
            <span>' . htmlspecialchars($exportedBy) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Total Records:</span>
            <span><strong>' . $totalUsers . '</strong> users</span>
        </div>
    </div>';

    // TABLE START
    $html .= '
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Nama</th>
                <th style="width: 18%;">Email</th>
                <th style="width: 10%;">Role</th>
                <th style="width: 15%;">Program Studi</th>
                <th style="width: 12%;">Membership</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 8%;">Gems</th>
                <th style="width: 12%;">Rating</th>
            </tr>
        </thead>
        <tbody>';

    // TABLE DATA
    $no = 1;
    
    foreach ($users as $user) {
        $isMentor = ($user['role'] === 'mentor');
        
        // Role
        $roleBadge = $isMentor ? 'badge-mentor' : 'badge-student';
        $roleText = $isMentor ? 'Mentor' : 'Student';
        
        // Membership
        if ($isMentor) {
            $membershipBadge = 'badge-free';
            $membershipText = '-';
        } else {
            if ($user['membership_name']) {
                $membershipBadge = 'badge-' . strtolower($user['membership_code'] ?? 'pro');
                $membershipText = htmlspecialchars($user['membership_name']);
            } else {
                $membershipBadge = 'badge-free';
                $membershipText = 'Free';
            }
        }
        
        // Status
        if ($isMentor) {
            $isApproved = $user['is_approved'] ?? 0;
            if ($isApproved == 1) {
                $statusBadge = 'status-active';
                $statusText = 'Active';
            } elseif ($isApproved == 2) {
                $statusBadge = 'status-rejected';
                $statusText = 'Rejected';
            } else {
                $statusBadge = 'status-pending';
                $statusText = 'Pending';
            }
        } else {
            if ($user['has_membership']) {
                if ($user['membership_end'] && strtotime($user['membership_end']) < time()) {
                    $statusBadge = 'status-expired';
                    $statusText = 'Expired';
                } else {
                    $statusBadge = 'status-active';
                    $statusText = 'Active';
                }
            } else {
                $statusBadge = 'status-free';
                $statusText = 'Free';
            }
        }
        
        // Rating
        $rating = ($isMentor && $user['review_count'] > 0)
            ? number_format($user['avg_rating'], 1) . ' (' . $user['review_count'] . ')'
            : '-';
        
        $gems = number_format($user['gems_balance'] ?? $user['gems'] ?? 0);
        
        $html .= '
            <tr>
                <td class="text-center text-bold">' . $no . '</td>
                <td class="text-bold">' . htmlspecialchars($user['name']) . '</td>
                <td>' . htmlspecialchars($user['email']) . '</td>
                <td class="text-center"><span class="badge ' . $roleBadge . '">' . $roleText . '</span></td>
                <td>' . htmlspecialchars($user['program_studi'] ?? '-') . '</td>
                <td class="text-center"><span class="badge ' . $membershipBadge . '">' . $membershipText . '</span></td>
                <td class="text-center"><span class="status ' . $statusBadge . '">' . $statusText . '</span></td>
                <td class="text-center">' . $gems . '</td>
                <td class="text-center">' . $rating . '</td>
            </tr>';
        
        $no++;
    }

    $html .= '
        </tbody>
    </table>';

    // SUMMARY
    $html .= '
    <div class="summary">
        <div class="summary-title">RINGKASAN LAPORAN</div>
        <div class="summary-row">
            <span class="summary-label">Total Users:</span>
            <span><strong>' . $totalUsers . '</strong> users</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Filter Applied:</span>
            <span>' . htmlspecialchars($filterName) . '</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Search Query:</span>
            <span>' . (!empty($search) ? htmlspecialchars($search) : '(No search)') . '</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Export Date:</span>
            <span>' . $exportDate . '</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Exported By:</span>
            <span>' . htmlspecialchars($exportedBy) . '</span>
        </div>
    </div>';

    // FOOTER
    $html .= '
    <div class="footer">
        <p><strong>JagoNugas Administrator Dashboard</strong></p>
        <p>Generated: ' . $exportDate . ' | Confidential Document</p>
    </div>
    
</body>
</html>';

    // GENERATE PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isFontSubsettingEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $timestamp = date('Y-m-d_His');
    $filterName = str_replace(' ', '', ucfirst($filter));
    $filename = "JagoNugas_Users_{$filterName}_{$timestamp}.pdf";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $dompdf->output();
    exit;

} catch (Exception $e) {
    error_log("Export users PDF error: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal export data: ' . $e->getMessage();
    header("Location: admin-users.php");
    exit;
}
?>
