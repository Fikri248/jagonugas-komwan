<?php
// payment-cancel.php - Cancel Pending Payment (KEEP - NO CHANGES)
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
    // Verify ownership and only delete if pending
    $stmt = $pdo->prepare("
        DELETE FROM gem_transactions 
        WHERE order_id = ? 
        AND user_id = ? 
        AND transaction_status = 'pending'
    ");
    
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Transaction cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Transaction not found or already processed']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
