<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_PATH . "/login");
    exit;
}

$name = $_SESSION['name'] ?? 'User';
$gemBalance = 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
</head>
<body class="dashboard-page">
    <!-- Top Navbar -->
<header class="jn-topbar">
    <div class="jn-topbar-inner">
        <!-- Kiri: logo + nama app -->
        <div class="jn-topbar-left">
            <span class="jn-topbar-logo-text">Dashboardmu</span>
        </div>

        <!-- Tengah: search kecil -->
        <form class="jn-topbar-search">
            <input type="text" placeholder="Cari jawaban untuk pertanyaan apa aja..." />
        </form>

        <!-- Kanan: aksi -->
        <div class="jn-topbar-right">
             <!-- Info Gem -->
    <div class="jn-topbar-gem">
        <span class="jn-topbar-gem-icon">💎</span>
        <div class="jn-topbar-gem-info">
            <span class="jn-topbar-gem-label">Gem kamu</span>
            <span class="jn-topbar-gem-value">
    <?php echo number_format((float)$gemBalance, 0, ',', '.'); ?>
</span>

        </div>
    </div>
            <!-- menu text baru -->
            <nav class="jn-topbar-links">
                <a href="/jagonugas-native/mentor">Mentor</a>
                <a href="/jagonugas-native/membership">Membership</a>
            </nav>

            <button type="button" class="jn-topbar-icon">🔔</button>

            <div class="jn-topbar-user">
                <div class="jn-topbar-avatar">
                    <?php echo strtoupper(substr($name,0,1)); ?>
                </div>
                <div class="jn-topbar-user-info">
                    <span class="jn-topbar-user-name"><?php echo htmlspecialchars($name); ?></span>
                    <span class="jn-topbar-user-role">Mahasiswa</span>
                </div>

                <div class="jn-topbar-menu">
                    <a href="#">Histori Chat</a>
                    <a href="#">Pengaturan Akun</a>
                    <a href="/jagonugas-native/logout">Keluar</a>
                </div>
            </div>
        </div>
    </div>
</header>


    <!-- Main Layout -->
<div class="dash-container dash-main">
    <main class="dash-content">
        <!-- Hero pertanyaan -->
        <section class="ask-hero">
    <div class="ask-hero-left">
        <h1 class="ask-title">Lagi Kesulitan?</h1>
        <p class="ask-subtitle">
            Tulis pertanyaan lo dan tunggu mentor atau mahasiswa lain bantu jawabnya.
        </p>
        <a href="<?php echo BASE_PATH; ?>/diskusi" class="ask-button">Tanya Sekarang</a>
    </div>

    <div class="ask-hero-right">
        <div class="ask-stat-card">
            <div class="ask-stat-label">Jawaban yang lo bantu</div>
            <div class="ask-stat-value">0</div>
        </div>
        <div class="ask-stat-card">
            <div class="ask-stat-label">Gem yang lo dapet</div>
            <div class="ask-stat-value">0</div>
        </div>
        <div class="ask-stat-card">
            <div class="ask-stat-label">Waktu aktif</div>
            <div class="ask-stat-value">3 jam</div>
        </div>
    </div>
</section>


        <!-- Daftar pertanyaan user lain -->
        <section class="ask-list">
    <h2 class="ask-list-title">Pertanyaan dari Mahasiswa Lain</h2>

    <!-- Belum terjawab -->
    <article class="ask-item">
        <header class="ask-item-header">
            <div>
                <div class="ask-item-topic">Matematika · 43 menit yang lalu</div>
                <div class="ask-item-stats">
                    <span>1 lampiran</span>
                    <span>0 jawaban</span>
                </div>
            </div>
            <div class="ask-item-point">+5 gem</div>
        </header>

        <p class="ask-item-title">
            Yang lengkap dan pakai caranya juga yaa
        </p>

        <footer class="ask-item-footer">
            <button class="ask-answer-btn">Jawab</button>
        </footer>
    </article>

    <!-- Sudah terjawab -->
    <article class="ask-item answered">
        <header class="ask-item-header">
            <div>
                <div class="ask-item-topic">Basis Data · 2 jam yang lalu</div>
                <div class="ask-item-stats">
                    <span>Tanpa lampiran</span>
                    <span>3 jawaban</span>
                </div>
            </div>
            <div class="ask-item-right">
                <span class="ask-badge-answered">Terjawab</span>
                <div class="ask-item-point">+10 gem</div>
            </div>
        </header>

        <p class="ask-item-title">
            Perbedaan ERD dan diagram kelas itu apa aja ya?
        </p>

        <footer class="ask-item-footer">
            <button class="ask-view-btn">Lihat Jawaban</button>
        </footer>
    </article>
</section>

    </main>
</div>

</body>
</html>
