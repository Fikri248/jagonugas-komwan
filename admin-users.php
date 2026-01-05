<?php

/**
 * Admin Users Management Page
 * Manages all users (students & mentors) with filtering, search, pagination
 * Supports is_verified field for mentor approval workflow
 * ADDED: Admin can upgrade user membership
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url_path(string $path = ''): string
{
    $base = defined('BASE_PATH') ? (string) constant('BASE_PATH') : '';
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url_path('login.php'));
    exit;
}

// Get flash messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// ✅ HANDLE UPGRADE MEMBERSHIP
if (isset($_POST['upgrade_membership']) && isset($_POST['user_id']) && isset($_POST['package_id'])) {
    $userId = intval($_POST['user_id']);
    $packageId = intval($_POST['package_id']);

    try {
        // Get package info
        $stmt = $pdo->prepare("SELECT name, code FROM gem_packages WHERE id = ?");
        $stmt->execute([$packageId]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$package) {
            throw new Exception("Package not found");
        }

        // Check if user already has active membership
        $stmt = $pdo->prepare("
            SELECT id, membership_id 
            FROM memberships 
            WHERE user_id = ? AND status = 'active' AND end_date >= NOW()
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Expire old membership
            $stmt = $pdo->prepare("
                UPDATE memberships 
                SET status = 'expired', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$existing['id']]);
        }

        // Create new membership
        $stmt = $pdo->prepare("
            INSERT INTO memberships 
            (user_id, membership_id, status, start_date, end_date, created_at)
            VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
        ");
        $stmt->execute([$userId, $packageId]);

        // Send notification to user
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, icon, color, created_at)
            VALUES (?, 'membership', '🎉 Membership Upgraded!', 'Admin telah mengupgrade membership Anda ke " . $package['name'] . "!', 'gift', '#10b981', NOW())
        ");
        $stmt->execute([$userId]);

        $success = "Membership berhasil diupgrade ke " . $package['name'] . "!";
    } catch (Exception $e) {
        $error = 'Gagal upgrade membership: ' . $e->getMessage();
    }
}

// Handle delete user
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$userId]);
        $success = 'User berhasil dihapus!';
    } catch (PDOException $e) {
        $error = 'Gagal menghapus user: ' . $e->getMessage();
    }
}

// Get filter & search parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    // Main query to get users with membership data
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
            m.id as has_membership,
            m.membership_id,
            m.status as membership_status,
            m.start_date as membership_start,
            m.end_date as membership_end,
            gp.name as membership_name,
            gp.code as membership_code,
            gp.price as membership_price
        FROM users u
        LEFT JOIN memberships m ON u.id = m.user_id 
            AND m.status = 'active' 
            AND m.end_date >= NOW()
        LEFT JOIN gem_packages gp ON m.membership_id = gp.id
        WHERE u.role IN ('student', 'mentor')
    ";

    // Count query
    $countSql = "
        SELECT COUNT(DISTINCT u.id)
        FROM users u
        LEFT JOIN memberships m ON u.id = m.user_id 
            AND m.status = 'active' 
            AND m.end_date >= NOW()
        WHERE u.role IN ('student', 'mentor')
    ";

    $params = [];
    $countParams = [];

    // Apply filters
    if ($filter === 'subscribed') {
        $sql .= " AND m.id IS NOT NULL";
        $countSql .= " AND m.id IS NOT NULL";
    } elseif ($filter === 'free') {
        $sql .= " AND m.id IS NULL AND u.role = 'student'";
        $countSql .= " AND m.id IS NULL AND u.role = 'student'";
    } elseif ($filter === 'students') {
        $sql .= " AND u.role = 'student'";
        $countSql .= " AND u.role = 'student'";
    } elseif ($filter === 'mentors') {
        $sql .= " AND u.role = 'mentor'";
        $countSql .= " AND u.role = 'mentor'";
    } elseif ($filter === 'pending') {
        $sql .= " AND u.role = 'mentor' AND u.is_verified = 0";
        $countSql .= " AND u.role = 'mentor' AND u.is_verified = 0";
    }

    // Apply search
    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $countSql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $countParams[] = $searchParam;
        $countParams[] = $searchParam;
    }

    // Get total count
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countParams);
    $totalUsers = (int) $stmtCount->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);

    // Add pagination to main query
    $sql .= " ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset";

    // Execute main query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get stats for filter buttons
    $stmtAll = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student', 'mentor')");
    $countAll = (int) $stmtAll->fetchColumn();

    $stmtSubs = $pdo->query("
        SELECT COUNT(DISTINCT u.id) FROM users u
        JOIN memberships m ON u.id = m.user_id
        WHERE u.role IN ('student', 'mentor') AND m.status = 'active' AND m.end_date >= NOW()
    ");
    $countSubscribed = (int) $stmtSubs->fetchColumn();

    $stmtFree = $pdo->query("
        SELECT COUNT(*) FROM users u
        LEFT JOIN memberships m ON u.id = m.user_id AND m.status = 'active' AND m.end_date >= NOW()
        WHERE u.role = 'student' AND m.id IS NULL
    ");
    $countFree = (int) $stmtFree->fetchColumn();

    $stmtStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $countStudents = (int) $stmtStudents->fetchColumn();

    $stmtMentors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor'");
    $countMentors = (int) $stmtMentors->fetchColumn();

    $stmtPending = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor' AND is_verified = 0");
    $countPending = (int) $stmtPending->fetchColumn();

    // Check for pending membership sync
    $stmtPendingSync = $pdo->query("
        SELECT COUNT(DISTINCT gt.user_id) 
        FROM gem_transactions gt
        JOIN users u ON gt.user_id = u.id
        LEFT JOIN memberships m ON gt.user_id = m.user_id 
            AND DATE(m.start_date) = DATE(gt.paid_at)
        WHERE gt.transaction_status = 'settlement'
        AND gt.paid_at IS NOT NULL
        AND m.id IS NULL
        AND u.role = 'student'
    ");
    $pendingSync = (int) $stmtPendingSync->fetchColumn();

    // ✅ GET AVAILABLE PACKAGES FOR UPGRADE
    $stmtPackages = $pdo->query("SELECT id, name, code, price FROM gem_packages WHERE is_active = 1 ORDER BY price ASC");
    $packages = $stmtPackages->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin users query error: " . $e->getMessage());
    $users = [];
    $totalUsers = 0;
    $totalPages = 0;
    $countAll = 0;
    $countSubscribed = 0;
    $countFree = 0;
    $countStudents = 0;
    $countMentors = 0;
    $countPending = 0;
    $pendingSync = 0;
    $packages = [];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pengguna - Admin JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 2px solid #10b981;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 2px solid #dc2626;
        }

        .alert-sync {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-sync i {
            font-size: 1.5rem;
            color: #f59e0b;
        }

        .alert-sync-content {
            flex: 1;
        }

        .alert-sync-title {
            font-weight: 700;
            color: #92400e;
            margin-bottom: 4px;
        }

        .alert-sync-text {
            font-size: 0.9rem;
            color: #78350f;
        }

        .btn-sync {
            padding: 10px 20px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-sync:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-card .label {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-card .icon {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 8px;
        }

        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #475569;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            min-width: 250px;
        }

        .search-box form {
            display: flex;
            gap: 8px;
        }

        .search-box input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box button {
            padding: 12px 20px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        thead th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 0.85rem;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody td {
            padding: 14px 12px;
            border-top: 1px solid #f1f5f9;
            color: #1e293b;
            font-size: 0.9rem;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr[data-role="mentor"] .membership-col {
            background: #f8fafc;
            color: #cbd5e1;
            text-align: center;
            font-style: italic;
            font-size: 0.85rem;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .badge.free {
            background: #f1f5f9;
            color: #64748b;
        }

        .badge.basic {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge.pro {
            background: #ede9fe;
            color: #7c3aed;
        }

        .badge.plus {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-role {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-role.student {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-role.mentor {
            background: #ede9fe;
            color: #7c3aed;
        }

        .status {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status.active {
            background: #d1fae5;
            color: #059669;
        }

        .status.inactive {
            background: #f1f5f9;
            color: #64748b;
        }

        .status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status.rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .status.expired {
            background: #fee2e2;
            color: #dc2626;
        }

        .actions {
            display: flex;
            gap: 6px;
        }

        .btn-action {
            padding: 6px 10px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }

        .btn-view {
            background: #dbeafe;
            color: #2563eb;
        }

        .btn-view:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-upgrade {
            background: #d1fae5;
            color: #059669;
        }

        .btn-upgrade:hover {
            background: #10b981;
            color: white;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: 2px solid #dc2626;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-outline {
            border: 2px solid #e2e8f0;
            background: white;
            color: #475569;
        }

        .btn-outline:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            border: 2px solid #e2e8f0;
            background: white;
            transition: all 0.2s;
        }

        .pagination a:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .pagination span.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }

        .pagination span.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 8px;
            color: #64748b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content i.modal-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .modal-content i.modal-icon.danger {
            color: #ef4444;
        }

        .modal-content i.modal-icon.success {
            color: #10b981;
        }

        .modal-content h3 {
            margin-bottom: 1rem;
            color: #1a202c;
        }

        .modal-content p {
            color: #64748b;
            margin-bottom: 2rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-modal {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-modal.cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-modal.cancel:hover {
            background: #e2e8f0;
        }

        .btn-modal.confirm {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-modal.confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-modal.confirm.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .btn-modal.confirm.success:hover {
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .package-list {
            display: grid;
            gap: 12px;
            margin-bottom: 2rem;
        }

        .package-item {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .package-item:hover {
            border-color: #667eea;
            background: #f8fafc;
        }

        .package-item input[type="radio"] {
            cursor: pointer;
        }

        .package-info {
            flex: 1;
            margin-left: 12px;
        }

        .package-name {
            font-weight: 600;
            color: #1e293b;
        }

        .package-price {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 4px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1400px) {
            table {
                font-size: 0.85rem;
            }

            thead th,
            tbody td {
                padding: 12px 8px;
            }
        }

        @media (max-width: 1024px) {
            .filters {
                flex-wrap: wrap;
            }

            .search-box {
                flex-basis: 100%;
                max-width: 100%;
                order: 10;
            }

            .header-actions {
                flex-basis: 100%;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .stats-summary {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.8rem;
            }

            .badge,
            .badge-role,
            .status {
                font-size: 0.7rem;
                padding: 4px 8px;
            }

            .btn {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/admin-navbar.php'; ?>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="bi bi-people-fill"></i> Daftar Pengguna</h1>
            <div class="header-actions">
                <!-- AFTER (NEW CODE) -->
                <?php
                $exportParams = http_build_query([
                    'filter' => $filter,
                    'search' => $search
                ]);
                ?>

                <a href="<?= url_path('export-users.php?format=excel&' . $exportParams) ?>"
                    class="btn btn-success" title="Export ke Excel (.xlsx)">
                    <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
                </a>

                <a href="<?= url_path('export-users.php?format=pdf&' . $exportParams) ?>"
                    class="btn btn-danger" title="Export ke PDF" target="_blank">
                    <i class="bi bi-file-earmark-pdf-fill"></i> Export PDF
                </a>


                <a href="<?= url_path('admin-dashboard.php') ?>" class="btn btn-outline">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Membership Sync Alert -->
        <?php if ($pendingSync > 0): ?>
            <div class="alert-sync">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div class="alert-sync-content">
                    <div class="alert-sync-title">Ada <?= $pendingSync ?> user belum dapat membership!</div>
                    <div class="alert-sync-text">User sudah bayar gems tapi belum masuk membership. Klik tombol untuk sinkronisasi otomatis.</div>
                </div>
                <a href="<?= url_path('sync-user-membership.php') ?>" class="btn-sync">
                    <i class="bi bi-arrow-repeat"></i> Sync Membership
                </a>
            </div>
        <?php endif; ?>

        <!-- Stats Summary -->
        <div class="stats-summary">
            <div class="stat-card">
                <div class="icon"><i class="bi bi-people-fill"></i></div>
                <div class="label">Total Users</div>
                <div class="value"><?= number_format($countAll) ?></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="bi bi-mortarboard-fill"></i></div>
                <div class="label">Students</div>
                <div class="value"><?= number_format($countStudents) ?></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="bi bi-award-fill"></i></div>
                <div class="label">Mentors</div>
                <div class="value"><?= number_format($countMentors) ?></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="bi bi-star-fill"></i></div>
                <div class="label">Subscribed</div>
                <div class="value"><?= number_format($countSubscribed) ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="bi bi-grid"></i> Semua (<?= $countAll ?>)
            </a>
            <a href="?filter=students" class="filter-btn <?= $filter === 'students' ? 'active' : '' ?>">
                <i class="bi bi-mortarboard-fill"></i> Students (<?= $countStudents ?>)
            </a>
            <a href="?filter=mentors" class="filter-btn <?= $filter === 'mentors' ? 'active' : '' ?>">
                <i class="bi bi-award-fill"></i> Mentors (<?= $countMentors ?>)
            </a>
            <?php if ($countPending > 0): ?>
                <a href="?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                    <i class="bi bi-hourglass-split"></i> Pending (<?= $countPending ?>)
                </a>
            <?php endif; ?>
            <a href="?filter=subscribed" class="filter-btn <?= $filter === 'subscribed' ? 'active' : '' ?>">
                <i class="bi bi-star-fill"></i> Subscribed (<?= $countSubscribed ?>)
            </a>
            <a href="?filter=free" class="filter-btn <?= $filter === 'free' ? 'active' : '' ?>">
                <i class="bi bi-person"></i> Free (<?= $countFree ?>)
            </a>

            <div class="search-box">
                <form method="GET">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <input type="text" name="search" placeholder="Cari nama atau email..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Program Studi</th>
                        <th>Membership</th>
                        <th>Status</th>
                        <th>Bergabung</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" style="padding: 0;">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <h3>Tidak ada pengguna ditemukan</h3>
                                    <p><?= !empty($search) ? 'Coba cari dengan kata kunci lain' : 'Belum ada data' ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $no = $offset + 1;
                        foreach ($users as $user):
                            $isMentor = ($user['role'] === 'mentor');
                        ?>
                            <tr data-role="<?= $user['role'] ?>">
                                <td><strong><?= $no++ ?></strong></td>
                                <td><strong><?= htmlspecialchars($user['name']) ?></strong></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge-role <?= $user['role'] ?>">
                                        <i class="bi bi-<?= $user['role'] === 'student' ? 'mortarboard' : 'award' ?>-fill"></i>
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['program_studi'] ?? '-') ?></td>

                                <!-- Membership Column -->
                                <td class="membership-col">
                                    <?php if ($isMentor): ?>
                                        -
                                    <?php else: ?>
                                        <?php if ($user['membership_name']): ?>
                                            <span class="badge <?= strtolower($user['membership_code'] ?? 'pro') ?>">
                                                <i class="bi bi-star-fill"></i>
                                                <?= htmlspecialchars($user['membership_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge free">
                                                <i class="bi bi-person"></i> Free
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Column -->
                                <td>
                                    <?php
                                    if ($isMentor) {
                                        $isVerified = $user['is_verified'] ?? 0;

                                        if ($isVerified == 1) {
                                            echo '<span class="status active"><i class="bi bi-check-circle-fill"></i> Active</span>';
                                        } elseif ($isVerified == 2) {
                                            echo '<span class="status rejected"><i class="bi bi-x-circle-fill"></i> Rejected</span>';
                                        } else {
                                            echo '<span class="status pending"><i class="bi bi-hourglass-split"></i> Pending</span>';
                                        }
                                    } else {
                                        if ($user['has_membership']) {
                                            if ($user['membership_end'] && strtotime($user['membership_end']) < time()) {
                                                echo '<span class="status expired"><i class="bi bi-clock-history"></i> Expired</span>';
                                            } else {
                                                echo '<span class="status active"><i class="bi bi-check-circle-fill"></i> Active</span>';
                                            }
                                        } else {
                                            echo '<span class="status inactive"><i class="bi bi-circle"></i> Free</span>';
                                        }
                                    }
                                    ?>
                                </td>

                                <td><small><?= date('d M Y', strtotime($user['created_at'])) ?></small></td>

                                <td>
                                    <div class="actions">
                                        <a href="<?= url_path('admin-user-detail.php?id=' . $user['id']) ?>" class="btn-action btn-view" title="View Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (!$isMentor): ?>
                                            <button type="button" class="btn-action btn-upgrade" title="Upgrade Membership"
                                                onclick="showUpgradeModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>', '<?= $user['membership_name'] ?? 'Free' ?>')">
                                                <i class="bi bi-arrow-up-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn-action btn-delete" title="Delete User"
                                            onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">
                        <i class="bi bi-chevron-left"></i> Prev
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="bi bi-chevron-left"></i> Prev</span>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Next <i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <i class="bi bi-exclamation-triangle-fill modal-icon danger"></i>
            <h3>Hapus User?</h3>
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus user ini? Tindakan ini tidak dapat dibatalkan.</p>

            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" name="user_id" id="deleteUserId">

                <div class="modal-actions">
                    <button type="button" class="btn-modal cancel" onclick="closeModal('deleteModal')">Batal</button>
                    <button type="submit" class="btn-modal confirm">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upgrade Membership Modal -->
    <div class="modal" id="upgradeModal">
        <div class="modal-content">
            <i class="bi bi-arrow-up-circle-fill modal-icon success"></i>
            <h3>Upgrade Membership</h3>
            <p id="upgradeMessage">Pilih paket membership untuk <strong id="upgradeUserName"></strong></p>

            <form method="POST" id="upgradeForm">
                <input type="hidden" name="upgrade_membership" value="1">
                <input type="hidden" name="user_id" id="upgradeUserId">

                <div class="package-list">
                    <?php foreach ($packages as $pkg): ?>
                        <label class="package-item">
                            <input type="radio" name="package_id" value="<?= $pkg['id'] ?>" required>
                            <div class="package-info">
                                <div class="package-name">
                                    <i class="bi bi-star-fill"></i> <?= htmlspecialchars($pkg['name']) ?>
                                </div>
                                <div class="package-price">Rp <?= number_format($pkg['price'], 0, ',', '.') ?>/bulan</div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-modal cancel" onclick="closeModal('upgradeModal')">Batal</button>
                    <button type="submit" class="btn-modal confirm success">Upgrade Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showUpgradeModal(userId, userName, currentMembership) {
            document.getElementById('upgradeUserId').value = userId;
            document.getElementById('upgradeUserName').textContent = userName;
            document.getElementById('upgradeMessage').innerHTML =
                `Pilih paket membership untuk <strong>${userName}</strong><br><small style="color: #64748b;">Membership saat ini: ${currentMembership}</small>`;
            document.getElementById('upgradeModal').classList.add('active');
        }

        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteMessage').innerHTML =
                `Apakah Anda yakin ingin menghapus <strong>${userName}</strong>? Tindakan ini tidak dapat dibatalkan.`;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        // Auto dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'all 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>

</html>