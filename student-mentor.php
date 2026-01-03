<?php
// student-mentor.php v3.4 - Add Mentor Bio Display


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}


if (!$pdo) {
    die('Database connection failed. Please contact administrator.');
}


$student_id = $_SESSION['user_id'];


$stmt = $pdo->prepare("SELECT gems FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$gem_balance = $student['gems'] ?? 0;


// Helper: Get Avatar URL (same as student-settings.php)
function get_avatar_url($avatar, $base = '') {
    if (empty($avatar)) return '';
    if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
    return $base . '/' . ltrim($avatar, '/');
}


$search = $_GET['search'] ?? '';
$specialization = $_GET['specialization'] ?? '';
$min_rating = $_GET['min_rating'] ?? '0';


// v3.4: Added bio column to SELECT
$query = "SELECT id, name, email, program_studi, specialization, hourly_rate, avatar, semester, bio,
          CASE 
              WHEN review_count > 0 THEN ROUND(total_rating / review_count, 1)
              ELSE 0 
          END as avg_rating,
          review_count
          FROM users 
          WHERE role = 'mentor' AND is_verified = 1";


$params = [];


if ($search) {
    $query .= " AND (name LIKE ? OR specialization LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}


if ($specialization) {
    $query .= " AND specialization LIKE ?";
    $params[] = "%$specialization%";
}


$query .= " HAVING avg_rating >= ?";
$params[] = (float)$min_rating;
$query .= " ORDER BY avg_rating DESC, review_count DESC";


$stmt = $pdo->prepare($query);
$stmt->execute($params);
$mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);


$spec_stmt = $pdo->query("SELECT DISTINCT specialization FROM users WHERE role = 'mentor' AND is_verified = 1 AND specialization IS NOT NULL");
$specializations = $spec_stmt ? $spec_stmt->fetchAll(PDO::FETCH_COLUMN) : [];


// FIX: Use string keys to avoid PHP 8.1+ deprecated warning
$ratingOptions = [
    '0' => 'Semua',
    '4' => '4.0+',
    '4.5' => '4.5+'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Mentor - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a202c;
            background: #f8fafc;
            min-height: 100vh;
        }


        /* ===== BUTTONS ===== */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 2px solid transparent;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.35);
        }
        .btn-outline {
            border: 2px solid #e2e8f0;
            color: #64748b;
            background: white;
        }
        .btn-outline:hover {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .btn-full { width: 100%; }
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }


        /* ===== PAGE WRAPPER ===== */
        .page-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 24px 60px;
        }


        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 28px;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .page-header p {
            color: #64748b;
            font-size: 0.95rem;
        }


        /* ===== FILTER SECTION ===== */
        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 28px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr auto;
            gap: 16px;
            align-items: end;
        }
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-item label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-item label i {
            color: #667eea;
        }
        .filter-item input {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #f8fafc;
            color: #1e293b;
        }
        .filter-item input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }
        .filter-item input::placeholder {
            color: #94a3b8;
        }


        /* ===== CUSTOM SELECT ===== */
        .custom-select {
            position: relative;
            width: 100%;
            font-size: 0.9rem;
            user-select: none;
        }
        .select-selected {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .select-selected:hover {
            border-color: #cbd5e1;
            background: #ffffff;
        }
        .custom-select.active .select-selected {
            border-color: #667eea;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12);
        }
        .select-text {
            color: #64748b;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .select-text.has-value {
            color: #1e293b;
            font-weight: 600;
        }
        .select-arrow {
            color: #94a3b8;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        .custom-select.active .select-arrow {
            transform: rotate(180deg);
            color: #667eea;
        }
        .select-items {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
            max-height: 280px;
            overflow-y: auto;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.25s ease;
            z-index: 1000;
            padding: 8px;
        }
        .custom-select.active .select-items {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .select-item {
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
            color: #475569;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .select-item:hover {
            background: linear-gradient(135deg, #f0f5ff 0%, #e8f0fe 100%);
            color: #667eea;
        }
        .select-item.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
        }
        .select-item i {
            font-size: 1rem;
        }


        /* ===== MENTOR GRID ===== */
        .mentor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }


        /* ===== MENTOR CARD ===== */
        .mentor-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
        }
        .mentor-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.15);
            border-color: rgba(102, 126, 234, 0.2);
        }


        /* Avatar */
        .mentor-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.25);
        }
        .mentor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        /* Name */
        .mentor-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }


        /* Rating Badge */
        .mentor-rating {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 16px;
        }
        .mentor-rating i {
            color: #f59e0b;
        }
        .mentor-rating .review-count {
            color: #b45309;
            font-weight: 500;
        }


        /* v3.4: Mentor Bio */
        .mentor-bio {
            width: 100%;
            padding: 12px 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            margin-bottom: 16px;
            text-align: left;
        }
        .mentor-bio-header {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }
        .mentor-bio-header i {
            color: #667eea;
            font-size: 0.9rem;
        }
        .mentor-bio-header span {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .mentor-bio-text {
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .mentor-bio.empty {
            background: #f8fafc;
            border: 1px dashed #e2e8f0;
        }
        .mentor-bio.empty .mentor-bio-text {
            color: #94a3b8;
            font-style: italic;
            text-align: center;
        }


        /* Tags */
        .mentor-tags {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
            margin-bottom: 16px;
        }
        .mentor-tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .tag-program {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
        }
        .tag-program i { color: #3b82f6; }
        .tag-semester {
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            color: #6b21a8;
            font-weight: 600;
        }
        .tag-semester i { color: #a855f7; }
        .tag-specialization {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #166534;
        }
        .tag-specialization i { color: #22c55e; }


        /* Price */
        .mentor-price {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            margin-bottom: 20px;
            width: 100%;
        }
        .mentor-price i {
            color: #8b5cf6;
            font-size: 1.1rem;
        }
        .mentor-price span {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }


        /* ===== EMPTY STATE ===== */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 24px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .empty-state .empty-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 16px;
            display: block;
        }
        .empty-state h2 {
            font-size: 1.35rem;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .empty-state p {
            color: #64748b;
            margin-bottom: 24px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        /* Reset Button - Compact & Professional */
        .reset-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .reset-btn:hover {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.05);
            transform: translateY(-1px);
        }
        .reset-btn i {
            font-size: 1rem;
        }


        /* ===== STATS BAR ===== */
        .stats-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 0 4px;
        }
        .stats-bar .result-count {
            font-size: 0.9rem;
            color: #64748b;
        }
        .stats-bar .result-count strong {
            color: #1e293b;
        }


        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .filter-row {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 20px 16px 40px;
            }
            .filter-row {
                grid-template-columns: 1fr;
            }
            .filter-card {
                padding: 20px;
            }
            .mentor-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'student-navbar.php'; ?>


    <main class="page-wrapper">
        <div class="page-header">
            <h1>Cari Mentor</h1>
            <p>Temukan mentor terbaik untuk membantu tugas dan proyekmu</p>
        </div>


        <!-- Filter Section -->
        <div class="filter-card">
            <form method="GET" action="" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="search"><i class="bi bi-search"></i> Cari Mentor</label>
                        <input type="text" id="search" name="search" placeholder="Nama atau spesialisasi..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>


                    <div class="filter-item">
                        <label><i class="bi bi-lightbulb-fill"></i> Spesialisasi</label>
                        <div class="custom-select" data-name="specialization">
                            <div class="select-selected">
                                <span class="select-text <?php echo !empty($specialization) ? 'has-value' : ''; ?>">
                                    <?php echo !empty($specialization) ? htmlspecialchars($specialization) : 'Semua Spesialisasi'; ?>
                                </span>
                                <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </div>
                            <div class="select-items">
                                <div class="select-item <?php echo empty($specialization) ? 'selected' : ''; ?>" data-value="">
                                    <i class="bi bi-grid-fill"></i> Semua Spesialisasi
                                </div>
                                <?php foreach ($specializations as $spec): ?>
                                    <div class="select-item <?php echo $spec === $specialization ? 'selected' : ''; ?>" data-value="<?php echo htmlspecialchars($spec); ?>">
                                        <i class="bi bi-lightbulb"></i> <?php echo htmlspecialchars($spec); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="specialization" value="<?php echo htmlspecialchars($specialization); ?>">
                        </div>
                    </div>


                    <div class="filter-item">
                        <label><i class="bi bi-star-fill"></i> Rating</label>
                        <div class="custom-select" data-name="min_rating">
                            <div class="select-selected">
                                <span class="select-text <?php echo $min_rating !== '0' ? 'has-value' : ''; ?>">
                                    <?php echo $ratingOptions[$min_rating] ?? 'Semua'; ?>
                                </span>
                                <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </div>
                            <div class="select-items">
                                <?php foreach ($ratingOptions as $value => $label): ?>
                                    <div class="select-item <?php echo $min_rating === (string)$value ? 'selected' : ''; ?>" data-value="<?php echo $value; ?>">
                                        <i class="bi bi-star<?php echo $value !== '0' ? '-fill' : ''; ?>"></i> <?php echo $label; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="min_rating" value="<?php echo htmlspecialchars($min_rating); ?>">
                        </div>
                    </div>


                    <div class="filter-item">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Cari
                        </button>
                    </div>
                </div>
            </form>
        </div>


        <!-- Stats Bar -->
        <?php if (!empty($mentors)): ?>
        <div class="stats-bar">
            <span class="result-count">Menampilkan <strong><?php echo count($mentors); ?></strong> mentor</span>
            <?php if ($search || $specialization || $min_rating !== '0'): ?>
                <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="reset-btn">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- Mentor Grid -->
        <div class="mentor-grid">
            <?php if (empty($mentors)): ?>
                <div class="empty-state">
                    <i class="bi bi-person-x empty-icon"></i>
                    <h2>Belum Ada Mentor Tersedia</h2>
                    <p>Mentor sedang dalam proses verifikasi atau tidak ada yang sesuai dengan filter pencarian.</p>
                    <?php if ($search || $specialization || $min_rating !== '0'): ?>
                        <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="reset-btn">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($mentors as $mentor): ?>
                    <?php 
                    // FIX v3.3: Use helper function for Google avatar support
                    $mentorAvatarUrl = get_avatar_url($mentor['avatar'] ?? '', BASE_PATH);
                    // v3.4: Get mentor bio
                    $mentorBio = trim($mentor['bio'] ?? '');
                    ?>
                    <div class="mentor-card">
                        <div class="mentor-avatar">
                            <?php if (!empty($mentorAvatarUrl)): ?>
                                <img src="<?php echo htmlspecialchars($mentorAvatarUrl); ?>" alt="<?php echo htmlspecialchars($mentor['name']); ?>" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>


                        <h3><?php echo htmlspecialchars($mentor['name']); ?></h3>


                        <div class="mentor-rating">
                            <i class="bi bi-star-fill"></i>
                            <?php echo number_format((float)$mentor['avg_rating'], 1); ?>
                            <span class="review-count">(<?php echo (int)$mentor['review_count']; ?> review)</span>
                        </div>


                        <!-- v3.4: Bio Section -->
                        <div class="mentor-bio <?php echo empty($mentorBio) ? 'empty' : ''; ?>">
                            <div class="mentor-bio-header">
                                <i class="bi bi-quote"></i>
                                <span>Tentang Mentor</span>
                            </div>
                            <p class="mentor-bio-text">
                                <?php echo !empty($mentorBio) ? htmlspecialchars($mentorBio) : 'Belum ada bio'; ?>
                            </p>
                        </div>


                        <div class="mentor-tags">
                            <?php if (!empty($mentor['semester'])): ?>
                                <span class="mentor-tag tag-semester">
                                    <i class="bi bi-mortarboard-fill"></i>
                                    Semester <?php echo (int)$mentor['semester']; ?>
                                </span>
                            <?php endif; ?>
                            <span class="mentor-tag tag-program">
                                <i class="bi bi-building"></i>
                                <?php echo htmlspecialchars($mentor['program_studi']); ?>
                            </span>
                            <?php if (!empty($mentor['specialization'])): ?>
                                <span class="mentor-tag tag-specialization">
                                    <i class="bi bi-lightbulb-fill"></i>
                                    <?php echo htmlspecialchars($mentor['specialization']); ?>
                                </span>
                            <?php endif; ?>
                        </div>


                        <div class="mentor-price">
                            <i class="bi bi-gem"></i>
                            <span><?php echo number_format((int)$mentor['hourly_rate'], 0, ',', '.'); ?> Gem/sesi</span>
                        </div>


                        <a href="<?php echo BASE_PATH; ?>/book-session.php?mentor_id=<?php echo $mentor['id']; ?>" class="btn btn-primary btn-full">
                            <i class="bi bi-calendar-check"></i> Book Session
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const customSelects = document.querySelectorAll('.custom-select');
        
        customSelects.forEach(select => {
            const selected = select.querySelector('.select-selected');
            const hiddenInput = select.querySelector('input[type="hidden"]');
            const selectText = select.querySelector('.select-text');


            selected.addEventListener('click', function(e) {
                e.stopPropagation();
                customSelects.forEach(s => { 
                    if (s !== select) s.classList.remove('active'); 
                });
                select.classList.toggle('active');
            });


            select.querySelectorAll('.select-item').forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.dataset.value;
                    const text = this.textContent.trim();
                    
                    hiddenInput.value = value;
                    selectText.textContent = text;
                    
                    if (value) {
                        selectText.classList.add('has-value');
                    } else {
                        selectText.classList.remove('has-value');
                    }
                    
                    select.querySelectorAll('.select-item').forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    setTimeout(() => select.classList.remove('active'), 150);
                });
            });
        });


        document.addEventListener('click', () => {
            customSelects.forEach(s => s.classList.remove('active'));
        });
    });
    </script>
</body>
</html>
