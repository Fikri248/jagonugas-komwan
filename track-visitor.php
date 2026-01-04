<?php
// track-visitor.php - WITH DEBUG
require_once __DIR__ . '/db.php';

function trackVisitor() {
    global $pdo;
    
    // ✅ Log that function is called
    error_log("==== track-visitor.php: Function called ====");
    
    if (!$pdo || !($pdo instanceof PDO)) {
        error_log("track-visitor.php: PDO connection not available");
        return false;
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $pageUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $sessionId = session_id();
        
        // ✅ Log data
        error_log("Tracking: Page=$pageUrl, IP=$ipAddress, Session=$sessionId, User=" . ($userId ?? 'guest'));
        
        // Check duplicate
        $stmt = $pdo->prepare("
            SELECT id FROM visitor_logs 
            WHERE session_id = ? 
            AND page_url = ?
            AND visited_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            LIMIT 1
        ");
        $stmt->execute([$sessionId, $pageUrl]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO visitor_logs 
                (user_id, ip_address, user_agent, page_url, session_id, visited_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $ipAddress, $userAgent, $pageUrl, $sessionId]);
            
            error_log("✅ Visit tracked successfully! ID: " . $pdo->lastInsertId());
            return true;
        }
        
        error_log("⚠️ Visit already tracked recently");
        return false;
        
    } catch (PDOException $e) {
        error_log("❌ track-visitor.php error: " . $e->getMessage());
        return false;
    }
}

// Auto-execute
trackVisitor();
?>
