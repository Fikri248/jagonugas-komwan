<?php
// test-track-function.php - Test tracking function
session_start();
require_once 'db.php';

echo "<h2>🧪 Testing Track Visitor Function</h2>";

// ✅ Simulate visitor data
$_SESSION['user_id'] = 99; // Test user
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Test Browser';
$_SERVER['REQUEST_URI'] = '/test-page.php';

try {
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $pageUrl = $_SERVER['REQUEST_URI'] ?? '/';
    $sessionId = session_id();
    
    echo "<h3>📋 Data yang akan di-insert:</h3>";
    echo "<pre>";
    echo "User ID: " . ($userId ?? 'NULL (Guest)') . "\n";
    echo "IP Address: $ipAddress\n";
    echo "User Agent: $userAgent\n";
    echo "Page URL: $pageUrl\n";
    echo "Session ID: $sessionId\n";
    echo "</pre>";
    
    // ✅ Check for duplicate in last 5 minutes
    $stmt = $pdo->prepare("
        SELECT id FROM visitor_logs 
        WHERE session_id = ? 
        AND page_url = ?
        AND visited_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LIMIT 1
    ");
    $stmt->execute([$sessionId, $pageUrl]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "<p style='color: orange;'>⚠️ Visit already tracked in last 5 minutes (ID: {$exists['id']})</p>";
    } else {
        // ✅ Insert new visit
        $stmt = $pdo->prepare("
            INSERT INTO visitor_logs 
            (user_id, ip_address, user_agent, page_url, session_id, visited_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $userId, 
            $ipAddress, 
            $userAgent, 
            $pageUrl,
            $sessionId
        ]);
        
        if ($result) {
            echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS! Visit tracked successfully!</p>";
            echo "<p>Inserted ID: " . $pdo->lastInsertId() . "</p>";
        } else {
            echo "<p style='color: red;'>❌ FAILED to track visit</p>";
        }
    }
    
    // ✅ Show total count
    $stmt = $pdo->query("SELECT COUNT(*) FROM visitor_logs");
    $total = $stmt->fetchColumn();
    echo "<h3>Total Visits: <strong>$total</strong></h3>";
    
    echo "<br>";
    echo "<a href='test-visitor.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>View Test Visitor Page</a>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong> " . $e->getMessage() . "</p>";
}
?>
