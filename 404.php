<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="error-page">
    <div class="error-container">
        <div class="error-icon">
            <i class="bi bi-emoji-frown"></i>
        </div>
        <h1>404</h1>
        <h2>Halaman Tidak Ditemukan</h2>
        <p>Maaf, halaman yang kamu cari tidak ada atau sudah dipindahkan.</p>
        <div class="error-actions">
            <a href="<?php echo BASE_PATH; ?>/index.php" class="btn btn-primary">
                <i class="bi bi-house"></i> Kembali ke Beranda
            </a>
            <a href="<?php echo BASE_PATH; ?>/student-dashboard.php" class="btn btn-outline">
                <i class="bi bi-grid"></i> Dashboard
            </a>
        </div>
    </div>
</body>
</html>
