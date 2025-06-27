<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$pdo = getDBConnection();

// Sanitize and get parameters
$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'html';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$product_id = $_GET['product_id'] ?? '';
$user_id = $_GET['user_id'] ?? '';

function clean($v) {
    return htmlspecialchars(trim($v));
}

$type = clean($type);
$format = clean($format);
$date_from = clean($date_from);
$date_to = clean($date_to);
$product_id = clean($product_id);
$user_id = clean($user_id);

// Function to generate PDF from HTML
function generatePDF($html, $filename) {
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Use wkhtmltopdf if available, otherwise use browser print
    $command = "wkhtmltopdf --page-size A4 --margin-top 20 --margin-bottom 20 --margin-left 20 --margin-right 20 - -";
    
    // Create temporary HTML file
    $tempFile = tempnam(sys_get_temp_dir(), 'report_') . '.html';
    file_put_contents($tempFile, $html);
    
    // Try to use wkhtmltopdf
    $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w")   // stderr
    );
    
    $process = proc_open($command, $descriptorspec, $pipes);
    
    if (is_resource($process)) {
        // Send HTML to stdin
        fwrite($pipes[0], $html);
        fclose($pipes[0]);
        
        // Get PDF output
        $pdf = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $return_value = proc_close($process);
        
        if ($return_value === 0 && !empty($pdf)) {
            echo $pdf;
            unlink($tempFile);
            exit;
        }
    }
    
    // Fallback: Output HTML for browser print
    header('Content-Type: text/html');
    echo $html;
    unlink($tempFile);
    exit;
}

// Function to generate CSV
function generateCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Add BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    // Output headers
    echo implode(',', array_map(function($header) {
        return '"' . str_replace('"', '""', $header) . '"';
    }, $headers)) . "\n";
    
    // Output data
    foreach ($data as $row) {
        echo implode(',', array_map(function($cell) {
            return '"' . str_replace('"', '""', $cell) . '"';
        }, $row)) . "\n";
    }
    exit;
}

if ($type === 'sales') {
    // Build query for sales report
    $where = '1=1';
    $params = [];
    if ($date_from) { $where .= ' AND s.sale_date >= ?'; $params[] = $date_from . ' 00:00:00'; }
    if ($date_to) { $where .= ' AND s.sale_date <= ?'; $params[] = $date_to . ' 23:59:59'; }
    if ($product_id) { $where .= ' AND si.product_id = ?'; $params[] = $product_id; }
    if ($user_id) { $where .= ' AND s.user_id = ?'; $params[] = $user_id; }
    
    $sql = "
        SELECT s.sale_id, s.sale_date, u.name as cashier, i.name as product, si.quantity, si.subtotal, s.total_amount
        FROM sales s
        JOIN users u ON s.user_id = u.user_id
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN inventory i ON si.product_id = i.product_id
        WHERE $where
        ORDER BY s.sale_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    // Calculate totals
    $totalSales = count(array_unique(array_column($rows, 'sale_id')));
    $totalRevenue = array_sum(array_column($rows, 'subtotal'));
    $totalItems = array_sum(array_column($rows, 'quantity'));
    
    if ($format === 'pdf') {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Sales Report - Candy Talk POS</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #ff6b6b; padding-bottom: 20px; }
                .header h1 { color: #ff6b6b; margin: 0; }
                .header p { color: #666; margin: 5px 0; }
                .summary { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
                .summary-item { text-align: center; }
                .summary-item h3 { margin: 0; color: #ff6b6b; }
                .summary-item p { margin: 5px 0; font-size: 14px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #ff6b6b; color: white; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .filters { background: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 12px; }
                .filters strong { color: #ff6b6b; }
                @media print { body { margin: 10px; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🍬 Candy Talk POS</h1>
                <p>Sales Report</p>
                <p>Generated on: ' . date('F j, Y g:i A') . '</p>
            </div>';
        
        // Add filters info
        $filters = [];
        if ($date_from) $filters[] = 'From: ' . date('M j, Y', strtotime($date_from));
        if ($date_to) $filters[] = 'To: ' . date('M j, Y', strtotime($date_to));
        if ($product_id) {
            $stmt = $pdo->prepare("SELECT name FROM inventory WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $productName = $stmt->fetchColumn();
            $filters[] = 'Product: ' . $productName;
        }
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $userName = $stmt->fetchColumn();
            $filters[] = 'Cashier: ' . $userName;
        }
        
        if (!empty($filters)) {
            $html .= '<div class="filters"><strong>Filters:</strong> ' . implode(' | ', $filters) . '</div>';
        }
        
        $html .= '<div class="summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <h3>' . $totalSales . '</h3>
                    <p>Total Sales</p>
                </div>
                <div class="summary-item">
                    <h3>$' . number_format($totalRevenue, 2) . '</h3>
                    <p>Total Revenue</p>
                </div>
                <div class="summary-item">
                    <h3>' . $totalItems . '</h3>
                    <p>Items Sold</p>
                </div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Sale ID</th>
                    <th>Date</th>
                    <th>Cashier</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($rows as $r) {
            $html .= '<tr>
                <td>' . $r['sale_id'] . '</td>
                <td>' . date('M j, Y g:i A', strtotime($r['sale_date'])) . '</td>
                <td>' . htmlspecialchars($r['cashier']) . '</td>
                <td>' . htmlspecialchars($r['product']) . '</td>
                <td>' . $r['quantity'] . '</td>
                <td>$' . number_format($r['subtotal'], 2) . '</td>
                <td>$' . number_format($r['total_amount'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table></body></html>';
        
        generatePDF($html, 'sales_report_' . date('Y-m-d_H-i-s') . '.pdf');
        
    } else {
        // CSV export
        $headers = ['Sale ID', 'Date', 'Cashier', 'Product', 'Quantity', 'Subtotal', 'Total Amount'];
        $csvData = [];
        
        foreach ($rows as $r) {
            $csvData[] = [
                $r['sale_id'],
                date('Y-m-d H:i:s', strtotime($r['sale_date'])),
                $r['cashier'],
                $r['product'],
                $r['quantity'],
                $r['subtotal'],
                $r['total_amount']
            ];
        }
        
        generateCSV($csvData, $headers, 'sales_report_' . date('Y-m-d_H-i-s') . '.csv');
    }
    
} elseif ($type === 'inventory') {
    // Inventory report
    $sql = "SELECT name, description, price, quantity FROM inventory ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    // Calculate totals
    $totalProducts = count($rows);
    $totalValue = array_sum(array_map(function($row) { return $row['price'] * $row['quantity']; }, $rows));
    $lowStockItems = count(array_filter($rows, function($row) { return $row['quantity'] < 10; }));
    
    if ($format === 'pdf') {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Inventory Report - Candy Talk POS</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #ff6b6b; padding-bottom: 20px; }
                .header h1 { color: #ff6b6b; margin: 0; }
                .header p { color: #666; margin: 5px 0; }
                .summary { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
                .summary-item { text-align: center; }
                .summary-item h3 { margin: 0; color: #ff6b6b; }
                .summary-item p { margin: 5px 0; font-size: 14px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #ff6b6b; color: white; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .low-stock { background-color: #ffe6e6 !important; }
                .out-of-stock { background-color: #ffcccc !important; }
                @media print { body { margin: 10px; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🍬 Candy Talk POS</h1>
                <p>Inventory Report</p>
                <p>Generated on: ' . date('F j, Y g:i A') . '</p>
            </div>
            
            <div class="summary">
                <div class="summary-grid">
                    <div class="summary-item">
                        <h3>' . $totalProducts . '</h3>
                        <p>Total Products</p>
                    </div>
                    <div class="summary-item">
                        <h3>$' . number_format($totalValue, 2) . '</h3>
                        <p>Total Value</p>
                    </div>
                    <div class="summary-item">
                        <h3>' . $lowStockItems . '</h3>
                        <p>Low Stock Items</p>
                    </div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total Value</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($rows as $r) {
            $rowClass = '';
            if ($r['quantity'] == 0) {
                $rowClass = 'out-of-stock';
            } elseif ($r['quantity'] < 10) {
                $rowClass = 'low-stock';
            }
            
            $totalValue = $r['price'] * $r['quantity'];
            
            $html .= '<tr class="' . $rowClass . '">
                <td>' . htmlspecialchars($r['name']) . '</td>
                <td>' . htmlspecialchars($r['description']) . '</td>
                <td>$' . number_format($r['price'], 2) . '</td>
                <td>' . $r['quantity'] . '</td>
                <td>$' . number_format($totalValue, 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table></body></html>';
        
        generatePDF($html, 'inventory_report_' . date('Y-m-d_H-i-s') . '.pdf');
        
    } else {
        // CSV export
        $headers = ['Product Name', 'Description', 'Price', 'Quantity', 'Total Value'];
        $csvData = [];
        
        foreach ($rows as $r) {
            $totalValue = $r['price'] * $r['quantity'];
            $csvData[] = [
                $r['name'],
                $r['description'],
                $r['price'],
                $r['quantity'],
                $totalValue
            ];
        }
        
        generateCSV($csvData, $headers, 'inventory_report_' . date('Y-m-d_H-i-s') . '.csv');
    }
    
} else {
    header('Content-Type: text/html');
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
    echo '<h2>Error: Invalid report type</h2>';
    echo '<p>The report type "' . htmlspecialchars($type) . '" is not supported.</p>';
    echo '<p><a href="index.php">Back to Reports</a></p>';
    echo '</body></html>';
    exit;
} 