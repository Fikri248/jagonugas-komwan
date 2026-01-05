<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url_path(string $path = ''): string {
    $base = defined('BASE_PATH') ? (string) constant('BASE_PATH') : '';
    $path = '/' . ltrim($path, '/');
    return rtrim($base, '/') . $path;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url_path('login.php'));
    exit;
}

$admin_name = $_SESSION['name'];
$success = '';
$error = '';

// Handle Package Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_packages') {
        try {
            $pdo->beginTransaction();
            
            foreach ($_POST['packages'] as $code => $data) {
                $price = (int) $data['price'];
                $gems = (int) $data['gems'];
                $bonus = (int) $data['bonus'];
                $total_gems = $gems + $bonus;
                
                // ✅ UPDATE: Pakai total_gems yang baru dihitung
                $stmt = $pdo->prepare("
                    UPDATE gem_packages 
                    SET name = ?, price = ?, gems = ?, bonus = ?, total_gems = ?, updated_at = NOW()
                    WHERE code = ?
                ");
                $stmt->execute([
                    $data['name'],
                    $price,
                    $gems,
                    $bonus,
                    $total_gems,
                    $code
                ]);
            }
            
            $pdo->commit();
            
            // ✅ Clear cache setelah update (opsional tapi recommended)
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            $success = 'Paket berhasil diperbarui! Perubahan akan langsung terlihat di halaman student.';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal memperbarui paket: ' . $e->getMessage();
        }
    }
    
    // Handle Clear Cache
    if ($_POST['action'] === 'clear_cache') {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        $success = 'Cache berhasil dibersihkan!';
    }
}

// Get current packages
try {
    $stmt = $pdo->query("SELECT * FROM gem_packages ORDER BY FIELD(code, 'basic', 'pro', 'plus')");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $packages = [];
    $error = 'Gagal memuat data paket: ' . $e->getMessage();
}

// Get system stats
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM gem_transactions");
    $total_transactions = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(amount) as total FROM gem_transactions WHERE transaction_status = 'settlement'");
    $total_revenue = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    $total_users = 0;
    $total_transactions = 0;
    $total_revenue = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - JagoNugas Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 0;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #718096;
            margin: 0;
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }

        .card-header-custom h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body-custom {
            padding: 2rem;
        }

        .package-item {
            background: #f8f9fa;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .package-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .package-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .package-badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .package-badge.basic {
            background: #6c757d;
            color: white;
        }

        .package-badge.pro {
            background: #667eea;
            color: white;
        }

        .package-badge.plus {
            background: #ffc107;
            color: #1a202c;
        }

        .form-group-custom {
            margin-bottom: 1.5rem;
        }

        .form-group-custom label {
            display: block;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control-custom {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-group-custom {
            display: flex;
            gap: 1rem;
        }

        .input-group-custom > div {
            flex: 1;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary-custom {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-custom:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .btn-danger-custom {
            background: #dc2626;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-danger-custom:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }

        .stat-card h4 {
            font-size: 0.85rem;
            color: #718096;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
        }

        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 2px solid #10b981;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 2px solid #dc2626;
        }

        .calculation-preview {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .calculation-preview .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .calculation-preview .row:last-child {
            margin-bottom: 0;
            padding-top: 0.5rem;
            border-top: 2px solid #667eea;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .danger-zone {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #dc2626;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .danger-zone h3 {
            color: #991b1b;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .danger-zone p {
            color: #991b1b;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .divider {
            margin: 24px 0;
            border: none;
            border-top: 2px solid #f1f5f9;
        }

        /* ✅ NEW: Info box for student page sync */
        .sync-info {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border: 2px solid #3b82f6;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sync-info i {
            font-size: 1.5rem;
            color: #1e40af;
        }

        .sync-info p {
            margin: 0;
            color: #1e3a8a;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .input-group-custom {
                flex-direction: column;
            }

            .package-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="bi bi-gear-fill"></i> Pengaturan Sistem</h1>
            <p>Kelola paket membership, harga, dan konfigurasi platform</p>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
        <div class="alert-custom alert-success">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-custom alert-error">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- System Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4><i class="bi bi-people-fill"></i> Total Users</h4>
                <div class="value"><?= number_format($total_users) ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #10b981;">
                <h4><i class="bi bi-receipt"></i> Total Transaksi</h4>
                <div class="value"><?= number_format($total_transactions) ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #fbbf24;">
                <h4><i class="bi bi-currency-dollar"></i> Total Revenue</h4>
                <div class="value">Rp <?= number_format($total_revenue, 0, ',', '.') ?></div>
            </div>
        </div>

        <!-- Package Settings -->
        <div class="settings-card">
            <div class="card-header-custom">
                <h3><i class="bi bi-gem"></i> Pengaturan Paket Gems</h3>
            </div>
            <div class="card-body-custom">
                <!-- ✅ NEW: Sync Info -->
                <div class="sync-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <p><strong>Auto-Sync:</strong> Perubahan harga dan jumlah gems akan <strong>langsung terlihat</strong> di halaman student purchase gems tanpa perlu refresh manual.</p>
                </div>

                <form method="POST" action="" id="packageForm">
                    <input type="hidden" name="action" value="update_packages">

                    <?php foreach ($packages as $package): ?>
                    <div class="package-item">
                        <div class="package-header">
                            <span class="package-badge <?= strtolower($package['code']) ?>">
                                <?= htmlspecialchars($package['name']) ?>
                            </span>
                            <small class="text-muted">Kode: <?= htmlspecialchars($package['code']) ?></small>
                        </div>

                        <div class="form-group-custom">
                            <label>Nama Paket</label>
                            <input type="text" 
                                   name="packages[<?= $package['code'] ?>][name]" 
                                   class="form-control-custom" 
                                   value="<?= htmlspecialchars($package['name']) ?>" 
                                   required>
                        </div>

                        <div class="input-group-custom">
                            <div class="form-group-custom">
                                <label><i class="bi bi-cash"></i> Harga (Rp)</label>
                                <input type="number" 
                                       name="packages[<?= $package['code'] ?>][price]" 
                                       class="form-control-custom package-price" 
                                       value="<?= $package['price'] ?>" 
                                       min="0" 
                                       step="1000"
                                       data-code="<?= $package['code'] ?>"
                                       required>
                            </div>

                            <div class="form-group-custom">
                                <label><i class="bi bi-gem"></i> Jumlah Gems</label>
                                <input type="number" 
                                       name="packages[<?= $package['code'] ?>][gems]" 
                                       class="form-control-custom package-gems" 
                                       value="<?= $package['gems'] ?>" 
                                       min="0"
                                       data-code="<?= $package['code'] ?>"
                                       required>
                            </div>

                            <div class="form-group-custom">
                                <label><i class="bi bi-gift-fill"></i> Bonus Gems</label>
                                <input type="number" 
                                       name="packages[<?= $package['code'] ?>][bonus]" 
                                       class="form-control-custom package-bonus" 
                                       value="<?= $package['bonus'] ?>" 
                                       min="0"
                                       data-code="<?= $package['code'] ?>"
                                       required>
                            </div>
                        </div>

                        <div class="calculation-preview" id="preview-<?= $package['code'] ?>">
                            <div class="row">
                                <span>Gems Base:</span>
                                <strong class="gems-base"><?= number_format($package['gems']) ?></strong>
                            </div>
                            <div class="row">
                                <span>Bonus:</span>
                                <strong class="gems-bonus">+<?= number_format($package['bonus']) ?></strong>
                            </div>
                            <div class="row">
                                <span>Total Gems:</span>
                                <strong class="gems-total"><?= number_format($package['total_gems']) ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn-save">
                            <i class="bi bi-save"></i>
                            <span>Simpan Perubahan Paket</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment Settings -->
        <div class="settings-card">
            <div class="card-header-custom">
                <h3><i class="bi bi-credit-card"></i> Pengaturan Payment Gateway</h3>
            </div>
            <div class="card-body-custom">
                <div class="form-group-custom">
                    <label>Midtrans Environment</label>
                    <select class="form-control-custom">
                        <option value="sandbox" selected>Sandbox (Testing)</option>
                        <option value="production">Production</option>
                    </select>
                </div>

                <div class="form-group-custom">
                    <label>Midtrans Server Key</label>
                    <input type="password" class="form-control-custom" value="<?= MIDTRANS_SERVER_KEY ?>" readonly>
                    <small class="text-muted">Diatur di config.php</small>
                </div>

                <div class="form-group-custom">
                    <label>Midtrans Client Key</label>
                    <input type="text" class="form-control-custom" value="<?= MIDTRANS_CLIENT_KEY ?>" readonly>
                    <small class="text-muted">Diatur di config.php</small>
                </div>
            </div>
        </div>

        <!-- Maintenance & Cache -->
        <div class="settings-card">
            <div class="card-header-custom">
                <h3><i class="bi bi-tools"></i> Maintenance & Cache</h3>
            </div>
            <div class="card-body-custom">
                <form method="POST" onsubmit="return confirm('Yakin ingin clear cache?')">
                    <input type="hidden" name="action" value="clear_cache">
                    
                    <div style="margin-bottom: 20px;">
                        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 8px; color: #1a202c;">Clear Cache</h3>
                        <p style="color: #64748b; font-size: 0.9rem; margin: 0;">Hapus cache sistem untuk memperbarui data dan meningkatkan performa</p>
                    </div>
                    
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-arrow-clockwise"></i> Clear Cache
                    </button>
                </form>
                
                <hr class="divider">
                
                <div class="danger-zone">
                    <h3><i class="bi bi-exclamation-triangle-fill"></i> Danger Zone</h3>
                    <p>Aksi di bawah ini bersifat permanen dan tidak dapat dibatalkan.</p>
                    
                    <button type="button" class="btn-danger-custom" onclick="if(confirm('PERINGATAN! Ini akan menghapus SEMUA data. Yakin?')) alert('Feature coming soon')">
                        <i class="bi bi-trash"></i> Reset Database
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time calculation preview
        document.querySelectorAll('.package-gems, .package-bonus').forEach(input => {
            input.addEventListener('input', function() {
                const code = this.dataset.code;
                const preview = document.getElementById('preview-' + code);
                
                const gemsInput = document.querySelector(`.package-gems[data-code="${code}"]`);
                const bonusInput = document.querySelector(`.package-bonus[data-code="${code}"]`);
                
                const gems = parseInt(gemsInput.value) || 0;
                const bonus = parseInt(bonusInput.value) || 0;
                const total = gems + bonus;
                
                preview.querySelector('.gems-base').textContent = gems.toLocaleString('id-ID');
                preview.querySelector('.gems-bonus').textContent = '+' + bonus.toLocaleString('id-ID');
                preview.querySelector('.gems-total').textContent = total.toLocaleString('id-ID');
            });
        });

        // Form validation
        document.getElementById('packageForm').addEventListener('submit', function(e) {
            const prices = document.querySelectorAll('.package-price');
            let hasError = false;

            prices.forEach(input => {
                if (parseInt(input.value) < 1000) {
                    hasError = true;
                    input.style.borderColor = '#dc2626';
                } else {
                    input.style.borderColor = '#e2e8f0';
                }
            });

            if (hasError) {
                e.preventDefault();
                alert('Harga minimal adalah Rp 1.000');
                return false;
            }

            // ✅ Show loading state
            const submitBtn = this.querySelector('.btn-save');
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
            submitBtn.disabled = true;
        });

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert-custom').forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                alert.style.transition = 'all 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>
