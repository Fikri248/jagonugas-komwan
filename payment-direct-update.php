<?php
// payment-direct-update.php - FIXED VERSION
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'NotificationHelper.php';

header('Content-Type: application/json');

if (ob_get_level()) ob_end_clean();

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;

    if (!$order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM gem_transactions WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    if ($transaction['transaction_status'] !== 'pending') {
        echo json_encode([
            'success' => true,
            'already_processed' => true,
            'status' => $transaction['transaction_status'],
            'total_gems' => (int)$transaction['gems'],
            'message' => 'Transaction already processed'
        ]);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Update transaction status
    $stmt = $pdo->prepare("
        UPDATE gem_transactions 
        SET transaction_status = 'settlement',
            paid_at = NOW(),
            updated_at = NOW()
        WHERE order_id = ?
    ");
    $stmt->execute([$order_id]);
    
    // Add gems to user
    $stmt = $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?");
    $stmt->execute([$transaction['gems'], $_SESSION['user_id']]);
    
    // Get updated gems count
    $stmt = $pdo->prepare("SELECT gems FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Create notification (FIXED)
    try {
        $notifHelper = new NotificationHelper($pdo);
        $notifHelper->create(
            $_SESSION['user_id'],
            'gem_purchase',
            "Selamat! Kamu berhasil membeli " . number_format($transaction['gems']) . " gems. Saldo gem kamu sekarang: " . number_format($user['gems']) . " gems.",
            $transaction['id'],
            'gem_transaction'
        );
    } catch (Exception $notifError) {
        error_log("Notification error: " . $notifError->getMessage());
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'gems_added' => (int)$transaction['gems'],
        'total_gems' => (int)$user['gems'],
        'message' => 'Payment successful and gems added!'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
