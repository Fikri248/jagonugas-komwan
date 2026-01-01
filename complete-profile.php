<?php
// complete-profile.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';
require_once __DIR__ . '/NotificationHelper.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

// Cek apakah ada data Google prefill
if (!isset($_SESSION['google_prefill'])) {
    header('Location: ' . $BASE . '/register.php');
    exit;
}

$googleData = $_SESSION['google_prefill'];
$error = '';
$success = '';

// Daftar Program Studi
$programStudiList = [
    'S1 Informatika',
    'S1 Sistem Informasi',
    'S1 Teknologi Informasi',
    'S1 Rekayasa Perangkat Lunak',
    'S1 Sains Data',
    'S1 Bisnis Digital',
    'S1 Teknik Elektro',
    'S1 Teknik Telekomunikasi',
    'S1 Teknik Komputer',
    'S1 Teknik Industri',
    'S1 Teknik Logistik'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $programStudi = trim((string)($_POST['program_studi'] ?? ''));
        $semester     = (int)($_POST['semester'] ?? 0);

        if ($programStudi === '' || $semester < 1) {
            $error = 'Lengkapi semua field';
        } elseif (!in_array($programStudi, $programStudiList, true)) {
            $error = 'Program studi tidak valid';
        } elseif ($semester < 1 || $semester > 14) {
            $error = 'Semester harus antara 1 sampai 14';
        } else {
            $db = (new Database())->getConnection();

            // Cek apakah email sudah ada (double check)
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$googleData['email']]);
            if ($checkStmt->fetch()) {
                $error = 'Email sudah terdaftar. Silakan login.';
            } else {
                // Buat user baru
                $bonusGems = 75;
                $hashedPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

                $insertStmt = $db->prepare("
                    INSERT INTO users (name, email, password, google_id, avatar, program_studi, semester, role, gems, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'student', ?, NOW())
                ");
                $insertStmt->execute([
                    $googleData['name'],
                    $googleData['email'],
                    $hashedPassword,
                    $googleData['google_id'],
                    $googleData['avatar'] ?? null,
                    $programStudi,
                    $semester,
                    $bonusGems
                ]);
                $newUserId = (int)$db->lastInsertId();

                // Kirim notifikasi welcome
                try {
                    $notif = new NotificationHelper($db);
                    if (method_exists($notif, 'welcome')) {
                        $notif->welcome($newUserId);
                    }
                    if (method_exists($notif, 'create')) {
                        $notif->create($newUserId, 'gem_bonus', 'Selamat! Kamu mendapat ' . $bonusGems . ' Gem gratis sebagai hadiah pendaftaran.', null, null);
                    }
                } catch (Throwable $e) {
                    // Ignore
                }

                // Set session
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['name']    = $googleData['name'];
                $_SESSION['email']   = $googleData['email'];
                $_SESSION['role']    = 'student';
                $_SESSION['gems']    = $bonusGems;

                // Hapus prefill data
                unset($_SESSION['google_prefill']);

                // Redirect ke dashboard
                header('Location: ' . $BASE . '/student-dashboard.php?welcome=1');
                exit;
            }
        }
    } catch (Throwable $e) {
        error_log('Complete Profile Error: ' . $e->getMessage());
        $error = 'Terjadi kesalahan. Silakan coba lagi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Profil - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f7fafc; }
        
        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
        .auth-card { background: #ffffff; border-radius: 20px; box-shadow: 0 18px 45px rgba(15,23,42,0.18); padding: 32px; max-width: 480px; width: 100%; border: 1px solid #e2e8f0; }
        
        .auth-title { font-size: 1.6rem; font-weight: 700; color: #1a202c; margin-bottom: 4px; text-align: center; }
        .auth-subtitle { font-size: 0.95rem; color: #718096; margin-bottom: 24px; text-align: center; }
        
        /* Google User Info */
        .google-user-info { display: flex; align-items: center; gap: 16px; padding: 16px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 12px; margin-bottom: 24px; border: 1px solid #bbf7d0; }
        .google-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 3px solid #10b981; }
        .google-avatar-placeholder { width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.25rem; }
        .google-info h3 { font-size: 1rem; color: #1e293b; margin-bottom: 2px; }
        .google-info p { font-size: 0.85rem; color: #64748b; }
        .google-badge { display: inline-flex; align-items: center; gap: 4px; background: white; padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; color: #059669; font-weight: 600; margin-top: 4px; }
        
        /* Form */
        .auth-form { display: flex; flex-direction: column; gap: 16px; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        
        /* Custom Select */
        .custom-select { position: relative; width: 100%; font-size: 0.95rem; user-select: none; }
        .select-selected { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 2px solid #e2e8f0; border-radius: 14px; cursor: pointer; transition: all 0.3s; }
        .select-selected:hover { border-color: #cbd5e1; }
        .custom-select.active .select-selected { border-color: #667eea; box-shadow: 0 0 0 4px rgba(102,126,234,0.15); }
        .select-text { color: #64748b; font-weight: 500; }
        .select-text.has-value { color: #1e293b; font-weight: 600; }
        .select-arrow { color: #94a3b8; transition: transform 0.3s; }
        .custom-select.active .select-arrow { transform: rotate(180deg); color: #667eea; }
        .select-items { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.12); max-height: 280px; overflow-y: auto; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.25s; z-index: 1000; padding: 8px; }
        .custom-select.active .select-items { opacity: 1; visibility: visible; transform: translateY(0); }
        .select-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 10px; cursor: pointer; color: #475569; font-weight: 500; transition: all 0.2s; margin-bottom: 2px; }
        .select-item:hover { background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); color: #4338ca; }
        .select-item.selected { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; }
        
        /* Alerts */
        .alert { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 0.9rem; font-weight: 500; }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; border: 1px solid #fecaca; }
        
        /* Bonus Info */
        .bonus-info { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; color: #92400e; font-size: 0.9rem; }
        .bonus-info i { font-size: 1.25rem; }
        .bonus-info strong { color: #78350f; }
        
        /* Button */
        .btn { padding: 14px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; cursor: pointer; width: 100%; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102,126,234,0.3); }
        
        @media (max-width: 480px) {
            .auth-card { padding: 24px 20px; }
            .google-user-info { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1 class="auth-title">Lengkapi Profil Kamu</h1>
        <p class="auth-subtitle">Satu langkah lagi untuk mulai belajar</p>

        <!-- Google User Info -->
        <div class="google-user-info">
            <?php if (!empty($googleData['avatar'])): ?>
                <img src="<?php echo htmlspecialchars($googleData['avatar']); ?>" alt="Avatar" class="google-avatar">
            <?php else: ?>
                <div class="google-avatar-placeholder">
                    <?php echo strtoupper(substr($googleData['name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="google-info">
                <h3><?php echo htmlspecialchars($googleData['name']); ?></h3>
                <p><?php echo htmlspecialchars($googleData['email']); ?></p>
                <span class="google-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    Terverifikasi Google
                </span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <!-- Program Studi -->
            <div class="form-group">
                <label>Program Studi</label>
                <div class="custom-select" data-name="program_studi">
                    <div class="select-selected">
                        <span class="select-text <?php echo !empty($_POST['program_studi']) ? 'has-value' : ''; ?>">
                            <?php echo !empty($_POST['program_studi']) ? htmlspecialchars($_POST['program_studi']) : 'Pilih Program Studi'; ?>
                        </span>
                        <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                    <div class="select-items">
                        <?php foreach ($programStudiList as $prodi): ?>
                            <div class="select-item <?php echo (($_POST['program_studi'] ?? '') === $prodi) ? 'selected' : ''; ?>" data-value="<?php echo htmlspecialchars($prodi); ?>">
                                <i class="bi bi-mortarboard-fill"></i>
                                <?php echo htmlspecialchars($prodi); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="program_studi" value="<?php echo htmlspecialchars($_POST['program_studi'] ?? ''); ?>" required>
                </div>
            </div>

            <!-- Semester -->
            <div class="form-group">
                <label>Semester</label>
                <div class="custom-select" data-name="semester">
                    <div class="select-selected">
                        <span class="select-text <?php echo !empty($_POST['semester']) ? 'has-value' : ''; ?>">
                            <?php echo !empty($_POST['semester']) ? 'Semester ' . htmlspecialchars((string)$_POST['semester']) : 'Pilih Semester'; ?>
                        </span>
                        <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                    <div class="select-items">
                        <?php for ($i = 1; $i <= 14; $i++): ?>
                            <div class="select-item <?php echo (($_POST['semester'] ?? '') == $i) ? 'selected' : ''; ?>" data-value="<?php echo $i; ?>">
                                <i class="bi bi-book-fill"></i>
                                Semester <?php echo $i; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="semester" value="<?php echo htmlspecialchars((string)($_POST['semester'] ?? '')); ?>" required>
                </div>
            </div>

            <!-- Bonus Info -->
            <div class="bonus-info">
                <i class="bi bi-gift-fill"></i>
                <span>Kamu akan mendapat <strong>75 Gem gratis!</strong></span>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle-fill"></i>
                Selesaikan Pendaftaran
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const customSelects = document.querySelectorAll('.custom-select');
        customSelects.forEach(select => {
            const selected = select.querySelector('.select-selected');
            const hiddenInput = select.querySelector('input[type="hidden"]');
            const selectText = select.querySelector('.select-text');

            selected.addEventListener('click', function(e) {
                e.stopPropagation();
                customSelects.forEach(s => { if (s !== select) s.classList.remove('active'); });
                select.classList.toggle('active');
            });

            select.querySelectorAll('.select-item').forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.dataset.value;
                    const clone = this.cloneNode(true);
                    const icon = clone.querySelector('i');
                    if (icon) icon.remove();
                    const text = clone.textContent.trim();

                    hiddenInput.value = value;
                    selectText.textContent = text;
                    selectText.classList.add('has-value');

                    select.querySelectorAll('.select-item').forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    setTimeout(() => select.classList.remove('active'), 150);
                });
            });
        });

        document.addEventListener('click', () => customSelects.forEach(s => s.classList.remove('active')));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') customSelects.forEach(s => s.classList.remove('active')); });
    });
    </script>
</body>
</html>
