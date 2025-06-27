<?php
$page_title = 'Dashboard';
require_once 'includes/header.php';
require_once 'config/database.php';

$pdo = getDBConnection();

// Get dashboard statistics
$stats = [];

// Today's sales
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
    FROM sales 
    WHERE DATE(sale_date) = CURDATE()
");
$stmt->execute();
$todaySales = $stmt->fetch();
$stats['today_sales'] = $todaySales['count'];
$stats['today_revenue'] = $todaySales['total'];

// Total revenue
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales");
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch()['total'];

// Low stock items (less than 10)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM inventory WHERE quantity < 10");
$stmt->execute();
$stats['low_stock'] = $stmt->fetch()['count'];

// Total products
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM inventory");
$stmt->execute();
$stats['total_products'] = $stmt->fetch()['count'];

// Recent sales
$stmt = $pdo->prepare("
    SELECT s.sale_id, s.sale_date, s.total_amount, u.name as cashier_name
    FROM sales s
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.sale_date DESC
    LIMIT 5
");
$stmt->execute();
$recentSales = $stmt->fetchAll();

// Low stock products
$stmt = $pdo->prepare("
    SELECT name, quantity, price
    FROM inventory
    WHERE quantity < 10
    ORDER BY quantity ASC
    LIMIT 5
");
$stmt->execute();
$lowStockProducts = $stmt->fetchAll();
?>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Today's Sales
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['today_sales']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Today's Revenue
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            $<?php echo number_format($stats['today_revenue'], 2); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Revenue
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            $<?php echo number_format($stats['total_revenue'], 2); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Low Stock Items
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['low_stock']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="modules/sales.php?action=new" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus me-2"></i>New Sale
                    </a>
                    <a href="modules/inventory.php?action=add" class="btn btn-success btn-lg">
                        <i class="fas fa-box me-2"></i>Add Product
                    </a>
                    <?php if ($_SESSION['role'] == 'Admin'): ?>
                    <a href="modules/users.php?action=add" class="btn btn-info btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Add User
                    </a>
                    <?php endif; ?>
                    <a href="reports/index.php" class="btn btn-warning btn-lg">
                        <i class="fas fa-chart-bar me-2"></i>View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Sales</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentSales)): ?>
                    <p class="text-muted">No recent sales</p>
                <?php else: ?>
                    <?php foreach ($recentSales as $sale): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>Sale #<?php echo $sale['sale_id']; ?></strong><br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($sale['cashier_name']); ?> • 
                                    <?php echo date('M j, g:i A', strtotime($sale['sale_date'])); ?>
                                </small>
                            </div>
                            <span class="badge bg-success">$<?php echo number_format($sale['total_amount'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-warning">Low Stock Alert</h6>
            </div>
            <div class="card-body">
                <?php if (empty($lowStockProducts)): ?>
                    <p class="text-success">All products are well stocked!</p>
                <?php else: ?>
                    <?php foreach ($lowStockProducts as $product): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                <small class="text-muted">$<?php echo number_format($product['price'], 2); ?></small>
                            </div>
                            <span class="badge bg-danger"><?php echo $product['quantity']; ?> left</span>
                        </div>
                    <?php endforeach; ?>
                    <a href="modules/inventory.php" class="btn btn-warning btn-sm mt-2">
                        <i class="fas fa-eye me-1"></i>View All
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.text-gray-300 {
    color: #dddfeb !important;
}
.text-gray-800 {
    color: #5a5c69 !important;
}
.font-weight-bold {
    font-weight: 700 !important;
}
.text-xs {
    font-size: 0.7rem;
}
</style>

<?php require_once 'includes/footer.php'; ?> 