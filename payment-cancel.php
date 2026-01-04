<?php
// payment-cancel.php - UPDATE STATUS TO CANCEL (NOT DELETE)
session_start();
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? null;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // ✅ Cek transaksi milik user ini dan statusnya
    $stmt = $pdo->prepare("
        SELECT id, transaction_status 
        FROM gem_transactions 
        WHERE order_id = ? AND user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }

    $current_status = $transaction['transaction_status'];

    // ✅ Hanya pending dan expire yang bisa di-cancel
    if (!in_array($current_status, ['pending', 'expire'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Only pending or expired transactions can be cancelled'
        ]);
        exit;
    }

    // ✅ UPDATE status jadi 'cancel' (BUKAN DELETE)
    $stmt = $pdo->prepare("
        UPDATE gem_transactions 
        SET transaction_status = 'cancel'
        WHERE order_id = ? AND user_id = ?
    ");
    
    $stmt->execute([$order_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        // ✅ Log activity
        error_log("Transaction cancelled: Order ID = $order_id, User ID = $user_id, Previous Status = $current_status");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Transaction cancelled successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to cancel transaction'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Cancel payment error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Cancel payment error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
