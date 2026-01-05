<?php
/**
 * Sync User Membership - AUTO UPGRADE LOGIC
 * - Auto create membership untuk user yang sudah bayar tapi belum dapat membership
 * - Auto upgrade jika user beli package lebih tinggi (Basic → Pro → Plus)
 * - Auto extend jika user beli package sama (renew)
 * - Auto ignore jika user beli package lebih rendah (keep higher tier)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Track visitor
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
    // ✅ Find students yang sudah bayar tapi belum dapat membership
    $stmt = $pdo->query("
        SELECT 
            gt.id as transaction_id,
            gt.user_id,
            gt.package,
            gt.amount,
            gt.paid_at,
            gt.order_id,
            u.name,
            u.email
        FROM gem_transactions gt
        JOIN users u ON gt.user_id = u.id
        WHERE gt.transaction_status = 'settlement'
        AND gt.paid_at IS NOT NULL
        AND u.role = 'student'
        ORDER BY gt.paid_at DESC
    ");
    
    $allTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $synced = 0;
    $upgraded = 0;
    $extended = 0;
    $ignored = 0;
    $errors = [];
    
    // ✅ GET TIER ORDER (untuk compare package)
    $tierOrder = [];
    $stmtTiers = $pdo->query("SELECT id, code, price FROM gem_packages WHERE is_active = 1 ORDER BY price ASC");
    while ($tier = $stmtTiers->fetch(PDO::FETCH_ASSOC)) {
        $tierOrder[$tier['code']] = [
            'id' => $tier['id'],
            'price' => floatval($tier['price'])
        ];
    }
    
    foreach ($allTransactions as $transaction) {
        try {
            $packageCode = strtolower($transaction['package']);
            
            // Check if package exists
            if (!isset($tierOrder[$packageCode])) {
                $errors[] = "Package '{$packageCode}' tidak ditemukan untuk {$transaction['name']}";
                continue;
            }
            
            $newPackageId = $tierOrder[$packageCode]['id'];
            $newPackagePrice = $tierOrder[$packageCode]['price'];
            
            // ✅ CHECK: Apakah transaksi ini sudah di-sync sebelumnya?
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM memberships 
                WHERE user_id = ? 
                AND membership_id = ?
                AND ABS(TIMESTAMPDIFF(SECOND, start_date, ?)) < 60
            ");
            $stmtCheck->execute([
                $transaction['user_id'], 
                $newPackageId, 
                $transaction['paid_at']
            ]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                // Already synced, skip
                continue;
            }
            
            // ✅ CHECK: Apakah user punya active membership?
            $stmtCurrent = $pdo->prepare("
                SELECT 
                    m.id, 
                    m.membership_id, 
                    m.end_date, 
                    gp.code as current_code,
                    gp.price as current_price
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
                // ✅ USER SUDAH PUNYA ACTIVE MEMBERSHIP
                
                $currentPrice = floatval($currentMembership['current_price']);
                $currentCode = $currentMembership['current_code'];
                
                if ($newPackagePrice > $currentPrice) {
                    // ========================================
                    // SCENARIO 1: UPGRADE (Basic → Pro → Plus)
                    // ========================================
                    
                    // Expire old membership
                    $stmtExpire = $pdo->prepare("
                        UPDATE memberships 
                        SET status = 'upgraded', 
                            end_date = NOW(), 
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmtExpire->execute([$currentMembership['id']]);
                    
                    // Create new higher tier membership
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO memberships 
                        (user_id, membership_id, start_date, end_date, status, created_at)
                        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', NOW())
                    ");
                    $stmtInsert->execute([$transaction['user_id'], $newPackageId]);
                    
                    // Send notification
                    $stmtNotif = $pdo->prepare("
                        INSERT INTO notifications 
                        (user_id, type, title, message, icon, color, created_at)
                        VALUES (?, 'membership', '🚀 Membership Upgraded!', ?, 'arrow-up-circle', '#10b981', NOW())
                    ");
                    $stmtNotif->execute([
                        $transaction['user_id'],
                        "Membership Anda berhasil diupgrade dari {$currentCode} ke {$packageCode}!"
                    ]);
                    
                    error_log("✅ UPGRADED: User {$transaction['name']} ({$transaction['user_id']}) from {$currentCode} to {$packageCode}");
                    $upgraded++;
                    $synced++;
                    
                } elseif (abs($newPackagePrice - $currentPrice) < 0.01) {
                    // ========================================
                    // SCENARIO 2: RENEWAL/EXTEND (Same Package)
                    // ========================================
                    
                    $stmtExtend = $pdo->prepare("
                        UPDATE memberships 
                        SET end_date = DATE_ADD(end_date, INTERVAL 30 DAY),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtExtend->execute([$currentMembership['id']]);
                    
                    // Send notification
                    $stmtNotif = $pdo->prepare("
                        INSERT INTO notifications 
                        (user_id, type, title, message, icon, color, created_at)
                        VALUES (?, 'membership', '⏱️ Membership Extended!', ?, 'calendar-plus', '#3b82f6', NOW())
                    ");
                    $stmtNotif->execute([
                        $transaction['user_id'],
                        "Membership {$packageCode} Anda diperpanjang 30 hari!"
                    ]);
                    
                    error_log("✅ EXTENDED: User {$transaction['name']} ({$transaction['user_id']}) - Package: {$packageCode}");
                    $extended++;
                    $synced++;
                    
                } else {
                    // ========================================
                    // SCENARIO 3: DOWNGRADE (Ignore, keep higher tier)
                    // ========================================
                    
                    error_log("⏭️ IGNORED: User {$transaction['name']} ({$transaction['user_id']}) - Keep {$currentCode}, Ignore {$packageCode}");
                    $ignored++;
                }
                
            } else {
                // ========================================
                // SCENARIO 4: NEW MEMBERSHIP (First Time)
                // ========================================
                
                $stmtInsert = $pdo->prepare("
                    INSERT INTO memberships 
                    (user_id, membership_id, start_date, end_date, status, created_at)
                    VALUES (?, ?, ?, DATE_ADD(?, INTERVAL 30 DAY), 'active', NOW())
                ");
                $stmtInsert->execute([
                    $transaction['user_id'],
                    $newPackageId,
                    $transaction['paid_at'],
                    $transaction['paid_at']
                ]);
                
                // Send notification
                $stmtNotif = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, type, title, message, icon, color, created_at)
                    VALUES (?, 'membership', '🎉 Membership Active!', ?, 'gift', '#10b981', NOW())
                ");
                $stmtNotif->execute([
                    $transaction['user_id'],
                    "Selamat! Membership {$packageCode} Anda sudah aktif!"
                ]);
                
                error_log("✅ NEW: User {$transaction['name']} ({$transaction['user_id']}) - Package: {$packageCode} - Order: {$transaction['order_id']}");
                $synced++;
            }
            
        } catch (PDOException $e) {
            $errors[] = "Error for {$transaction['name']}: " . $e->getMessage();
            error_log("❌ Sync error for user {$transaction['user_id']}: " . $e->getMessage());
        }
    }
    
    // ✅ BUILD SUCCESS MESSAGE
    $messages = [];
    
    if ($synced > 0) {
        $messages[] = "✅ Berhasil sync <strong>{$synced} membership</strong>";
    }
    
    if ($upgraded > 0) {
        $messages[] = "🚀 <strong>{$upgraded}</strong> upgraded ke tier lebih tinggi";
    }
    
    if ($extended > 0) {
        $messages[] = "⏱️ <strong>{$extended}</strong> diperpanjang (renewal)";
    }
    
    if ($ignored > 0) {
        $messages[] = "⏭️ <strong>{$ignored}</strong> diabaikan (tier lebih rendah)";
    }
    
    if (!empty($messages)) {
        $_SESSION['success'] = implode(' | ', $messages);
    } else {
        $_SESSION['success'] = "ℹ️ Tidak ada membership yang perlu di-sync.";
    }
    
    // Add error messages if any
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
    
} catch (PDOException $e) {
    error_log("❌ Sync membership error: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal sync membership: ' . $e->getMessage();
}

// Redirect back to users page
header("Location: " . url_path('admin-users.php'));
exit;
