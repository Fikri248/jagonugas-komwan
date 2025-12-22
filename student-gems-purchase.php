<?php
// student-gems-purchase.php
session_start();
require_once 'config.php';
require_once 'db.php';

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT name, email, gems FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get transaction history
$stmt = $pdo->prepare("
    SELECT * FROM gem_transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Packages definition
$packages = [
    'basic' => [
        'name' => 'Basic',
        'price' => 10000,
        'gems' => 4500,
        'bonus' => 0,
        'total_gems' => 4500,
        'badge' => 'secondary',
        'icon' => 'bi-box'
    ],
    'pro' => [
        'name' => 'Pro',
        'price' => 25000,
        'gems' => 12500,
        'bonus' => 500,
        'total_gems' => 13000,
        'badge' => 'primary',
        'icon' => 'bi-box-seam',
        'popular' => true
    ],
    'plus' => [
        'name' => 'Plus',
        'price' => 50000,
        'gems' => 27000,
        'bonus' => 2000,
        'total_gems' => 29000,
        'badge' => 'warning',
        'icon' => 'bi-box-seam-fill',
        'best_value' => true
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beli Gem - JagoNugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .gradient-bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .gradient-bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .hover-lift {
            transition: all 0.3s ease;
        }
        .hover-lift:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2) !important;
        }
        .package-card {
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .package-card:hover {
            border-color: #667eea;
        }
        .gem-icon {
            font-size: 3rem;
            background: linear-gradient(135deg, #fbbf24, #f59e0b, #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            position: relative;
            overflow: hidden;
        }
        .balance-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
        .badge-popular {
            position: absolute;
            top: -10px;
            right: 20px;
            transform: rotate(10deg);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: rotate(10deg) scale(1); }
            50% { transform: rotate(10deg) scale(1.1); }
        }
        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .feature-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <?php include 'student-navbar.php'; ?>

    <div class="container py-5">
        <!-- Header Section -->
        <div class="text-center mb-5">
            <h1 class="display-4 fw-bold mb-3">
                <i class="bi bi-gem text-warning"></i> Beli Gem
            </h1>
            <p class="lead text-muted">Pilih paket gem yang sesuai dengan kebutuhanmu!</p>
        </div>

        <!-- Current Balance Card -->
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto">
                <div class="card balance-card text-white shadow-lg">
                    <div class="card-body p-4 position-relative">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <p class="mb-2 opacity-75">
                                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($user['name']) ?>
                                </p>
                                <h5 class="mb-1 opacity-75">Saldo Gem Saat Ini</h5>
                                <h2 class="mb-0 fw-bold display-4">
                                    <i class="bi bi-gem"></i> <?= number_format($user['gems']) ?>
                                </h2>
                                <p class="mt-2 mb-0 opacity-75">
                                    <small>1 gem = 1 menit sesi mentoring</small>
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="bi bi-wallet2" style="font-size: 6rem; opacity: 0.2;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Packages Section -->
        <h3 class="text-center mb-4 fw-bold">Pilih Paket Membership</h3>
        <div class="row g-4 mb-5">
            <?php foreach ($packages as $key => $package): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card package-card h-100 shadow-sm hover-lift position-relative">
                    <?php if (!empty($package['popular'])): ?>
                        <div class="badge-popular">
                            <span class="badge bg-danger px-3 py-2 fs-6">
                                <i class="bi bi-fire"></i> Terpopuler
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($package['best_value'])): ?>
                        <div class="badge-popular">
                            <span class="badge bg-success px-3 py-2 fs-6">
                                <i class="bi bi-star-fill"></i> Best Value
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="card-body text-center p-4">
                        <!-- Package Icon -->
                        <div class="mb-3">
                            <i class="<?= $package['icon'] ?> gem-icon"></i>
                        </div>

                        <!-- Package Name -->
                        <h3 class="mb-3">
                            <span class="badge bg-<?= $package['badge'] ?> px-4 py-2 fs-5">
                                <?= $package['name'] ?>
                            </span>
                        </h3>
                        
                        <!-- Price -->
                        <div class="mb-4">
                            <div class="display-5 fw-bold text-dark">
                                Rp <?= number_format($package['price'], 0, ',', '.') ?>
                            </div>
                            <small class="text-muted">Pembayaran sekali</small>
                        </div>

                        <hr class="my-4">

                        <!-- Gem Details -->
                        <div class="mb-4">
                            <ul class="list-unstyled feature-list text-start">
                                <li class="d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-gem text-warning me-2"></i>Gem Dasar</span>
                                    <strong><?= number_format($package['gems']) ?></strong>
                                </li>
                                <?php if ($package['bonus'] > 0): ?>
                                <li class="d-flex align-items-center justify-content-between text-success">
                                    <span><i class="bi bi-gift-fill me-2"></i>Bonus Gem</span>
                                    <strong>+<?= number_format($package['bonus']) ?></strong>
                                </li>
                                <?php endif; ?>
                                <li class="d-flex align-items-center justify-content-between border-top pt-3 mt-3">
                                    <span class="fw-bold"><i class="bi bi-star-fill text-warning me-2"></i>Total Gem</span>
                                    <h4 class="mb-0 text-primary"><?= number_format($package['total_gems']) ?></h4>
                                </li>
                            </ul>
                        </div>

                        <!-- Features -->
                        <div class="mb-4 text-start">
                            <p class="small text-muted mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Pembayaran aman via Midtrans</p>
                            <p class="small text-muted mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Gem langsung masuk otomatis</p>
                            <p class="small text-muted mb-0"><i class="bi bi-check-circle-fill text-success me-2"></i>Berlaku selamanya</p>
                        </div>

                        <!-- Buy Button -->
                        <button type="button" 
                                class="btn btn-<?= $package['badge'] ?> w-100 btn-lg fw-bold"
                                onclick="buyPackage('<?= $key ?>', <?= $package['price'] ?>, <?= $package['total_gems'] ?>)">
                            <i class="bi bi-credit-card me-2"></i>Beli Sekarang
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Why Choose Us Section -->
        <div class="row mb-5">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h4 class="mb-4 text-center"><i class="bi bi-shield-check text-success me-2"></i>Kenapa Beli Gem?</h4>
                        <div class="row g-4">
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <i class="bi bi-lightning-charge-fill text-warning" style="font-size: 3rem;"></i>
                                </div>
                                <h5>Akses Instant</h5>
                                <p class="text-muted">Gem langsung masuk ke akunmu setelah pembayaran berhasil</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <i class="bi bi-shield-fill-check text-success" style="font-size: 3rem;"></i>
                                </div>
                                <h5>Aman & Terpercaya</h5>
                                <p class="text-muted">Pembayaran diproses oleh Midtrans, payment gateway terpercaya</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <i class="bi bi-infinity text-primary" style="font-size: 3rem;"></i>
                                </div>
                                <h5>Tidak Ada Expired</h5>
                                <p class="text-muted">Gem yang kamu beli berlaku selamanya, tanpa batas waktu</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction History -->
        <?php if (!empty($transactions)): ?>
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Riwayat Transaksi
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Paket</th>
                                        <th>Gems</th>
                                        <th>Harga</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $trx): ?>
                                    <tr>
                                        <td>
                                            <code class="small"><?= htmlspecialchars($trx['order_id']) ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $packages[$trx['package']]['badge'] ?>">
                                                <?= $packages[$trx['package']]['name'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="bi bi-gem text-warning"></i> 
                                            <strong><?= number_format($trx['gems']) ?></strong>
                                        </td>
                                        <td>Rp <?= number_format($trx['amount'], 0, ',', '.') ?></td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'pending' => 'warning',
                                                'settlement' => 'success',
                                                'capture' => 'success',
                                                'deny' => 'danger',
                                                'cancel' => 'danger',
                                                'expire' => 'secondary',
                                                'failure' => 'danger'
                                            ];
                                            $statusText = [
                                                'pending' => 'Menunggu',
                                                'settlement' => 'Berhasil',
                                                'capture' => 'Berhasil',
                                                'deny' => 'Ditolak',
                                                'cancel' => 'Dibatalkan',
                                                'expire' => 'Kadaluarsa',
                                                'failure' => 'Gagal'
                                            ];
                                            $status = $trx['transaction_status'];
                                            ?>
                                            <span class="badge bg-<?= $statusClass[$status] ?? 'secondary' ?>">
                                                <?= $statusText[$status] ?? $status ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('d M Y, H:i', strtotime($trx['created_at'])) ?>
                                            </small>
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
            <div class="modal-content">
                <div class="modal-body text-center p-5">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5>Memproses pembayaran...</h5>
                    <p class="text-muted mb-0">Mohon tunggu sebentar</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let loadingModal = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        });

        function buyPackage(packageType, price, gems) {
            // Show loading modal
            loadingModal.show();

            // Create transaction
            fetch('payment-process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    package: packageType,
                    price: price,
                    gems: gems
                })
            })
            .then(response => response.json())
            .then(data => {
                loadingModal.hide();
                
                if (data.success) {
                    // Open Midtrans Snap
                    snap.pay(data.snap_token, {
                        onSuccess: function(result) {
                            console.log('Payment success:', result);
                            checkPaymentStatus(data.order_id);
                        },
                        onPending: function(result) {
                            console.log('Payment pending:', result);
                            showAlert('warning', 'Pembayaran Tertunda', 'Silakan selesaikan pembayaran Anda.');
                            setTimeout(() => location.reload(), 2000);
                        },
                        onError: function(result) {
                            console.log('Payment error:', result);
                            showAlert('danger', 'Pembayaran Gagal', 'Terjadi kesalahan. Silakan coba lagi.');
                        },
                        onClose: function() {
                            console.log('Payment popup closed');
                        }
                    });
                } else {
                    showAlert('danger', 'Error', data.message || 'Terjadi kesalahan. Silakan coba lagi.');
                }
            })
            .catch(error => {
                loadingModal.hide();
                console.error('Error:', error);
                showAlert('danger', 'Error', 'Terjadi kesalahan sistem. Silakan coba lagi.');
            });
        }

        function checkPaymentStatus(orderId) {
            fetch('payment-check.php?order_id=' + orderId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.status === 'settlement' || data.status === 'capture') {
                        showAlert('success', 'Pembayaran Berhasil! 🎉', 
                            `Gems sebanyak ${data.gems.toLocaleString()} telah ditambahkan ke akun Anda.`);
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showAlert('warning', 'Status Pembayaran', 'Status: ' + data.status);
                        setTimeout(() => location.reload(), 2000);
                    }
                } else {
                    showAlert('danger', 'Error', 'Gagal memeriksa status pembayaran.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error', 'Gagal memeriksa status pembayaran.');
            });
        }

        function showAlert(type, title, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
                     role="alert" style="z-index: 9999; min-width: 300px; max-width: 500px;">
                    <strong>${title}</strong><br>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                });
            }, 5000);
        }
    </script>
</body>
</html>