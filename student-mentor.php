<?php
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

$search = $_GET['search'] ?? '';
$specialization = $_GET['specialization'] ?? '';
$min_rating = $_GET['min_rating'] ?? 0;

$query = "SELECT id, name, email, program_studi, specialization, hourly_rate, 
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
$params[] = $min_rating;
$query .= " ORDER BY avg_rating DESC, review_count DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spec_stmt = $pdo->query("SELECT DISTINCT specialization FROM users WHERE role = 'mentor' AND is_verified = 1 AND specialization IS NOT NULL");
$specializations = $spec_stmt ? $spec_stmt->fetchAll(PDO::FETCH_COLUMN) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Mentor - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include 'student-navbar.php'; ?>

    <div class="mentor-catalog">
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="search"><i class="bi bi-search"></i> Cari Mentor</label>
                        <input type="text" id="search" name="search" placeholder="Nama atau spesialisasi..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-item">
                        <label for="specialization"><i class="bi bi-lightbulb-fill"></i> Spesialisasi</label>
                        <select id="specialization" name="specialization">
                            <option value="">Semua Spesialisasi</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $spec === $specialization ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="min_rating"><i class="bi bi-star-fill"></i> Rating Minimum</label>
                        <select id="min_rating" name="min_rating">
                            <option value="0" <?php echo $min_rating == 0 ? 'selected' : ''; ?>>Semua Rating</option>
                            <option value="4" <?php echo $min_rating == 4 ? 'selected' : ''; ?>>4+ ⭐</option>
                            <option value="4.5" <?php echo $min_rating == 4.5 ? 'selected' : ''; ?>>4.5+ ⭐</option>
                        </select>
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

        <!-- Mentor Grid -->
        <div class="mentor-grid">
            <?php if (empty($mentors)): ?>
                <div class="empty-state">
                    <i class="bi bi-person-x"></i>
                    <h2>Belum Ada Mentor Tersedia</h2>
                    <p>Mentor sedang dalam proses verifikasi atau tidak ada yang sesuai dengan filter pencarian.</p>
                    <?php if ($search || $specialization || $min_rating > 0): ?>
                        <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="btn btn-outline">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($mentors as $mentor): ?>
                    <div class="mentor-card">
                        <div class="mentor-header">
                            <div class="mentor-avatar">
                                <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                            </div>
                            <div class="mentor-info">
                                <h3><?php echo htmlspecialchars($mentor['name']); ?></h3>
                                <span class="mentor-rating">
                                    <i class="bi bi-star-fill"></i>
                                    <?php echo $mentor['avg_rating']; ?> (<?php echo $mentor['review_count']; ?>)
                                </span>
                            </div>
                        </div>
                        
                        <div class="mentor-details">
                            <span class="mentor-tag">
                                <i class="bi bi-book-fill"></i> 
                                <?php echo htmlspecialchars($mentor['program_studi']); ?>
                            </span>
                            <?php if ($mentor['specialization']): ?>
                                <span class="mentor-tag">
                                    <i class="bi bi-lightbulb-fill"></i> 
                                    <?php echo htmlspecialchars($mentor['specialization']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mentor-price">
                            <i class="bi bi-gem"></i>
                            <span><?php echo number_format($mentor['hourly_rate']); ?> Gem/sesi</span>
                        </div>
                        
                        <a href="<?php echo BASE_PATH; ?>/book-session.php?mentor_id=<?php echo $mentor['id']; ?>" class="btn btn-primary btn-full">
                            <i class="bi bi-calendar-check-fill"></i> Book Session
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
