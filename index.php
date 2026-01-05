<?php
require_once __DIR__ . '/config.php';

// ✅ TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}


$isLoggedIn = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? '';

function url_path(string $path = ''): string {
    $path = '/' . ltrim($path, '/');
    return BASE_PATH . ($path === '/' ? '' : $path);
}

$dashboardUrl = url_path('student-dashboard.php');
if ($role === 'admin') $dashboardUrl = url_path('admin-dashboard.php');
if ($role === 'mentor') $dashboardUrl = url_path('mentor-dashboard.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #ffffff; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 40px; }

        /* ===== NAVBAR ===== */
        .navbar { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 1rem 0; position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo h1 { color: #667eea; font-size: 1.5rem; font-weight: 700; }
        .nav-menu { display: flex; gap: 2rem; align-items: center; }
        .nav-menu a { position: relative; color: #4a5568; text-decoration: none; font-weight: 500; font-size: 0.98rem; padding: 0.4rem 0.8rem; border-radius: 999px; transition: color 0.2s ease, background-color 0.2s ease, transform 0.15s ease; }
        .nav-menu a:hover { color: #1a202c; background: rgba(102,126,234,0.08); transform: translateY(-1px); }
        .nav-buttons { display: flex; gap: 1rem; align-items: center; }

        /* ===== BUTTONS ===== */
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; border: 2px solid transparent; cursor: pointer; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102,126,234,0.3); }
        .btn-outline { border: 2px solid #e2e8f0; color: #4a5568; background: transparent; }
        .btn-outline:hover { border-color: #667eea; color: #667eea; background: rgba(102,126,234,0.05); }
        .btn-text { background: transparent; color: #4a5568; padding: 0.5rem 1rem; }
        .btn-text:hover { color: #667eea; }
        .btn-full { width: 100%; justify-content: center; }

        /* ===== HERO SECTION ===== */
        .hero-corporate { padding: 30px 0 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); position: relative; overflow: hidden; }
        .hero-corporate::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100%%" height="100%%" fill="url(%%23grid)"/></svg>'); opacity: 0.3; }
        .hero-corporate .container { position: relative; z-index: 1; }
        .hero-two-column { display: grid; grid-template-columns: 1.4fr 1fr; gap: 60px; align-items: center; }
        .badge { display: inline-block; padding: 8px 16px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; color: white; font-size: 0.9rem; font-weight: 600; margin-bottom: 24px; }
        .hero-title { font-size: 3.5rem; font-weight: 800; color: white; line-height: 1.2; margin-bottom: 24px; letter-spacing: -0.02em; }
        .hero-description { font-size: 1.25rem; color: rgba(255,255,255,0.9); margin-bottom: 32px; line-height: 1.7; }
        .hero-cta { display: flex; gap: 16px; margin-bottom: 48px; }
        .btn-hero-cta { background: white; color: #667eea; padding: 1rem 2rem; font-size: 1.05rem; border-radius: 12px; font-weight: 700; }
        .btn-hero-cta:hover { background: #f7fafc; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .hero-stats { display: flex; justify-content: flex-start; gap: 48px; margin-top: 24px; }
        .hero-stats .stat { text-align: center; }
        .hero-stats .stat-number { font-size: 1.75rem; font-weight: 800; color: white; line-height: 1; margin-bottom: 4px; }
        .hero-stats .stat-label { font-size: 0.75rem; color: rgba(255,255,255,0.8); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .hero-logo-wrapper { display: flex; justify-content: center; }
        .hero-logo-card { background: #fff; border-radius: 32px; padding: 32px 40px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; border: 4px solid rgba(255,255,255,0.3); }
        .hero-logo-img { max-width: 260px; height: auto; display: block; border-radius: 24px; }

        /* ===== SECTION HEADER ===== */
        .section-header { text-align: center; margin-bottom: 60px; display: flex; flex-direction: column; align-items: center; }
        .section-badge { display: inline-block; padding: 8px 20px; background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); color: #667eea; font-size: 0.85rem; font-weight: 600; border-radius: 50px; margin-bottom: 16px; width: fit-content; }
        .section-title { font-size: 2.25rem; font-weight: 800; color: #1e293b; margin-bottom: 16px; line-height: 1.3; max-width: 600px; }
        .section-subtitle { font-size: 1.1rem; color: #64748b; line-height: 1.6; max-width: 550px; }

        /* ===== FEATURES ===== */
        .features-corporate { padding: 40px 0; }
        .features-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
        .feature-card-corporate { padding: 32px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; transition: all 0.3s ease; }
        .feature-card-corporate:hover { border-color: #667eea; transform: translateY(-4px); box-shadow: 0 12px 24px rgba(102,126,234,0.15); }
        .feature-icon-corporate { font-size: 2.5rem; margin-bottom: 16px; }
        .feature-card-corporate h3 { font-size: 1.25rem; font-weight: 700; color: #1a202c; margin-bottom: 12px; }
        .feature-card-corporate p { color: #718096; line-height: 1.7; }

        /* ===== HOW IT WORKS ===== */
        .how-it-works-corporate { padding: 40px 0; background: #f7fafc; }
        .steps-timeline { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; margin-top: 40px; }
        .step-corporate { text-align: center; position: relative; }
        .step-number-corporate { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; margin: 0 auto 24px; box-shadow: 0 8px 20px rgba(102,126,234,0.3); }
        .step-corporate h3 { font-size: 1.5rem; color: #1a202c; margin-bottom: 12px; }
        .step-corporate p { color: #718096; line-height: 1.7; }

        /* ===== PRICING ===== */
        .pricing-corporate { padding: 40px 0; }
        .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; align-items: stretch; margin-top: 40px; }
        .pricing-card { background: white; border: 2px solid #e2e8f0; border-radius: 16px; padding: 40px; transition: all 0.3s ease; position: relative; }
        .pricing-card:hover { border-color: #667eea; transform: translateY(-8px); box-shadow: 0 20px 40px rgba(102,126,234,0.15); }
        .pricing-card.featured { border-color: #667eea; background: linear-gradient(135deg, rgba(102,126,234,0.05) 0%, rgba(118,75,162,0.05) 100%); }
        .popular-badge { position: absolute; top: -16px; left: 50%; transform: translateX(-50%); background: #667eea; color: white; padding: 6px 20px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
        .pricing-header h3 { font-size: 1.5rem; color: #1a202c; margin-bottom: 8px; }
        .price { font-size: 3rem; font-weight: 700; color: #667eea; margin-bottom: 8px; }
        .gem-amount { font-size: 1.1rem; color: #718096; margin-bottom: 32px; }
        .pricing-features { list-style: none; margin-bottom: 32px; }
        .pricing-features li { padding: 12px 0; color: #4a5568; border-bottom: 1px solid #e2e8f0; }
        .pricing-features li:last-child { border-bottom: none; }

        /* ===== TESTIMONIALS ===== */
        .testimonials-corporate { padding: 40px 0; background: #f7fafc; }
        .testimonials-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
        .testimonial-card { background: white; padding: 32px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .stars { color: #fbbf24; font-size: 1.25rem; margin-bottom: 16px; }
        .testimonial-card p { color: #4a5568; line-height: 1.7; margin-bottom: 24px; font-style: italic; }
        .testimonial-author { display: flex; gap: 12px; align-items: center; }
        .author-avatar { width: 44px !important; height: 44px !important; min-width: 44px !important; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50% !important; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.8rem; overflow: hidden !important; flex: 0 0 44px !important; }
        .author-avatar img { width: 100% !important; height: 100% !important; object-fit: cover; display: block; }
        .author-name { font-weight: 600; color: #1a202c; }
        .author-info { font-size: 0.85rem; color: #718096; }

        /* ===== FOOTER ===== */
        .footer-corporate { background: #1a202c; color: white; padding: 64px 0 24px; }
        .footer-content { display: grid; grid-template-columns: 2fr 3fr; gap: 64px; margin-bottom: 48px; }
        .footer-brand { display: flex; flex-direction: column; gap: 16px; }
        .footer-brand h3 { font-size: 1.5rem; color: #667eea; }
        .footer-brand p { color: #a0aec0; line-height: 1.7; }
        .footer-links { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
        .footer-column h4 { font-size: 1rem; margin-bottom: 16px; color: white; }
        .footer-column a { display: block; color: #a0aec0; text-decoration: none; margin-bottom: 12px; transition: color 0.2s; }
        .footer-column a:hover { color: #667eea; }
        .footer-bottom { display: flex; justify-content: space-between; align-items: center; padding-top: 32px; border-top: 1px solid rgba(255,255,255,0.1); }
        .footer-bottom p { color: #a0aec0; }
        .social-links { display: flex; gap: 24px; }
        .social-links a { color: #a0aec0; text-decoration: none; font-size: 1.25rem; transition: color 0.2s; }
        .social-links a:hover { color: #667eea; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .hero-two-column { grid-template-columns: 1fr; row-gap: 40px; text-align: center; }
            .hero-cta { justify-content: center; }
            .hero-stats { justify-content: center; }
            .features-grid-3, .pricing-grid, .testimonials-grid, .steps-timeline { grid-template-columns: 1fr; }
            .nav-menu { display: none; }
        }
        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .section-title { font-size: 1.75rem; }
            .container { padding: 0 20px; }
            .footer-content { grid-template-columns: 1fr; }
            .hero-stats { flex-wrap: wrap; gap: 24px; }
            .hero-stats .stat-number { font-size: 1.5rem; }
            .footer-bottom { flex-direction: column; gap: 16px; text-align: center; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="<?php echo htmlspecialchars(url_path('index.php')); ?>" style="text-decoration:none;">
                    <h1>JagoNugas</h1>
                </a>
            </div>
            <div class="nav-menu">
                <a href="#features">Fitur</a>
                <a href="#how-it-works">Cara Kerja</a>
                <a href="#pricing">Harga</a>
                <a href="#testimonials">Testimoni</a>
            </div>
            <div class="nav-buttons">
                <?php if ($isLoggedIn): ?>
                    <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="btn btn-text">
                        Dashboard<?php echo $name ? ' (' . htmlspecialchars($name) . ')' : ''; ?>
                    </a>
                    <a href="<?php echo htmlspecialchars(url_path('logout.php')); ?>" class="btn btn-primary">Logout</a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars(url_path('login.php')); ?>" class="btn btn-text">Login</a>
                    <a href="<?php echo htmlspecialchars(url_path('register.php')); ?>" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-corporate">
        <div class="container hero-two-column">
            <div class="hero-content">
                <div class="badge">
                    <i class="bi bi-rocket-takeoff-fill"></i>
                    &nbsp;Dipercaya 5,000+ Mahasiswa
                </div>
                <h1 class="hero-title">Selesain Tugas lo dengan Bantuan Kakak Tingkat Terbaik</h1>
                <p class="hero-description">
                    Platform tutoring terpercaya untuk mahasiswa Indonesia.
                    Konsultasi langsung dengan mentor berpengalaman, harga terjangkau, hasil terbukti.
                </p>
                <div class="hero-cta">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="btn btn-hero-cta">
                            <span>Ke Dashboard</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars(url_path('register.php')); ?>" class="btn btn-hero-cta">
                            <span>Mulai Gratis</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-logo-wrapper">
                <div class="hero-logo-card">
                    <img src="<?php echo htmlspecialchars(url_path('assets/logo.png')); ?>" alt="JagoNugas" class="hero-logo-img">
                </div>
            </div>
        </div>
        <div class="container">
            <div class="hero-stats">
                <div class="stat">
                    <div class="stat-number">5,000+</div>
                    <div class="stat-label">Mahasiswa Aktif</div>
                </div>
                <div class="stat">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Mentor Terverifikasi</div>
                </div>
                <div class="stat">
                    <div class="stat-number">4.9/5</div>
                    <div class="stat-label">Rating Pengguna</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-corporate">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Fitur Unggulan</span>
                <h2 class="section-title">Semua yang Lo Butuhkan untuk Sukses Kuliah</h2>
                <p class="section-subtitle">Platform lengkap dengan teknologi terkini untuk pengalaman belajar terbaik</p>
            </div>
            <div class="features-grid-3">
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate"><i class="bi bi-chat-dots-fill"></i></div>
                    <h3>Forum Diskusi Real-Time</h3>
                    <p>Tanya jawab langsung dengan sesama mahasiswa dan mentor yang lagi online. Response cepat, solusi instan.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate"><i class="bi bi-mortarboard-fill"></i></div>
                    <h3>Mentor Terverifikasi</h3>
                    <p>Semua mentor sudah melalui proses verifikasi KHS. Dijamin berkualitas.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate"><i class="bi bi-gem"></i></div>
                    <h3>Sistem Gem Fleksibel</h3>
                    <p>Top-up gem sesuai budget, pakai kapan aja. Tidak ada subscription bulanan yang membebani.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate"><i class="bi bi-star-fill"></i></div>
                    <h3>Rating & Review Transparan</h3>
                    <p>Lihat track record mentor dari review real mahasiswa. Pilih yang paling cocok dengan lo.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate"><i class="bi bi-chat-left-text-fill"></i></div>
                    <h3>History Chat Mentor</h3>
                    <p>Liat ulang semua percakapan lo dengan mentor, lengkap dengan waktu dan topik yang pernah dibahas.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate"><i class="bi bi-shield-lock-fill"></i></div>
                    <h3>Pembayaran Aman</h3>
                    <p>Tersedia berbagai metode pembayaran. Data lo 100% aman & terenkripsi.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works-corporate">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Cara Kerja</span>
                <h2 class="section-title">Mulai Dalam 3 Langkah Mudah</h2>
                <p class="section-subtitle">Proses simpel tanpa ribet, langsung bisa belajar</p>
            </div>
            <div class="steps-timeline">
                <div class="step-corporate">
                    <div class="step-number-corporate">01</div>
                    <h3>Daftar & Isi Profil</h3>
                    <p>Buat akun gratis dalam 1 menit. Lengkapi profil dengan program studi dan semester lo.</p>
                </div>
                <div class="step-corporate">
                    <div class="step-number-corporate">02</div>
                    <h3>Pilih Mentor & Konsultasi</h3>
                    <p>Browse mentor berdasarkan rating dan spesialisasi. Booking sesuai kebutuhan lo.</p>
                </div>
                <div class="step-corporate">
                    <div class="step-number-corporate">03</div>
                    <h3>Bayar & Mulai Belajar</h3>
                    <p>Top-up gem, bayar session, dan langsung connect dengan mentor secara online.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="pricing-corporate">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Paket Gem</span>
                <h2 class="section-title">Harga Transparan, Tanpa Biaya Tersembunyi</h2>
                <p class="section-subtitle">Pilih paket yang sesuai kebutuhan belajar lo</p>
            </div>
            <div class="pricing-grid">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Basic</h3>
                        <div class="price">Rp 10.000</div>
                        <div class="gem-amount">4,500 Gem</div>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="bi bi-check-lg"></i> Akses Forum Diskusi</li>
                        <li><i class="bi bi-check-lg"></i> 3-4 Session Konsultasi</li>
                        <li><i class="bi bi-check-lg"></i> Chat dengan Mentor</li>
                        <li><i class="bi bi-check-lg"></i> Upload File (JPG/PDF)</li>
                    </ul>
                    <a href="<?php echo htmlspecialchars($isLoggedIn ? $dashboardUrl : url_path('register.php')); ?>" class="btn btn-outline btn-full">
                        <?php echo $isLoggedIn ? 'Mulai Sekarang' : 'Pilih Paket'; ?>
                    </a>
                </div>
                <div class="pricing-card featured">
                    <div class="popular-badge">⭐ Paling Populer</div>
                    <div class="pricing-header">
                        <h3>Pro</h3>
                        <div class="price">Rp 25.000</div>
                        <div class="gem-amount">12,500 Gem</div>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="bi bi-check-lg"></i> Semua Fitur Basic</li>
                        <li><i class="bi bi-check-lg"></i> 10-12 Session Konsultasi</li>
                        <li><i class="bi bi-check-lg"></i> Priority Support</li>
                        <li><i class="bi bi-check-lg"></i> Bonus 500 Gem</li>
                    </ul>
                    <a href="<?php echo htmlspecialchars($isLoggedIn ? $dashboardUrl : url_path('register.php')); ?>" class="btn btn-primary btn-full">
                        <?php echo $isLoggedIn ? 'Mulai Sekarang' : 'Pilih Paket'; ?>
                    </a>
                </div>
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Plus</h3>
                        <div class="price">Rp 50.000</div>
                        <div class="gem-amount">27,000 Gem</div>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="bi bi-check-lg"></i> Semua Fitur Pro</li>
                        <li><i class="bi bi-check-lg"></i> 25+ Session Konsultasi</li>
                        <li><i class="bi bi-check-lg"></i> Dedicated Support</li>
                        <li><i class="bi bi-check-lg"></i> Bonus 2,000 Gem</li>
                    </ul>
                    <a href="<?php echo htmlspecialchars($isLoggedIn ? $dashboardUrl : url_path('register.php')); ?>" class="btn btn-outline btn-full">
                        <?php echo $isLoggedIn ? 'Mulai Sekarang' : 'Pilih Paket'; ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="testimonials-corporate">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Testimoni</span>
                <h2 class="section-title">Apa Kata Mahasiswa Lain?</h2>
                <p class="section-subtitle">Ribuan mahasiswa sudah terbantu dengan JagoNugas</p>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p>"Platform ini lifesaver banget! Gara-gara JagoNugas, gw berhasil naikin IPK dari 2.8 ke 3.5 dalam 2 semester."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <img src="<?php echo htmlspecialchars(url_path('assets/profil-raihan.jpeg')); ?>" alt="Foto Profil Raihan">
                        </div>
                        <div class="author-meta">
                            <div class="author-name">Mohammad Raihan Riski</div>
                            <div class="author-info">Sistem Informasi, Semester 5</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p>"Mentornya sabar dan jelasinnya detail. Harga juga jauh lebih murah dari les privat biasa. Recommended!"</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <img src="<?php echo htmlspecialchars(url_path('assets/profil-rizky.jpg')); ?>" alt="Foto Profil Muhammad Rizky">
                        </div>
                        <div class="author-meta">
                            <div class="author-name">Muhammad Rizky Ardian</div>
                            <div class="author-info">Sistem Informasi, Semester 5</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p>"Forum diskusinya helpful banget. Gw bisa dapet jawaban cepet tanpa perlu booking mentor dulu. Love it!"</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <img src="<?php echo htmlspecialchars(url_path('assets/profil-fikri.jpeg')); ?>" alt="Foto Profil Fikri">
                        </div>
                        <div class="author-meta">
                            <div class="author-name">Mohamad Fikri Isfahani</div>
                            <div class="author-info">Sistem Informasi, Semester 5</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-corporate">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>JagoNugas</h3>
                    <p>Platform yang dibutuhkan untuk menunjang perkuliahan semua mahasiswa untuk farming IPK.</p>
                </div>
                <div class="footer-links">
                    <div class="footer-column">
                        <h4>Produk</h4>
                        <a href="#features">Fitur</a>
                        <a href="#pricing">Harga</a>
                        <a href="<?php echo htmlspecialchars(url_path('mentor-register.php')); ?>">Jadi Mentor</a>
                    </div>
                    <div class="footer-column">
                        <h4>Lainnya</h4>
                        <a href="#">Tentang Kami</a>
                        <a href="#">Blog</a>
                        <a href="#">Kerja Sama</a>
                    </div>
                    <div class="footer-column">
                        <h4>Info Lebih Lanjut</h4>
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">FAQ</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 JagoNugas. All rights reserved.</p>
                <div class="social-links">
                    <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" aria-label="TikTok"><i class="bi bi-tiktok"></i></a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
