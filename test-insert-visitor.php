<?php
// test-manual-insert.php - Force insert data
require_once 'db.php';

try {
    // ✅ Clear old data (optional)
    // $pdo->exec("TRUNCATE TABLE visitor_logs");
    
    // ✅ Insert test data
    $stmt = $pdo->prepare("
        INSERT INTO visitor_logs 
        (user_id, ip_address, user_agent, page_url, session_id, referrer, visited_at) 
        VALUES 
        (NULL, '127.0.0.1', 'Chrome Browser', '/index.php', 'test123', NULL, NOW()),
        (1, '192.168.1.1', 'Firefox Browser', '/login.php', 'test456', 'https://google.com', NOW()),
        (2, '192.168.1.2', 'Safari Browser', '/student-dashboard.php', 'test789', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY))
    ");
    
    $inserted = $stmt->execute();
    
    echo "<h2>✅ Insert Test Data</h2>";
    echo "Status: " . ($inserted ? "SUCCESS" : "FAILED") . "<br>";
    echo "Rows inserted: " . $stmt->rowCount() . "<br><br>";
    
    // ✅ Count total
    $stmt = $pdo->query("SELECT COUNT(*) FROM visitor_logs");
    $total = $stmt->fetchColumn();
    echo "<h3>Total Visits in Database: <strong>$total</strong></h3>";
    
    // ✅ Show all data
    $stmt = $pdo->query("SELECT * FROM visitor_logs ORDER BY visited_at DESC LIMIT 10");
    $logs = $stmt->fetchAll();
    
    echo "<h3>Latest 10 Visits:</h3>";
    echo "<table border='1' cellpadding='8' cellspacing='0'>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>ID</th><th>User ID</th><th>IP</th><th>Page</th><th>Session</th><th>Time</th>";
    echo "</tr>";
    
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log['id']}</td>";
        echo "<td>" . ($log['user_id'] ?? '<em>Guest</em>') . "</td>";
        echo "<td>{$log['ip_address']}</td>";
        echo "<td>{$log['page_url']}</td>";
        echo "<td>" . substr($log['session_id'], 0, 8) . "...</td>";
        echo "<td>{$log['visited_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><br>";
    echo "<a href='test-visitor.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>View Test Visitor Page</a>";
    echo "&nbsp;&nbsp;";
    echo "<a href='index.php' style='padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;'>Go to Index</a>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}
?>
