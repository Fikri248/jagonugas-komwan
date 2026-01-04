<?php
// sync-user-membership.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only admin can run this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

try {
    // Find students yang sudah bayar tapi belum dapat membership
    $stmt = $pdo->query("
        SELECT 
            gt.user_id,
            gt.package,
            gt.gems,
            gt.paid_at,
            gt.order_id,
            u.name,
            u.email
        FROM gem_transactions gt
        JOIN users u ON gt.user_id = u.id
        LEFT JOIN memberships m ON gt.user_id = m.user_id 
            AND DATE(m.start_date) = DATE(gt.paid_at)
        WHERE gt.transaction_status = 'settlement'
        AND gt.paid_at IS NOT NULL
        AND m.id IS NULL
        AND u.role = 'student'
        ORDER BY gt.paid_at DESC
    ");
    
    $pendingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $synced = 0;
    $errors = [];
    
    foreach ($pendingUsers as $transaction) {
        try {
            // Get package ID from gem_packages
            $stmtPkg = $pdo->prepare("SELECT id FROM gem_packages WHERE code = ?");
            $stmtPkg->execute([$transaction['package']]);
            $packageId = $stmtPkg->fetchColumn();
            
            if (!$packageId) {
                $errors[] = "Package '{$transaction['package']}' not found for user {$transaction['name']}";
                continue;
            }
            
            // Check if membership already exists for this date
            $stmtCheck = $pdo->prepare("
                SELECT id FROM memberships 
                WHERE user_id = ? 
                AND DATE(start_date) = DATE(?)
            ");
            $stmtCheck->execute([$transaction['user_id'], $transaction['paid_at']]);
            
            if ($stmtCheck->fetchColumn()) {
                // Already synced
                continue;
            }
            
            // Create membership (30 days from payment date)
            $stmtInsert = $pdo->prepare("
                INSERT INTO memberships 
                (user_id, membership_id, start_date, end_date, status, created_at) 
                VALUES (?, ?, ?, DATE_ADD(?, INTERVAL 30 DAY), 'active', NOW())
            ");
            
            $stmtInsert->execute([
                $transaction['user_id'],
                $packageId,
                $transaction['paid_at'],
                $transaction['paid_at']
            ]);
            
            // Log success
            error_log("Synced membership for user {$transaction['user_id']} - {$transaction['name']} - Order: {$transaction['order_id']}");
            
            $synced++;
            
        } catch (PDOException $e) {
            $errors[] = "Error for user {$transaction['name']}: " . $e->getMessage();
            error_log("Sync error for user {$transaction['user_id']}: " . $e->getMessage());
        }
    }
    
    // Set success message
    if ($synced > 0) {
        $_SESSION['success'] = "Berhasil sync {$synced} membership!";
    } else {
        $_SESSION['success'] = "Tidak ada membership yang perlu di-sync.";
    }
    
    // Add error messages if any
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
    
} catch (PDOException $e) {
    error_log("Sync membership error: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal sync membership: ' . $e->getMessage();
}

// Redirect back to users page
header("Location: admin-users.php");
exit;
?>
