<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url_path(string $path = ''): string
{
    $base = defined('BASE_PATH') ? (string) constant('BASE_PATH') : '';
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . $BASE . "/login.php");
    exit;
}

$name = $_SESSION['name'] ?? 'Admin';

// Get filter & search
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

try {
    // ✅ FIX: LEFT JOIN untuk menampilkan transaksi meskipun user dihapus
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

    // Filter by status
    if ($filter !== 'all') {
        $sql .= " AND gt.transaction_status = ?";
        $params[] = $filter;
    }

    // Search by order_id, user name, or email
    if (!empty($search)) {
        $sql .= " AND (gt.order_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Filter by date range
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

    // Get counts for filters
    $stmtAll = $pdo->query("SELECT COUNT(*) FROM gem_transactions");
    $countAll = (int) $stmtAll->fetchColumn();

    $stmtSettlement = $pdo->query("SELECT COUNT(*) FROM gem_transactions WHERE transaction_status = 'settlement'");
    $countSettlement = (int) $stmtSettlement->fetchColumn();

    $stmtPending = $pdo->query("SELECT COUNT(*) FROM gem_transactions WHERE transaction_status = 'pending'");
    $countPending = (int) $stmtPending->fetchColumn();

    $stmtExpire = $pdo->query("SELECT COUNT(*) FROM gem_transactions WHERE transaction_status = 'expire'");
    $countExpire = (int) $stmtExpire->fetchColumn();

    $stmtCancel = $pdo->query("SELECT COUNT(*) FROM gem_transactions WHERE transaction_status = 'cancel'");
    $countCancel = (int) $stmtCancel->fetchColumn();

    // Total revenue (settlement only)
    $stmtRevenue = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) 
        FROM gem_transactions 
        WHERE transaction_status = 'settlement'
    ");
    $totalRevenue = (int) $stmtRevenue->fetchColumn();

} catch (PDOException $e) {
    error_log("Admin transactions error: " . $e->getMessage());
    $transactions = [];
    $countAll = 0;
    $countSettlement = 0;
    $countPending = 0;
    $countExpire = 0;
    $countCancel = 0;
    $totalRevenue = 0;
}

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatDate($date) {
    if (!$date) return '-';
    return date('d M Y, H:i', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'settlement' => ['class' => 'success', 'icon' => 'check-circle-fill', 'label' => 'Success'],
        'pending' => ['class' => 'pending', 'icon' => 'clock-fill', 'label' => 'Pending'],
        'expire' => ['class' => 'danger', 'icon' => 'x-circle-fill', 'label' => 'Expired'],
        'cancel' => ['class' => 'danger', 'icon' => 'x-circle-fill', 'label' => 'Cancelled']
    ];
    return $badges[$status] ?? ['class' => 'secondary', 'icon' => 'question-circle-fill', 'label' => ucfirst($status)];
}

function getPackageInfo($package) {
    $packages = [
        'basic' => ['name' => 'Basic', 'gems' => 50, 'price' => 10000],
        'pro' => ['name' => 'Pro', 'gems' => 200, 'price' => 25000],
        'plus' => ['name' => 'Plus', 'gems' => 500, 'price' => 50000]
    ];
    return $packages[$package] ?? ['name' => ucfirst($package), 'gems' => 0, 'price' => 0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi - Admin JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 32px 20px; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .header h1 { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        
        .revenue-banner { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 16px; padding: 24px 32px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8px 24px rgba(102,126,234,0.3); }
        .revenue-info h2 { font-size: 2rem; font-weight: 700; margin-bottom: 4px; }
        .revenue-info p { opacity: 0.9; font-size: 0.95rem; }
        .revenue-icon { font-size: 3rem; opacity: 0.2; }
        
        /* ✅ EXPORT SECTION */
        .export-section {
            background: white;
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .export-section h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .export-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .export-buttons form {
            display: inline;
        }

        .btn-export {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .btn-export.excel {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-export.excel:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-export.pdf {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-export.pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
        
        .filters { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; align-items: center; }
        .filter-btn { padding: 10px 20px; border-radius: 10px; border: 2px solid #e2e8f0; background: white; color: #475569; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .filter-btn:hover { border-color: #667eea; color: #667eea; }
        .filter-btn.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-color: #667eea; }
        
        .search-filters { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .search-box { flex: 1; min-width: 250px; }
        .search-box input { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; }
        .search-box input:focus { outline: none; border-color: #667eea; }
        
        .date-filter { display: flex; gap: 8px; align-items: center; }
        .date-filter input { padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; }
        .date-filter input:focus { outline: none; border-color: #667eea; }
        .btn-filter { padding: 10px 20px; border-radius: 10px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-weight: 600; border: none; cursor: pointer; transition: all 0.3s; }
        .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
        
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); overflow: hidden; }
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8fafc; }
        thead th { padding: 16px; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem; white-space: nowrap; }
        tbody td { padding: 16px; border-top: 1px solid #f1f5f9; color: #1e293b; }
        tbody tr:hover { background: #f8fafc; }
        
        .badge-status { padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .badge-status.success { background: #d1fae5; color: #059669; }
        .badge-status.pending { background: #fef3c7; color: #92400e; }
        .badge-status.danger { background: #fee2e2; color: #dc2626; }
        .badge-status.secondary { background: #f1f5f9; color: #64748b; }
        
        .badge-package { padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .badge-package.basic { background: #dbeafe; color: #2563eb; }
        .badge-package.pro { background: #ede9fe; color: #7c3aed; }
        .badge-package.plus { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
        
        .btn-back { padding: 10px 20px; border-radius: 10px; border: 2px solid #e2e8f0; background: white; color: #475569; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-back:hover { border-color: #667eea; color: #667eea; }
        
        /* ✅ USER DELETED BADGE */
        .user-deleted { color: #dc2626; font-weight: 600; font-style: italic; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 3rem; margin-bottom: 16px; opacity: 0.5; }
        .empty-state h3 { font-size: 1.2rem; margin-bottom: 8px; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            .header h1 { font-size: 1.5rem; }
            .revenue-banner { flex-direction: column; text-align: center; gap: 16px; }
            .export-section { padding: 16px 20px; }
            .export-section h3 { font-size: 1rem; }
            .btn-export { padding: 10px 16px; font-size: 0.85rem; flex: 1; }
            .export-buttons { flex-direction: column; }
            table { font-size: 0.85rem; }
            thead th, tbody td { padding: 12px 8px; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/admin-navbar.php'; ?>

    <div class="container">
        <div class="header">
            <h1><i class="bi bi-credit-card-fill"></i> Transaksi Gems</h1>
            <a href="<?php echo htmlspecialchars(url_path('admin-dashboard.php')); ?>" class="btn-back">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>

        <!-- Revenue Banner -->
        <div class="revenue-banner">
            <div class="revenue-info">
                <h2><?php echo formatRupiah($totalRevenue); ?></h2>
                <p>Total Pendapatan (<?php echo number_format($countSettlement); ?> transaksi berhasil)</p>
            </div>
            <div class="revenue-icon">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>

        <!-- ✅ EXPORT SECTION -->
        <div class="export-section">
            <h3><i class="bi bi-download"></i> Export Data Transaksi</h3>
            <div class="export-buttons">
                <form method="GET" action="<?php echo htmlspecialchars(url_path('export-transactions-helper.php')); ?>">
                    <input type="hidden" name="format" value="excel">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    <button type="submit" class="btn-export excel">
                        <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
                    </button>
                </form>

                <form method="GET" action="<?php echo htmlspecialchars(url_path('export-transactions-helper.php')); ?>">
                    <input type="hidden" name="format" value="pdf">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    <button type="submit" class="btn-export pdf">
                        <i class="bi bi-file-earmark-pdf-fill"></i> Export PDF
                    </button>
                </form>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filters">
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="bi bi-grid"></i> Semua (<?php echo $countAll; ?>)
            </a>
            <a href="?filter=settlement" class="filter-btn <?php echo $filter === 'settlement' ? 'active' : ''; ?>">
                <i class="bi bi-check-circle-fill"></i> Success (<?php echo $countSettlement; ?>)
            </a>
            <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                <i class="bi bi-clock-fill"></i> Pending (<?php echo $countPending; ?>)
            </a>
            <a href="?filter=expire" class="filter-btn <?php echo $filter === 'expire' ? 'active' : ''; ?>">
                <i class="bi bi-x-circle-fill"></i> Expired (<?php echo $countExpire; ?>)
            </a>
            <a href="?filter=cancel" class="filter-btn <?php echo $filter === 'cancel' ? 'active' : ''; ?>">
                <i class="bi bi-x-circle-fill"></i> Cancelled (<?php echo $countCancel; ?>)
            </a>
        </div>

        <!-- Search & Date Filter -->
        <form method="GET" class="search-filters">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            
            <div class="search-box">
                <input type="text" name="search" placeholder="Cari Order ID, nama, atau email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="date-filter">
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" placeholder="Dari">
                <span>-</span>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" placeholder="Sampai">
            </div>
            
            <button type="submit" class="btn-filter">
                <i class="bi bi-search"></i> Filter
            </button>
        </form>

        <!-- Transactions Table -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>User</th>
                        <th>Paket</th>
                        <th>Gems</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" style="padding: 0;">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h3>Tidak ada transaksi ditemukan</h3>
                                <p>
                                    <?php if (!empty($search)): ?>
                                        Coba cari dengan kata kunci lain
                                    <?php elseif ($filter === 'pending'): ?>
                                        Belum ada transaksi pending
                                    <?php elseif ($filter === 'settlement'): ?>
                                        Belum ada transaksi berhasil
                                    <?php elseif ($filter === 'expire'): ?>
                                        Belum ada transaksi expired
                                    <?php elseif ($filter === 'cancel'): ?>
                                        Belum ada transaksi cancelled
                                    <?php else: ?>
                                        Belum ada transaksi terdaftar
                                    <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $trx): ?>
                        <?php 
                        $statusBadge = getStatusBadge($trx['transaction_status']);
                        $packageInfo = getPackageInfo($trx['package']);
                        $isDeleted = ($trx['user_name'] === 'User Deleted');
                        ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars(substr($trx['order_id'], -8)); ?></strong>
                            </td>
                            <td>
                                <?php if ($isDeleted): ?>
                                    <span class="user-deleted">
                                        <i class="bi bi-person-x-fill"></i> User Deleted
                                    </span><br>
                                    <small style="color: #94a3b8;">ID: <?php echo htmlspecialchars($trx['user_id']); ?></small>
                                <?php else: ?>
                                    <strong><?php echo htmlspecialchars($trx['user_name']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($trx['user_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-package <?php echo htmlspecialchars($trx['package']); ?>">
                                    <?php echo $packageInfo['name']; ?>
                                </span>
                            </td>
                            <td>
                                <i class="bi bi-gem" style="color: #f59e0b;"></i>
                                <strong><?php echo number_format($trx['gems']); ?></strong>
                            </td>
                            <td><?php echo formatRupiah($trx['amount']); ?></td>
                            <td>
                                <span class="badge-status <?php echo $statusBadge['class']; ?>">
                                    <i class="bi bi-<?php echo $statusBadge['icon']; ?>"></i>
                                    <?php echo $statusBadge['label']; ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo formatDate($trx['created_at']); ?></small>
                                <?php if ($trx['paid_at'] && $trx['transaction_status'] === 'settlement'): ?>
                                <br><small style="color: #10b981;">
                                    <i class="bi bi-check-circle"></i> <?php echo formatDate($trx['paid_at']); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
