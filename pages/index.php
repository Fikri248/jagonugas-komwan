<?php
if (!defined('BASE_PATH')) {
  require_once __DIR__ . '/../config.php';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JagoNugas Kelompok 2</title>

    <!-- CSS utama JagoNugas -->
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">

    <!-- Bootstrap Icons (ikon saja, bukan Bootstrap CSS) -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <h1>JagoNugas</h1>
            </div>
            <div class="nav-menu">
                <a href="#features">Fitur</a>
                <a href="#how-it-works">Cara Kerja</a>
                <a href="#pricing">Harga</a>
                <a href="#testimonials">Testimoni</a>
            </div>
            <div class="nav-buttons">
                <a href="<?php echo BASE_PATH; ?>/login" class="btn btn-text">Login</a>
                <a href="<?php echo BASE_PATH; ?>/register" class="btn btn-primary">Register</a>
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
                    Platform tutoring terpercaya untuk mahasiswa Telkom University Surabaya. 
                    Konsultasi langsung dengan mentor berpengalaman, harga terjangkau, hasil terbukti.
                </p>
                <div class="hero-cta">
                    <a href="<?php echo BASE_PATH; ?>/register" class="btn btn-primary btn-large">
                        <span>Mulai Gratis</span>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"/>
                        </svg>
                    </a>
                </div>

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

            <!-- Kanan: logo besar -->
            <div class="hero-logo-wrapper">
                <div class="hero-logo-card">
                    <img src="<?php echo BASE_PATH; ?>/assets/logo.png" alt="JagoNugas" class="hero-logo-img">
                </div>
            </div>
        </div>
    </section>

    <!-- Trusted By Section -->
    <section class="trusted-by">
        <div class="container">
            <p class="trusted-label">Dipercaya oleh dosen</p>
            <div class="universities">
                <span>Mochamad Nizar Palefi Ma'ady</span>
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
                    <div class="feature-icon-corporate">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <h3>Forum Diskusi Real-Time</h3>
                    <p>Tanya jawab langsung dengan sesama mahasiswa dan mentor yang lagi online. Response cepat, solusi instan.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <h3>Mentor Terverifikasi</h3>
                    <p>Semua mentor sudah melalui proses verifikasi KHS. Dijamin berkualitas.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate">
                        <i class="bi bi-gem"></i>
                    </div>
                    <h3>Sistem Gem Fleksibel</h3>
                    <p>Top-up gem sesuai budget, pakai kapan aja. Tidak ada subscription bulanan yang membebani.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate">
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <h3>Rating & Review Transparan</h3>
                    <p>Lihat track record mentor dari review real mahasiswa. Pilih yang paling cocok dengan lo.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate">
                        <i class="bi bi-chat-left-text-fill"></i>
                    </div>
                    <h3>History Chat Mentor</h3>
                    <p>Liat ulang semua percakapan lo dengan mentor, lengkap dengan waktu dan topik yang pernah dibahas.</p>
                </div>
                <div class="feature-card-corporate">
                    <div class="feature-icon-corporate">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
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
            </div>
            <div class="pricing-grid">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Basic</h3>
                        <div class="price">Rp 10.000</div>
                        <div class="gem-amount">4,500 Gem</div>
                    </div>
                    <ul class="pricing-features">
                        <li>✓ Akses Forum Diskusi</li>
                        <li>✓ 3-4 Session Konsultasi</li>
                        <li>✓ Chat dengan Mentor</li>
                        <li>✓ Upload File (JPG/PDF)</li>
                    </ul>
                    <a href="<?php echo BASE_PATH; ?>/register" class="btn btn-outline btn-full">Pilih Paket</a>
                </div>
                <div class="pricing-card featured">
                    <div class="popular-badge">Paling Populer</div>
                    <div class="pricing-header">
                        <h3>Pro</h3>
                        <div class="price">Rp 25.000</div>
                        <div class="gem-amount">12,500 Gem</div>
                    </div>
                    <ul class="pricing-features">
                        <li>✓ Semua Fitur Basic</li>
                        <li>✓ 10-12 Session Konsultasi</li>
                        <li>✓ Priority Support</li>
                        <li>✓ Bonus 500 Gem</li>
                    </ul>
                    <a href="<?php echo BASE_PATH; ?>/register" class="btn btn-outline btn-full">Pilih Paket</a>
                </div>
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Plus</h3>
                        <div class="price">Rp 50.000</div>
                        <div class="gem-amount">27,000 Gem</div>
                    </div>
                    <ul class="pricing-features">
                        <li>✓ Semua Fitur Pro</li>
                        <li>✓ 25+ Session Konsultasi</li>
                        <li>✓ Dedicated Support</li>
                        <li>✓ Bonus 2,000 Gem</li>
                    </ul>
                    <a href="<?php echo BASE_PATH; ?>/register" class="btn btn-outline btn-full">Pilih Paket</a>
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
            </div>
            <div class="testimonials-grid">
                <!-- Testimoni 1 -->
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <p>"Platform ini lifesaver banget! Gara-gara JagoNugas, gw berhasil naikin IPK dari 2.8 ke 3.5 dalam 2 semester."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <img src="<?php echo BASE_PATH; ?>/assets/profil-raihan.jpeg" alt="Foto Profil Raihan">
                        </div>
                        <div class="author-meta">
                            <div class="author-name">Mohammad Raihan Riski</div>
                            <div class="author-info">Sistem Informasi, Semester 5</div>
                        </div>
                    </div>
                </div>

                <!-- Testimoni 2 -->
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <p>"Mentornya sabar dan jelasinnya detail. Harga juga jauh lebih murah dari les privat biasa. Recommended!"</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <img src="<?php echo BASE_PATH; ?>/assets/profil-rizky.jpg" alt="Foto Profil Muhammad Rizky">
                        </div>
                        <div class="author-meta">
                            <div class="author-name">Muhammad Rizky Ardian</div>
                            <div class="author-info">Sistem Informasi, Semester 5</div>
                        </div>
                    </div>
                </div>

                <!-- Testimoni 3 -->
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <p>"Forum diskusinya helpful banget. Gw bisa dapet jawaban cepet tanpa perlu booking mentor dulu. Love it!"</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <img src="<?php echo BASE_PATH; ?>/assets/profil-fikri.jpeg" alt="Foto Profil Fikri">
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
                        <a href="#">Jadi Mentor</a>
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
                    <a href="#">Instagram</a>
                    <a href="#">Twitter</a>
                    <a href="#">Tiktok</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
