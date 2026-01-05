<?php
// student-gems-purchase.php - DYNAMIC DATABASE VERSION
session_start();
require_once 'config.php';
require_once 'db.php';

// ✅ TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, email, gems FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM gem_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// ✅ FETCH PACKAGES FROM DATABASE (DYNAMIC)
try {
    $stmt = $pdo->query("SELECT * FROM gem_packages ORDER BY FIELD(code, 'basic', 'pro', 'plus')");
    $dbPackages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array with additional display properties
    $packages = [];
    foreach ($dbPackages as $pkg) {
        $code = strtolower($pkg['code']);
        $packages[$code] = [
            'name' => $pkg['name'],
            'price' => (int)$pkg['price'],
            'gems' => (int)$pkg['gems'],
            'bonus' => (int)$pkg['bonus'],
            'total_gems' => (int)$pkg['total_gems'],
            // Display properties
            'badge_color' => $code === 'basic' ? 'secondary' : ($code === 'pro' ? 'primary' : 'warning'),
            'button_color' => $code === 'basic' ? 'dark' : ($code === 'pro' ? 'primary' : 'warning'),
            'popular' => $code === 'pro',
            'best_value' => $code === 'plus'
        ];
    }
    
    // Fallback jika database kosong
    if (empty($packages)) {
        throw new Exception("No packages found");
    }
    
} catch (Exception $e) {
    // Fallback to hardcoded values if database fails
    error_log("Failed to fetch packages: " . $e->getMessage());
    $packages = [
        'basic' => ['name' => 'Basic', 'price' => 10000, 'gems' => 4500, 'bonus' => 0, 'total_gems' => 4500, 'badge_color' => 'secondary', 'button_color' => 'dark'],
        'pro' => ['name' => 'Pro', 'price' => 25000, 'gems' => 12500, 'bonus' => 500, 'total_gems' => 13000, 'badge_color' => 'primary', 'button_color' => 'primary', 'popular' => true],
        'plus' => ['name' => 'Plus', 'price' => 50000, 'gems' => 27000, 'bonus' => 2000, 'total_gems' => 29000, 'badge_color' => 'warning', 'button_color' => 'warning', 'best_value' => true]
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beli Gem - JagoNugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script type="text/javascript" src="<?= MIDTRANS_SNAP_URL ?>" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ===== BASE STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a202c;
            background: #f8f9fa;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 40px;
        }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-outline {
            border: 2px solid #e2e8f0;
            color: #4a5568;
            background: transparent;
        }

        .btn-outline:hover {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .btn-full {
            width: 100%;
            justify-content: center;
        }

        /* ===== ALERTS ===== */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        /* ===== BALANCE CARD ===== */
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }

        /* ===== PROMO BADGE ===== */
        .promo-badge {
            position: absolute;
            top: -18px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 20;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 800;
            font-size: 0.7rem;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            animation: badge-float 3s ease-in-out infinite;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            white-space: nowrap;
        }

        .promo-badge i {
            font-size: 0.9rem;
            line-height: 1;
            flex-shrink: 0;
        }

        .promo-badge.popular {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .promo-badge.best-value {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        @keyframes badge-float {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-5px); }
        }

        /* ===== PACKAGE CARD ===== */
        .package-card {
            border: 3px solid #e5e7eb;
            border-radius: 20px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: white;
            height: 100%;
            position: relative;
            overflow: visible;
            margin-top: 35px;
        }

        .package-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }

        .package-card.popular {
            border-color: #ef4444;
            box-shadow: 0 10px 40px rgba(239, 68, 68, 0.2);
        }

        .package-card.popular:hover {
            box-shadow: 0 25px 60px rgba(239, 68, 68, 0.3);
        }

        .package-card.best-value {
            border-color: #10b981;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.2);
        }

        .package-card.best-value:hover {
            box-shadow: 0 25px 60px rgba(16, 185, 129, 0.3);
        }

        .package-name-badge {
            display: inline-block;
            padding: 8px 24px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== GEM ICON ===== */
        .gem-icon {
            font-size: 4.5rem;
            background: linear-gradient(135deg, #fbbf24, #f59e0b, #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 4px 12px rgba(251, 191, 36, 0.4));
        }

        /* ===== PRICE TAG ===== */
        .price-tag {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        /* ===== GEMS DISPLAY ===== */
        .gems-display {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 2px solid #fbbf24;
        }

        .gems-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #92400e;
        }

        .bonus-gems {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            display: inline-block;
            margin-top: 0.75rem;
            border: 2px solid #10b981;
        }

        .total-gems-section {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 2px solid #667eea;
        }

        .total-gems-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: #667eea;
        }

        /* ===== BUY BUTTON - FIXED CENTER ===== */
        .buy-button {
            border-radius: 15px;
            padding: 1.1rem 2rem;
            font-size: 1.05rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
        }

        .buy-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .buy-button:active {
            transform: translateY(-1px);
        }

        /* ===== FEATURE ITEM ===== */
        .feature-item {
            padding: 0.6rem 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.85rem;
        }

        .feature-item:last-child {
            border-bottom: none;
        }

        /* ===== WHY CARD ===== */
        .why-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
            height: 100%;
        }

        .why-card:hover {
            border-color: #667eea;
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.2);
        }

        .why-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        /* ===== TRANSACTION TABLE ===== */
        .table-transactions {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-transactions thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            vertical-align: middle;
        }

        .table-transactions tbody tr {
            transition: background 0.15s ease;
        }

        .table-transactions tbody tr:hover {
            background: #f8f9fa;
        }

        .table-transactions tbody tr:last-child td {
            border-bottom: none;
        }

        .table-transactions tbody td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .table-transactions .badge {
            padding: 0.4rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
            vertical-align: middle;
            display: inline-block;
        }

        .table-transactions .btn-sm {
            padding: 0.4rem 0.9rem;
            font-size: 0.8rem;
            border-radius: 8px;
            font-weight: 600;
            vertical-align: middle;
        }

        .table-transactions .btn-sm:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3);
        }

        /* ===== MODAL ===== */
        .modal-content {
            border-radius: 20px;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes scale-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .promo-badge {
                font-size: 0.6rem;
                padding: 7px 18px;
                top: -15px;
                gap: 4px;
                letter-spacing: 0.5px;
            }

            .promo-badge i {
                font-size: 0.75rem;
            }

            .package-card {
                margin-top: 30px;
            }

            .price-tag {
                font-size: 2.5rem;
            }

            .gem-icon {
                font-size: 3.5rem;
            }

            .total-gems-number {
                font-size: 1.8rem;
            }

            .container {
                padding: 0 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'student-navbar.php'; ?>

    <div class="container py-5">
        <!-- Balance Card -->
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto">
                <div class="card balance-card text-white shadow-lg">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <p class="mb-2 opacity-75"><i class="bi bi-person-circle me-2 fs-5"></i><?= htmlspecialchars($user['name']) ?></p>
                                <h6 class="mb-2 opacity-75 fw-normal">Saldo Gem Saat Ini</h6>
                                <h1 class="mb-0 fw-bold" style="font-size: 3.5rem;"><i class="bi bi-gem me-2"></i><?= number_format($user['gems']) ?></h1>
                                <p class="mt-3 mb-0 opacity-75"><i class="bi bi-info-circle me-2"></i><small>1 gem = 1 menit sesi mentoring</small></p>
                            </div>
                            <div class="col-md-4 text-center d-none d-md-block">
                                <i class="bi bi-wallet2" style="font-size: 7rem; opacity: 0.15;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header -->
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-2">Pilih Paket Membership</h2>
            <p class="text-muted">Investasi terbaik untuk masa depanmu</p>
        </div>

        <!-- Packages -->
        <div class="row g-4 mb-5">
            <?php foreach ($packages as $key => $package): ?>
            <div class="col-lg-4 col-md-6">
                <div class="package-card <?= !empty($package['popular']) ? 'popular' : '' ?> <?= !empty($package['best_value']) ? 'best-value' : '' ?>">
                    <?php if (!empty($package['popular'])): ?>
                        <div class="promo-badge popular"><i class="bi bi-fire"></i><span>Terpopuler</span></div>
                    <?php endif; ?>
                    <?php if (!empty($package['best_value'])): ?>
                        <div class="promo-badge best-value"><i class="bi bi-star-fill"></i><span>Best Value</span></div>
                    <?php endif; ?>

                    <div class="card-body p-4 text-center">
                        <div class="mt-3 mb-2"><h2 class="price-tag mb-0">Rp <?= number_format($package['price'], 0, ',', '.') ?></h2></div>
                        <div class="mb-3"><span class="package-name-badge bg-<?= $package['badge_color'] ?> text-white"><?= htmlspecialchars($package['name']) ?></span></div>
                        <p class="text-muted small mb-4">Pembayaran sekali</p>
                        <div class="my-4"><i class="bi bi-gem gem-icon"></i></div>
                        <div class="gems-display">
                            <h3 class="gems-number mb-0"><?= number_format($package['gems']) ?> Gems</h3>
                            <?php if ($package['bonus'] > 0): ?>
                                <div class="bonus-gems"><i class="bi bi-gift-fill text-success me-1"></i><strong class="text-success" style="font-size: 0.9rem;">Bonus +<?= number_format($package['bonus']) ?></strong></div>
                            <?php endif; ?>
                        </div>
                        <div class="total-gems-section">
                            <p class="mb-1 text-muted fw-semibold" style="font-size: 0.75rem; letter-spacing: 1px;">TOTAL GEMS</p>
                            <h2 class="total-gems-number mb-0"><?= number_format($package['total_gems']) ?></h2>
                        </div>
                        <div class="text-start mb-4">
                            <div class="feature-item"><i class="bi bi-check-circle-fill text-success me-2"></i><span class="text-muted">Pembayaran aman via Midtrans</span></div>
                            <div class="feature-item"><i class="bi bi-check-circle-fill text-success me-2"></i><span class="text-muted">Gems masuk otomatis instant</span></div>
                            <div class="feature-item"><i class="bi bi-check-circle-fill text-success me-2"></i><span class="text-muted">Berlaku selamanya tanpa expired</span></div>
                        </div>
                        <button type="button" class="btn btn-<?= $package['button_color'] ?> w-100 buy-button" onclick="buyPackage('<?= $key ?>', <?= $package['price'] ?>, <?= $package['total_gems'] ?>)">
                            <i class="bi bi-credit-card"></i><span>Beli Sekarang</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Why Section -->
        <div class="row mb-5">
            <div class="col-12">
                <h3 class="text-center mb-4 fw-bold">Kenapa Beli Gem di JagoNugas?</h3>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="why-card">
                            <div class="why-icon"><i class="bi bi-lightning-charge-fill text-warning"></i></div>
                            <h5 class="fw-bold mb-3">Akses Instant</h5>
                            <p class="text-muted mb-0">Gems langsung masuk setelah pembayaran berhasil</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="why-card">
                            <div class="why-icon"><i class="bi bi-shield-fill-check text-success"></i></div>
                            <h5 class="fw-bold mb-3">Aman & Terpercaya</h5>
                            <p class="text-muted mb-0">Diproses oleh Midtrans payment gateway #1</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="why-card">
                            <div class="why-icon"><i class="bi bi-infinity text-primary"></i></div>
                            <h5 class="fw-bold mb-3">Tidak Ada Expired</h5>
                            <p class="text-muted mb-0">Gems berlaku selamanya tanpa batas waktu</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction History -->
        <?php if (!empty($transactions)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm" style="border-radius: 20px; overflow: hidden;">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Riwayat Transaksi</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-transactions mb-0">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Paket</th>
                                        <th>Gems</th>
                                        <th>Harga</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $trx): ?>
                                    <tr data-order-id="<?= htmlspecialchars($trx['order_id']) ?>">
                                        <td><code class="small"><?= substr($trx['order_id'], 0, 20) ?>...</code></td>
                                        <td><span class="badge bg-<?= $packages[$trx['package']]['badge_color'] ?? 'secondary' ?>"><?= $packages[$trx['package']]['name'] ?? $trx['package'] ?></span></td>
                                        <td><i class="bi bi-gem text-warning"></i> <strong><?= number_format($trx['gems']) ?></strong></td>
                                        <td><strong>Rp <?= number_format($trx['amount'], 0, ',', '.') ?></strong></td>
                                        <td>
                                            <?php
                                            $statusClass = ['pending' => 'warning', 'settlement' => 'success', 'capture' => 'success', 'deny' => 'danger', 'cancel' => 'danger', 'expire' => 'secondary', 'failure' => 'danger'];
                                            $statusText = ['pending' => 'Menunggu', 'settlement' => 'Berhasil', 'capture' => 'Berhasil', 'deny' => 'Ditolak', 'cancel' => 'Dibatalkan', 'expire' => 'Kadaluarsa', 'failure' => 'Gagal'];
                                            $status = $trx['transaction_status'];
                                            ?>
                                            <span class="badge bg-<?= $statusClass[$status] ?? 'secondary' ?>"><?= $statusText[$status] ?? $status ?></span>
                                        </td>
                                        <td><small class="text-muted"><i class="bi bi-calendar me-1"></i><?= date('d M Y, H:i', strtotime($trx['created_at'])) ?></small></td>
                                        <td class="text-center">
                                            <?php if ($status === 'pending' && !empty($trx['snap_token'])): ?>
                                                <button type="button" class="btn btn-sm btn-primary" onclick="continuePaymentFromHistory('<?= htmlspecialchars($trx['snap_token']) ?>', '<?= htmlspecialchars($trx['order_id']) ?>', <?= $trx['gems'] ?>)">
                                                    <i class="bi bi-credit-card me-1"></i>Bayar
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-5">
                    <div class="spinner-border text-primary mb-3" style="width: 4rem; height: 4rem;" role="status"><span class="visually-hidden">Loading...</span></div>
                    <h5 class="fw-bold mb-2">Memproses Pembayaran</h5>
                    <p class="text-muted mb-0">Mohon tunggu sebentar...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Cancel Modal -->
    <div class="modal fade" id="confirmCancelModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-3">Batalkan Pembayaran?</h4>
                    <p class="text-muted mb-4">Apakah Anda yakin ingin membatalkan pembayaran ini?<br>Transaksi akan dihapus dari riwayat.</p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-danger btn-lg fw-bold" style="border-radius: 12px; padding: 0.9rem;" onclick="confirmCancelPayment()"><i class="bi bi-x-circle me-2"></i>Ya, Batalkan</button>
                        <button type="button" class="btn btn-outline-secondary btn-lg" style="border-radius: 12px; padding: 0.9rem;" onclick="dismissCancelModal()"><i class="bi bi-arrow-left me-2"></i>Kembali ke Pembayaran</button>
                    </div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Anda bisa melanjutkan pembayaran kapan saja dari riwayat transaksi</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let loadingModal = null, confirmCancelModal = null, currentOrderId = null, currentSnapToken = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            confirmCancelModal = new bootstrap.Modal(document.getElementById('confirmCancelModal'));
        });

        function buyPackage(packageKey, price, gems) {
            loadingModal.show();
            fetch('payment-process.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ package: packageKey, price: price, gems: gems }) })
            .then(r => r.json()).then(data => {
                loadingModal.hide();
                if (!data.success) { Swal.fire({ icon: 'error', title: 'Oops...', text: data.message || 'Gagal membuat transaksi' }); return; }
                currentOrderId = data.order_id; currentSnapToken = data.snap_token;
                snap.pay(currentSnapToken, {
                    onSuccess: r => instantDirectUpdate(currentOrderId, gems),
                    onPending: r => Swal.fire({ icon: 'info', title: 'Pembayaran Tertunda', text: 'Silakan selesaikan pembayaran Anda.' }).then(() => location.reload()),
                    onError: r => Swal.fire({ icon: 'error', title: 'Pembayaran Gagal', text: 'Terjadi kesalahan. Silakan coba lagi.' }),
                    onClose: () => confirmCancelModal.show()
                });
            }).catch(e => { loadingModal.hide(); Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan: ' + e.message }); });
        }

        function instantDirectUpdate(orderId, gemsAmount) {
            document.querySelector('#loadingModal .modal-content').innerHTML = `<div class="modal-body text-center p-5"><div class="mb-4"><div style="width: 100px; height: 100px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; animation: scale-pulse 1s ease-in-out infinite;"><i class="bi bi-gem text-white" style="font-size: 3rem;"></i></div></div><h4 class="fw-bold mb-3">💎 Pembayaran Berhasil!</h4><p class="text-muted mb-0">Gems sedang ditambahkan ke akun Anda...</p></div>`;
            loadingModal.show();
            fetch('payment-direct-update.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({order_id: orderId}) })
            .then(r => r.json()).then(data => {
                loadingModal.hide();
                if (data.success) {
                    Swal.fire({ icon: 'success', title: '🎉 Yeay! Pembayaran Berhasil!', html: `<div class="text-center"><div class="mb-3"><i class="bi bi-gem text-warning" style="font-size: 4rem;"></i></div><h4 class="fw-bold mb-3">+${Number(gemsAmount).toLocaleString('id-ID')} Gems</h4><p class="text-muted mb-2">Gems telah ditambahkan ke akun Anda!</p><p class="text-success fw-bold">Total Gems: ${Number(data.total_gems).toLocaleString('id-ID')}</p></div>`, confirmButtonText: 'Lihat Saldo Baru', confirmButtonColor: '#10b981', allowOutsideClick: false }).then(() => window.location.reload());
                } else { Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Pembayaran berhasil, tapi gagal update gems.' }).then(() => window.location.reload()); }
            }).catch(e => { loadingModal.hide(); Swal.fire({ icon: 'error', title: 'Error', text: 'Pembayaran berhasil, tapi terjadi error.' }).then(() => window.location.reload()); });
        }

        function continuePaymentFromHistory(snapToken, orderId, gems) {
            currentOrderId = orderId; currentSnapToken = snapToken;
            snap.pay(snapToken, {
                onSuccess: r => instantDirectUpdate(orderId, gems),
                onPending: r => Swal.fire({ icon: 'info', title: 'Pembayaran Tertunda' }).then(() => location.reload()),
                onError: r => Swal.fire({ icon: 'error', title: 'Pembayaran Gagal' }),
                onClose: () => confirmCancelModal.show()
            });
        }

        function confirmCancelPayment() {
            if (!currentOrderId) { confirmCancelModal.hide(); return; }
            fetch('payment-cancel.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({order_id: currentOrderId}) })
            .then(r => r.json()).then(data => { confirmCancelModal.hide(); if (data.success) Swal.fire({ icon: 'info', title: 'Transaksi Dibatalkan', timer: 3000 }).then(() => location.reload()); })
            .catch(() => confirmCancelModal.hide());
        }

        function dismissCancelModal() {
            confirmCancelModal.hide();
            if (currentSnapToken) {
                const row = document.querySelector(`tr[data-order-id="${currentOrderId}"]`);
                const gems = parseInt(row.querySelector('td:nth-child(3) strong').textContent.replace(/[^0-9]/g, ''));
                snap.pay(currentSnapToken, { onSuccess: r => instantDirectUpdate(currentOrderId, gems), onPending: r => Swal.fire({ icon: 'info', title: 'Pembayaran Tertunda' }).then(() => location.reload()), onError: r => Swal.fire({ icon: 'error', title: 'Pembayaran Gagal' }), onClose: () => confirmCancelModal.show() });
            }
        }
    </script>
</body>
</html>
