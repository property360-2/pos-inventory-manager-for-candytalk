<?php
require_once '../config/database.php';
$pdo = getDBConnection();

// Get filters from query params
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$product_id = $_GET['product_id'] ?? '';
$user_id = $_GET['user_id'] ?? '';

function clean($v) {
    return htmlspecialchars(trim($v));
}
$date_from = clean($date_from);
$date_to = clean($date_to);
$product_id = clean($product_id);
$user_id = clean($user_id);

// Determine date range for sales chart
if ($date_from && $date_to) {
    $from = strtotime($date_from);
    $to = strtotime($date_to);
    if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
    $days = ($to - $from) / 86400 + 1;
    if ($days > 30) {
        $from = $to - 29 * 86400;
        $date_from = date('Y-m-d', $from);
    }
} else {
    $to = strtotime('today');
    $from = $to - 29 * 86400;
    $date_from = date('Y-m-d', $from);
    $date_to = date('Y-m-d', $to);
}

// Sales & Revenue (filtered last 30 days)
$salesLabels = [];
$salesCounts = [];
$salesRevenue = [];
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime($date_from . "+$i days"));
    if ($date > $date_to) break;
    $salesLabels[] = date('M j', strtotime($date));
    $where = 'DATE(sale_date) = ?';
    $params = [$date];
    if ($product_id) {
        $where .= ' AND sale_id IN (SELECT sale_id FROM sale_items WHERE product_id = ?)';
        $params[] = $product_id;
    }
    if ($user_id) {
        $where .= ' AND user_id = ?';
        $params[] = $user_id;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount),0) as revenue FROM sales WHERE $where");
    $stmt->execute($params);
    $row = $stmt->fetch();
    $salesCounts[] = (int)$row['count'];
    $salesRevenue[] = (float)$row['revenue'];
}

// Top 5 selling products (by quantity sold, filtered by date/user)
$where = '1=1';
$params = [];
if ($date_from) { $where .= ' AND s.sale_date >= ?'; $params[] = $date_from . ' 00:00:00'; }
if ($date_to) { $where .= ' AND s.sale_date <= ?'; $params[] = $date_to . ' 23:59:59'; }
if ($user_id) { $where .= ' AND s.user_id = ?'; $params[] = $user_id; }
$sql = "SELECT i.name, SUM(si.quantity) as units
        FROM sale_items si
        JOIN inventory i ON si.product_id = i.product_id
        JOIN sales s ON si.sale_id = s.sale_id
        WHERE $where
        GROUP BY si.product_id
        ORDER BY units DESC
        LIMIT 5";
if ($product_id) {
    $sql = str_replace('GROUP BY', 'AND si.product_id = ? GROUP BY', $sql);
    array_splice($params, ($user_id ? 3 : 2), 0, $product_id);
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$topProducts = $stmt->fetchAll();
$topProductLabels = array_column($topProducts, 'name');
$topProductUnits = array_map('intval', array_column($topProducts, 'units'));

// Inventory stock levels (top 10 by quantity, not filtered)
$stmt = $pdo->prepare("SELECT name, quantity FROM inventory ORDER BY quantity DESC, name ASC LIMIT 10");
$stmt->execute();
$inventory = $stmt->fetchAll();
$inventoryLabels = array_column($inventory, 'name');
$inventoryStock = array_map('intval', array_column($inventory, 'quantity'));

header('Content-Type: application/json');
echo json_encode([
    'sales' => [
        'labels' => $salesLabels,
        'counts' => $salesCounts,
        'revenue' => $salesRevenue
    ],
    'top_products' => [
        'labels' => $topProductLabels,
        'units' => $topProductUnits
    ],
    'inventory' => [
        'labels' => $inventoryLabels,
        'stock' => $inventoryStock
    ]
]); 