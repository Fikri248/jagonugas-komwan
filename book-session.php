<?php
// book-session.php v4.0 - Session Timer Integration + Modal Konfirmasi
// Auto-create conversation + Duration for chat timer


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}


try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed. Please contact administrator.');
}


$student_id = $_SESSION['user_id'];
$mentor_id  = (int)($_GET['mentor_id'] ?? 0);


// Get student gems
$stmt = $pdo->prepare("SELECT gems FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$student_gems = $student['gems'] ?? 0;


// Get mentor details
$stmt = $pdo->prepare("
    SELECT id, name, email, program_studi, specialization, avatar,
           CASE 
               WHEN review_count > 0 THEN ROUND(total_rating / review_count, 1)
               ELSE 0 
           END as avg_rating,
           review_count
    FROM users 
    WHERE id = ? AND role = 'mentor' AND is_verified = 1
");
$stmt->execute([$mentor_id]);
$mentor = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$mentor) {
    header('Location: ' . BASE_PATH . '/student-mentor.php');
    exit;
}


// v4.0: Package configuration with timer info
$packages = [
    15 => ['gems' => 1000, 'name' => 'Tugas Biasa',     'desc' => 'Konsultasi singkat untuk tugas biasa',           'icon' => 'bi-lightning-charge-fill', 'color' => '#3b82f6'],
    30 => ['gems' => 2500, 'name' => 'Tugas Praktikum', 'desc' => 'Bimbingan untuk tugas praktikum',                'icon' => 'bi-journal-code',          'color' => '#8b5cf6'],
    60 => ['gems' => 5000, 'name' => 'Tugas Ngoding',   'desc' => 'Sesi lengkap untuk tugas coding/programming',    'icon' => 'bi-code-slash',            'color' => '#10b981', 'popular' => true],
    90 => ['gems' => 7500, 'name' => 'Tugas Besar',     'desc' => 'Sesi intensif untuk tugas besar/projek akhir',   'icon' => 'bi-rocket-takeoff-fill',   'color' => '#f59e0b'],
];


$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $duration = (int)($_POST['duration'] ?? 0);
    $notes    = trim($_POST['notes'] ?? '');


    if (!isset($packages[$duration])) {
        $error = 'Durasi tidak valid!';
    } else {
        $price = $packages[$duration]['gems'];
        if ($student_gems < $price) {
            $error = 'Saldo gems tidak cukup! Anda memerlukan ' . number_format($price) . ' gems.';
        } else {
            try {
                $pdo->beginTransaction();


                // v4.0: Insert session with duration (used for chat timer)
                $stmt = $pdo->prepare("
                    INSERT INTO sessions (student_id, mentor_id, duration, price, notes, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$student_id, $mentor_id, $duration, $price, $notes]);
                $session_id = $pdo->lastInsertId();


                // Auto create conversation for this session
                $stmt = $pdo->prepare("
                    INSERT INTO conversations (mentor_id, student_id, session_id, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$mentor_id, $student_id, $session_id]);


                // Deduct gems
                $stmt = $pdo->prepare("UPDATE users SET gems = gems - ? WHERE id = ?");
                $stmt->execute([$price, $student_id]);


                $pdo->commit();


                $_SESSION['success'] = 'Booking berhasil! Sesi ' . $duration . ' menit akan dimulai setelah mentor menerima.';
                header('Location: ' . BASE_PATH . '/student-sessions.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan saat booking. Silakan coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Session - <?php echo htmlspecialchars($mentor['name']); ?> | JagoNugas</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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


        /* ===== BOOKING PAGE ===== */
        .booking-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 32px 24px 60px;
        }
        .booking-header {
            margin-bottom: 24px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 0;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: #667eea;
        }
        .booking-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin-top: 12px;
        }
        .booking-header p {
            color: #64748b;
            font-size: 0.95rem;
        }


        /* Alert */
        .alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-info {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }


        /* Mentor Card */
        .mentor-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 24px;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .mentor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }
        .mentor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .mentor-details {
            flex: 1;
        }
        .mentor-details h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .mentor-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
        }
        .mentor-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: #64748b;
        }
        .mentor-meta i {
            color: #667eea;
        }
        .mentor-rating {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .mentor-rating i {
            color: #fbbf24;
        }
        .mentor-rating strong {
            color: #1e293b;
        }


        /* v4.0: Timer Info Banner */
        .timer-info-banner {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #86efac;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .timer-info-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .timer-info-content h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 2px;
        }
        .timer-info-content p {
            font-size: 0.85rem;
            color: #047857;
            margin: 0;
        }


        /* Package Section */
        .package-section {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 24px;
        }
        .package-section h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .package-section h3 i {
            color: #667eea;
        }
        .package-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .package-item {
            position: relative;
            cursor: pointer;
        }
        .package-item input {
            display: none;
        }
        .package-card {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            background: white;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
        }
        .package-item input:checked + .package-card {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        .package-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }
        .package-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .package-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .package-duration {
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .package-duration i {
            font-size: 0.75rem;
            color: #10b981;
        }
        .package-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .package-desc {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        .package-price {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 1.25rem;
            font-weight: 700;
            color: #667eea;
        }
        .package-price i {
            font-size: 1rem;
        }
        .package-badge {
            position: absolute;
            top: -10px;
            right: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .check-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .package-item input:checked + .package-card .check-indicator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }


        /* v4.0: Timer Preview in Package */
        .package-timer-preview {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
            font-size: 0.8rem;
            color: #64748b;
        }
        .package-timer-preview i {
            color: #10b981;
        }


        /* Gems Balance */
        .gems-balance {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            margin-top: 20px;
        }
        .gems-balance-left {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            color: #64748b;
        }
        .gems-balance-left i {
            color: #8b5cf6;
            font-size: 1.25rem;
        }
        .gems-balance-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }
        .gems-balance-value.insufficient {
            color: #dc2626;
        }
        .topup-link {
            font-size: 0.9rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .topup-link:hover {
            text-decoration: underline;
        }


        /* Notes Section */
        .notes-section {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 24px;
        }
        .notes-section label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .notes-section label i {
            color: #667eea;
        }
        .notes-section label span {
            font-weight: 400;
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .notes-textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .notes-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }
        .notes-textarea::placeholder {
            color: #94a3b8;
        }


        /* v4.0: Summary Section */
        .summary-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            display: none;
        }
        .summary-section.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        .summary-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-title i {
            color: #667eea;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }
        .summary-row:not(:last-child) {
            border-bottom: 1px dashed #e2e8f0;
        }
        .summary-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        .summary-value {
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .summary-value.price {
            color: #667eea;
            font-size: 1.1rem;
        }
        .summary-value i {
            font-size: 0.9rem;
        }
        .summary-timer {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #16a34a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }


        /* Actions */
        .booking-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }


        /* ===== MODAL KONFIRMASI ===== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-container {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .modal-overlay.active .modal-container {
            transform: scale(1) translateY(0);
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px;
            text-align: center;
            position: relative;
        }
        .modal-icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }
        .modal-icon i {
            font-size: 2rem;
            color: white;
        }
        .modal-header h3 {
            color: white;
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0;
        }
        .modal-header p {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            margin: 4px 0 0;
        }
        .modal-body {
            padding: 24px;
        }
        .modal-detail-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .modal-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
        }
        .modal-detail-row:not(:last-child) {
            border-bottom: 1px dashed #e2e8f0;
        }
        .modal-detail-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 0.9rem;
        }
        .modal-detail-label i {
            color: #667eea;
            font-size: 1rem;
        }
        .modal-detail-value {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.95rem;
        }
        .modal-detail-value.highlight {
            color: #667eea;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .modal-detail-value.timer {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #16a34a;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .modal-mentor-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 16px;
        }
        .modal-mentor-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        .modal-mentor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .modal-mentor-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 1rem;
        }
        .modal-mentor-prodi {
            font-size: 0.85rem;
            color: #64748b;
        }
        .modal-warning {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fcd34d;
            border-radius: 12px;
            font-size: 0.85rem;
            color: #92400e;
        }
        .modal-warning i {
            color: #f59e0b;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .modal-footer {
            display: flex;
            gap: 12px;
            padding: 0 24px 24px;
        }
        .modal-btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .modal-btn-cancel {
            background: #f1f5f9;
            color: #64748b;
        }
        .modal-btn-cancel:hover {
            background: #e2e8f0;
            color: #475569;
        }
        .modal-btn-confirm {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .modal-btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.35);
        }
        .modal-btn-confirm:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .modal-btn-confirm .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }


        /* Responsive */
        @media (max-width: 768px) {
            .booking-wrapper {
                padding: 20px 16px 40px;
            }
            .package-grid {
                grid-template-columns: 1fr;
            }
            .mentor-card {
                flex-direction: column;
                text-align: center;
            }
            .mentor-meta {
                justify-content: center;
            }
            .gems-balance {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
            .timer-info-banner {
                flex-direction: column;
                text-align: center;
            }
            .modal-container {
                width: 95%;
                margin: 16px;
            }
            .modal-footer {
                flex-direction: column-reverse;
            }
        }
    </style>
</head>
<body>
    <?php include 'student-navbar.php'; ?>


    <main class="booking-wrapper">
        <div class="booking-header">
            <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Kembali ke Daftar Mentor
            </a>
            <h1>Book Sesi Konsultasi</h1>
            <p>Pilih paket yang sesuai dengan kebutuhanmu</p>
        </div>


        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>


        <!-- v4.0: Timer Info Banner -->
        <div class="timer-info-banner">
            <div class="timer-info-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="timer-info-content">
                <h4><i class="bi bi-stopwatch"></i> Sesi dengan Timer Otomatis</h4>
                <p>Waktu chat akan mulai dihitung saat mentor menerima sesi. Timer akan tampil di halaman chat dan memberikan notifikasi 5 menit & 1 menit sebelum berakhir.</p>
            </div>
        </div>


        <!-- Mentor Info -->
        <div class="mentor-card">
            <div class="mentor-avatar">
                <?php if (!empty($mentor['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($mentor['avatar']); ?>" alt="<?php echo htmlspecialchars($mentor['name']); ?>" referrerpolicy="no-referrer">
                <?php else: ?>
                    <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="mentor-details">
                <h2><?php echo htmlspecialchars($mentor['name']); ?></h2>
                <div class="mentor-meta">
                    <span><i class="bi bi-mortarboard-fill"></i> <?php echo htmlspecialchars($mentor['program_studi']); ?></span>
                    <?php if ($mentor['specialization']): ?>
                    <span><i class="bi bi-lightbulb-fill"></i> <?php echo htmlspecialchars($mentor['specialization']); ?></span>
                    <?php endif; ?>
                    <span class="mentor-rating">
                        <i class="bi bi-star-fill"></i>
                        <strong><?php echo $mentor['avg_rating']; ?></strong>
                        (<?php echo $mentor['review_count']; ?> reviews)
                    </span>
                </div>
            </div>
        </div>


        <form method="POST" action="" id="bookingForm">
            <!-- Package Selection -->
            <div class="package-section">
                <h3><i class="bi bi-box-seam"></i> Pilih Paket Konsultasi</h3>
                <div class="package-grid">
                    <?php foreach ($packages as $duration => $pkg): ?>
                    <label class="package-item">
                        <input type="radio" name="duration" value="<?php echo $duration; ?>" data-gems="<?php echo $pkg['gems']; ?>" data-name="<?php echo htmlspecialchars($pkg['name']); ?>" required>
                        <div class="package-card">
                            <?php if (!empty($pkg['popular'])): ?>
                            <span class="package-badge">Populer</span>
                            <?php endif; ?>
                            <span class="check-indicator"><i class="bi bi-check"></i></span>
                            <div class="package-header">
                                <div class="package-icon" style="background: <?php echo $pkg['color']; ?>20; color: <?php echo $pkg['color']; ?>">
                                    <i class="bi <?php echo $pkg['icon']; ?>"></i>
                                </div>
                                <span class="package-duration">
                                    <i class="bi bi-clock-fill"></i>
                                    <?php echo $duration; ?> menit
                                </span>
                            </div>
                            <div class="package-name"><?php echo htmlspecialchars($pkg['name']); ?></div>
                            <div class="package-desc"><?php echo htmlspecialchars($pkg['desc']); ?></div>
                            <div class="package-price">
                                <i class="bi bi-gem"></i>
                                <?php echo number_format($pkg['gems'], 0, ',', '.'); ?>
                            </div>
                            <!-- v4.0: Timer Preview -->
                            <div class="package-timer-preview">
                                <i class="bi bi-stopwatch"></i>
                                Timer chat: <?php echo $duration; ?> menit aktif
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>


                <div class="gems-balance">
                    <div class="gems-balance-left">
                        <i class="bi bi-gem"></i>
                        <span>Saldo Gems Kamu:</span>
                        <span class="gems-balance-value" id="gemsBalance"><?php echo number_format($student_gems, 0, ',', '.'); ?></span>
                    </div>
                    <a href="<?php echo BASE_PATH; ?>/student-gems-purchase.php" class="topup-link">
                        Top Up <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>


            <!-- v4.0: Summary Section -->
            <div class="summary-section" id="summarySection">
                <div class="summary-title">
                    <i class="bi bi-receipt"></i>
                    Ringkasan Booking
                </div>
                <div class="summary-row">
                    <span class="summary-label">Paket Dipilih</span>
                    <span class="summary-value" id="summaryPackage">-</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Durasi Chat</span>
                    <span class="summary-value">
                        <span class="summary-timer" id="summaryDuration">- menit</span>
                    </span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Mentor</span>
                    <span class="summary-value"><?php echo htmlspecialchars($mentor['name']); ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Harga</span>
                    <span class="summary-value price">
                        <i class="bi bi-gem"></i>
                        <span id="summaryPrice">-</span>
                    </span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Sisa Saldo</span>
                    <span class="summary-value" id="summaryRemaining">-</span>
                </div>
            </div>


            <!-- Notes -->
            <div class="notes-section">
                <label for="notes">
                    <i class="bi bi-chat-text"></i>
                    Catatan untuk Mentor
                    <span>(opsional)</span>
                </label>
                <textarea 
                    id="notes" 
                    name="notes" 
                    class="notes-textarea"
                    placeholder="Jelaskan topik yang ingin kamu diskusikan, misalnya: Butuh bantuan untuk tugas struktur data tentang linked list..."
                ></textarea>
            </div>


            <!-- Actions -->
            <div class="booking-actions">
                <button type="button" class="btn btn-primary btn-full" id="showConfirmBtn" disabled>
                    <i class="bi bi-calendar-check"></i>
                    Konfirmasi Booking
                </button>
                <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="btn btn-outline btn-full">
                    <i class="bi bi-arrow-left"></i>
                    Batal
                </a>
            </div>
        </form>
    </main>


    <!-- ===== MODAL KONFIRMASI ===== -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h3>Konfirmasi Booking</h3>
                <p>Periksa detail sesi sebelum melanjutkan</p>
            </div>
            <div class="modal-body">
                <!-- Mentor Info -->
                <div class="modal-mentor-info">
                    <div class="modal-mentor-avatar">
                        <?php if (!empty($mentor['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($mentor['avatar']); ?>" alt="<?php echo htmlspecialchars($mentor['name']); ?>" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="modal-mentor-name"><?php echo htmlspecialchars($mentor['name']); ?></div>
                        <div class="modal-mentor-prodi"><?php echo htmlspecialchars($mentor['program_studi']); ?></div>
                    </div>
                </div>


                <!-- Detail Card -->
                <div class="modal-detail-card">
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">
                            <i class="bi bi-box-seam"></i>
                            Paket
                        </span>
                        <span class="modal-detail-value" id="modalPackage">-</span>
                    </div>
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">
                            <i class="bi bi-stopwatch"></i>
                            Durasi
                        </span>
                        <span class="modal-detail-value timer" id="modalDuration">- menit</span>
                    </div>
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">
                            <i class="bi bi-gem"></i>
                            Harga
                        </span>
                        <span class="modal-detail-value highlight" id="modalPrice">
                            <i class="bi bi-gem"></i>
                            <span>-</span>
                        </span>
                    </div>
                </div>


                <!-- Warning -->
                <div class="modal-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Gems akan langsung dipotong setelah konfirmasi. Sesi dimulai setelah mentor menerima permintaan.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" id="cancelModalBtn">
                    <i class="bi bi-x-lg"></i>
                    Batal
                </button>
                <button type="button" class="modal-btn modal-btn-confirm" id="confirmBookingBtn">
                    <i class="bi bi-check-lg"></i>
                    Ya, Booking Sekarang
                </button>
            </div>
        </div>
    </div>


    <script>
    (function() {
        const userGems = <?php echo (int)$student_gems; ?>;
        const gemsBalanceEl = document.getElementById('gemsBalance');
        const summarySection = document.getElementById('summarySection');
        const summaryPackage = document.getElementById('summaryPackage');
        const summaryDuration = document.getElementById('summaryDuration');
        const summaryPrice = document.getElementById('summaryPrice');
        const summaryRemaining = document.getElementById('summaryRemaining');
        const showConfirmBtn = document.getElementById('showConfirmBtn');
        const packageInputs = document.querySelectorAll('input[name="duration"]');


        // Modal elements
        const confirmModal = document.getElementById('confirmModal');
        const cancelModalBtn = document.getElementById('cancelModalBtn');
        const confirmBookingBtn = document.getElementById('confirmBookingBtn');
        const modalPackage = document.getElementById('modalPackage');
        const modalDuration = document.getElementById('modalDuration');
        const modalPrice = document.getElementById('modalPrice');
        const bookingForm = document.getElementById('bookingForm');


        // Current selection
        let currentSelection = null;


        // Format number
        function formatNumber(num) {
            return num.toLocaleString('id-ID');
        }


        // Update summary on package selection
        packageInputs.forEach(input => {
            input.addEventListener('change', function() {
                const duration = parseInt(this.value);
                const gems = parseInt(this.dataset.gems);
                const name = this.dataset.name;
                const remaining = userGems - gems;


                currentSelection = { duration, gems, name };


                // Show summary
                summarySection.classList.add('show');
                summaryPackage.textContent = name;
                summaryDuration.textContent = duration + ' menit';
                summaryPrice.textContent = formatNumber(gems);
                
                if (remaining >= 0) {
                    summaryRemaining.innerHTML = '<i class="bi bi-gem" style="color:#10b981"></i> ' + formatNumber(remaining);
                    summaryRemaining.style.color = '#10b981';
                    gemsBalanceEl.classList.remove('insufficient');
                    showConfirmBtn.disabled = false;
                } else {
                    summaryRemaining.innerHTML = '<span style="color:#dc2626">Tidak cukup! Kurang ' + formatNumber(Math.abs(remaining)) + '</span>';
                    summaryRemaining.style.color = '#dc2626';
                    gemsBalanceEl.classList.add('insufficient');
                    showConfirmBtn.disabled = true;
                }
            });
        });


        // Show modal
        showConfirmBtn.addEventListener('click', function() {
            if (!currentSelection) {
                alert('Silakan pilih paket konsultasi terlebih dahulu!');
                return;
            }


            if (currentSelection.gems > userGems) {
                alert('Saldo gems tidak cukup!');
                return;
            }


            // Update modal content
            modalPackage.textContent = currentSelection.name;
            modalDuration.textContent = currentSelection.duration + ' menit';
            modalPrice.innerHTML = '<i class="bi bi-gem"></i> ' + formatNumber(currentSelection.gems);


            // Show modal
            confirmModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });


        // Close modal
        function closeModal() {
            confirmModal.classList.remove('active');
            document.body.style.overflow = '';
        }


        cancelModalBtn.addEventListener('click', closeModal);


        // Close on overlay click
        confirmModal.addEventListener('click', function(e) {
            if (e.target === confirmModal) {
                closeModal();
            }
        });


        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && confirmModal.classList.contains('active')) {
                closeModal();
            }
        });


        // Confirm booking
        confirmBookingBtn.addEventListener('click', function() {
            // Disable button and show loading
            confirmBookingBtn.disabled = true;
            confirmBookingBtn.innerHTML = '<span class="spinner"></span> Memproses...';


            // Submit form
            bookingForm.submit();
        });


        // Auto-hide error
        const errorAlert = document.querySelector('.alert-error');
        if (errorAlert) {
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                errorAlert.style.transform = 'translateY(-10px)';
                setTimeout(() => errorAlert.remove(), 300);
            }, 5000);
        }
    })();
    </script>
</body>
</html>
