<?php
// ============================================================
// GET DASHBOARD STATS ACTION - COMPLETE FIXED
// ============================================================

// ============================================================
// ERROR REPORTING
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $today = date('Y-m-d');
    $currency = $_SESSION['currency'] ?? '₹';
    
    // Check if tables exist
    $check = $pdo->query("SHOW TABLES LIKE 'Orders'");
    if ($check->rowCount() == 0) {
        echo json_encode([
            'success' => true,
            'today' => ['orders' => 0, 'revenue' => 0],
            'week' => ['orders' => 0, 'revenue' => 0],
            'month' => ['orders' => 0, 'revenue' => 0],
            'topServices' => [],
            'topUsers' => []
        ]);
        exit();
    }
    
    // Check columns
    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM Orders");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['Field'];
    }
    
    // Determine column names
    $serviceColumn = in_array('service_name', $columns) ? 'service_name' : 'service_id';
    $priceColumn = in_array('price', $columns) ? 'price' : 'amount';
    $statusColumn = in_array('status', $columns) ? 'status' : "'pending'";
    
    // Today's stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as orders, COALESCE(SUM($priceColumn), 0) as revenue FROM Orders WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders' => 0, 'revenue' => 0];

    // Week stats
    $weekStart = date('Y-m-d', strtotime('last monday'));
    $stmt = $pdo->prepare("SELECT COUNT(*) as orders, COALESCE(SUM($priceColumn), 0) as revenue FROM Orders WHERE DATE(created_at) >= ?");
    $stmt->execute([$weekStart]);
    $weekStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders' => 0, 'revenue' => 0];

    // Month stats
    $monthStart = date('Y-m-01');
    $stmt = $pdo->prepare("SELECT COUNT(*) as orders, COALESCE(SUM($priceColumn), 0) as revenue FROM Orders WHERE DATE(created_at) >= ?");
    $stmt->execute([$monthStart]);
    $monthStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders' => 0, 'revenue' => 0];

    // Top Services
    if ($serviceColumn == 'service_name') {
        $stmt = $pdo->query("SELECT $serviceColumn as service_name, COUNT(*) as count FROM Orders GROUP BY $serviceColumn ORDER BY count DESC LIMIT 10");
    } else {
        $stmt = $pdo->query("SELECT s.name as service_name, COUNT(o.id) as count FROM Orders o JOIN Services s ON o.service_id = s.id GROUP BY o.service_id ORDER BY count DESC LIMIT 10");
    }
    $topServices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Top Users
    $stmt = $pdo->query("SELECT u.email, COUNT(o.id) as orders, COALESCE(SUM(o.$priceColumn), 0) as spent FROM Orders o JOIN Users u ON o.user_id = u.id GROUP BY o.user_id ORDER BY orders DESC LIMIT 10");
    $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'today' => $todayStats,
        'week' => $weekStats,
        'month' => $monthStats,
        'topServices' => $topServices,
        'topUsers' => $topUsers
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>