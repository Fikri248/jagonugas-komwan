<?php
// payment-process.php - Create Midtrans Transaction (KEEP - NO CHANGES)
session_start();
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$package = $input['package'] ?? null;
$price = $input['price'] ?? null;
$gems = $input['gems'] ?? null;

if (!$package || !$price || !$gems) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $order_id = 'GEM-' . $user_id . '-' . time();
    
    // Prepare transaction data for Midtrans
    $transaction_data = [
        'transaction_details' => [
            'order_id' => $order_id,
            'gross_amount' => (int)$price
        ],
        'customer_details' => [
            'first_name' => $_SESSION['name'] ?? 'Student',
            'email' => $_SESSION['email'] ?? 'student@example.com'
        ],
        'item_details' => [
            [
                'id' => $package,
                'price' => (int)$price,
                'quantity' => 1,
                'name' => 'Paket ' . ucfirst($package) . ' - ' . number_format($gems) . ' Gems'
            ]
        ]
    ];
    
    // Call Midtrans Snap API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => MIDTRANS_API_URL . '/snap/v1/transactions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($transaction_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create transaction',
            'http_code' => $httpCode
        ]);
        exit;
    }
    
    $midtrans_response = json_decode($response, true);
    $snap_token = $midtrans_response['token'] ?? null;
    
    if (!$snap_token) {
        echo json_encode(['success' => false, 'message' => 'No snap token received']);
        exit;
    }
    
    // Save to database
    $stmt = $pdo->prepare("
        INSERT INTO gem_transactions 
        (user_id, order_id, package, amount, gems, transaction_status, snap_token, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())
    ");
    
    $stmt->execute([$user_id, $order_id, $package, $price, $gems, $snap_token]);
    
    echo json_encode([
        'success' => true,
        'snap_token' => $snap_token,
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    error_log("ERROR in payment-process.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
