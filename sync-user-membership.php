<?php
// sync-user-membership.php - UPDATED WITH AUTO UPGRADE
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ✅ TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url_path(string $path = ''): string
{
    $base = defined('BASE_PATH') ? (string) constant('BASE_PATH') : '';
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

// Only admin can run this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url_path('login.php'));
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
    $upgraded = 0;
    $errors = [];
    
    foreach ($pendingUsers as $transaction) {
        try {
            // Get package details from gem_packages
            $stmtPkg = $pdo->prepare("SELECT id, price, code FROM gem_packages WHERE code = ?");
            $stmtPkg->execute([$transaction['package']]);
            $newPackage = $stmtPkg->fetch(PDO::FETCH_ASSOC);
            
            if (!$newPackage) {
                $errors[] = "Package '{$transaction['package']}' not found for user {$transaction['name']}";
                continue;
            }
            
            // Check if membership already exists for this exact payment date
            $stmtCheck = $pdo->prepare("
                SELECT id FROM memberships 
                WHERE user_id = ? 
                AND DATE(start_date) = DATE(?)
            ");
            $stmtCheck->execute([$transaction['user_id'], $transaction['paid_at']]);
            
            if ($stmtCheck->fetchColumn()) {
                // Already synced for this payment date
                continue;
            }
            
            // ✅ CHECK FOR EXISTING ACTIVE MEMBERSHIP (AUTO UPGRADE LOGIC)
            $stmtCurrent = $pdo->prepare("
                SELECT m.id, m.membership_id, m.end_date, gp.price as current_price, gp.code as current_code
                FROM memberships m
                JOIN gem_packages gp ON m.membership_id = gp.id
                WHERE m.user_id = ? 
                AND m.status = 'active' 
                AND m.end_date >= NOW()
                ORDER BY gp.price DESC 
                LIMIT 1
            ");
            $stmtCurrent->execute([$transaction['user_id']]);
            $currentMembership = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
            
            if ($currentMembership) {
                // User already has active membership
                $currentPrice = floatval($currentMembership['current_price']);
                $newPrice = floatval($newPackage['price']);
                
                if ($newPrice > $currentPrice) {
                    // ✅ UPGRADE: New package is higher
                    // Expire old membership
                    $stmtExpire = $pdo->prepare("
                        UPDATE memberships 
                        SET status = 'upgraded', end_date = NOW() 
                        WHERE id = ?
                    ");
                    $stmtExpire->execute([$currentMembership['id']]);
                    
                    // Create new higher membership
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO memberships 
                        (user_id, membership_id, start_date, end_date, status, created_at) 
                        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', NOW())
                    ");
                    $stmtInsert->execute([
                        $transaction['user_id'],
                        $newPackage['id']
                    ]);
                    
                    error_log("UPGRADED membership for user {$transaction['user_id']} from {$currentMembership['current_code']} to {$newPackage['code']}");
                    $upgraded++;
                    $synced++;
                    
                } elseif ($newPrice == $currentPrice) {
                    // ✅ EXTEND: Same package, extend duration
                    $stmtExtend = $pdo->prepare("
                        UPDATE memberships 
                        SET end_date = DATE_ADD(end_date, INTERVAL 30 DAY) 
                        WHERE id = ?
                    ");
                    $stmtExtend->execute([$currentMembership['id']]);
                    
                    error_log("EXTENDED membership for user {$transaction['user_id']} - Package: {$newPackage['code']}");
                    $synced++;
                    
                } else {
                    // ✅ DOWNGRADE: Lower package, ignore (keep current higher membership)
                    error_log("IGNORED lower package purchase for user {$transaction['user_id']} - Current: {$currentMembership['current_code']}, New: {$newPackage['code']}");
                    continue;
                }
                
            } else {
                // ✅ NEW MEMBERSHIP: No active membership, create new
                $stmtInsert = $pdo->prepare("
                    INSERT INTO memberships 
                    (user_id, membership_id, start_date, end_date, status, created_at) 
                    VALUES (?, ?, ?, DATE_ADD(?, INTERVAL 30 DAY), 'active', NOW())
                ");
                
                $stmtInsert->execute([
                    $transaction['user_id'],
                    $newPackage['id'],
                    $transaction['paid_at'],
                    $transaction['paid_at']
                ]);
                
                error_log("NEW membership for user {$transaction['user_id']} - {$transaction['name']} - Order: {$transaction['order_id']}");
                $synced++;
            }
            
        } catch (PDOException $e) {
            $errors[] = "Error for user {$transaction['name']}: " . $e->getMessage();
            error_log("Sync error for user {$transaction['user_id']}: " . $e->getMessage());
        }
    }
    
    // Set success message
    if ($synced > 0) {
        $message = "Berhasil sync {$synced} membership!";
        if ($upgraded > 0) {
            $message .= " ({$upgraded} upgraded ke package lebih tinggi)";
        }
        $_SESSION['success'] = $message;
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
header("Location: " . url_path('admin-users.php'));
exit;
?>
