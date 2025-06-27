<?php
require_once '../includes/header.php';
$pdo = getDBConnection();

// Fetch products and users for filters
$stmt = $pdo->prepare("SELECT product_id, name FROM inventory ORDER BY name ASC");
$stmt->execute();
$products = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT user_id, name FROM users ORDER BY name ASC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<div class="container-fluid">
    <h2 class="mb-4">Reports</h2>
    <!-- Advanced Search Panel for Charts -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white"><i class="fas fa-filter me-2"></i>Chart Filters</div>
        <div class="card-body">
            <form class="row g-3" id="chartFilterForm" onsubmit="return false;">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" id="chartDateFrom" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" id="chartDateTo" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Product</label>
                    <select name="product_id" id="chartProductId" class="form-select">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user_id" id="chartUserId" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="button" class="btn btn-primary" id="applyChartFilters"><i class="fas fa-search me-1"></i>Apply Filters</button>
                    <button type="button" class="btn btn-secondary" id="resetChartFilters"><i class="fas fa-undo me-1"></i>Reset</button>
                </div>
            </form>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">Sales & Revenue (Last 30 Days)</div>
                <div class="card-body">
                    <canvas id="salesRevenueChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">Top Selling Products</div>
                <div class="card-body">
                    <canvas id="topProductsChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">Inventory Stock Levels</div>
                <div class="card-body">
                    <canvas id="inventoryStockChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Generate Sales Report</div>
        <div class="card-body">
            <form class="row g-3" method="GET" action="generate.php">
                <input type="hidden" name="type" value="sales">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Product</label>
                    <select name="product_id" class="form-select">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Format</label>
                    <select name="format" class="form-select" required>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-download me-1"></i>Download</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header bg-info text-white">Generate Inventory Report</div>
        <div class="card-body">
            <form class="row g-3" method="GET" action="generate.php">
                <input type="hidden" name="type" value="inventory">
                <div class="col-md-3">
                    <label class="form-label">Format</label>
                    <select name="format" class="form-select" required>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-download me-1"></i>Download</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let salesRevenueChart, topProductsChart, inventoryStockChart;

function fetchAndRenderCharts() {
    const params = new URLSearchParams();
    const dateFrom = document.getElementById('chartDateFrom').value;
    const dateTo = document.getElementById('chartDateTo').value;
    const productId = document.getElementById('chartProductId').value;
    const userId = document.getElementById('chartUserId').value;
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (productId) params.append('product_id', productId);
    if (userId) params.append('user_id', userId);
    fetch('summary_data.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            // Destroy previous charts if they exist
            if (salesRevenueChart) salesRevenueChart.destroy();
            if (topProductsChart) topProductsChart.destroy();
            if (inventoryStockChart) inventoryStockChart.destroy();
            // Sales & Revenue Chart
            const salesRevenueCtx = document.getElementById('salesRevenueChart').getContext('2d');
            salesRevenueChart = new Chart(salesRevenueCtx, {
                type: 'bar',
                data: {
                    labels: data.sales.labels,
                    datasets: [
                        {
                            label: 'Sales Count',
                            data: data.sales.counts,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Revenue',
                            data: data.sales.revenue,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    stacked: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Sales Count' } },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: { display: true, text: 'Revenue ($)' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
            // Top Products Chart
            const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
            topProductsChart = new Chart(topProductsCtx, {
                type: 'bar',
                data: {
                    labels: data.top_products.labels,
                    datasets: [{
                        label: 'Units Sold',
                        data: data.top_products.units,
                        backgroundColor: 'rgba(255, 206, 86, 0.7)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
            // Inventory Stock Chart
            const inventoryStockCtx = document.getElementById('inventoryStockChart').getContext('2d');
            inventoryStockChart = new Chart(inventoryStockCtx, {
                type: 'bar',
                data: {
                    labels: data.inventory.labels,
                    datasets: [{
                        label: 'Stock',
                        data: data.inventory.stock,
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        });
}

document.getElementById('applyChartFilters').onclick = fetchAndRenderCharts;
document.getElementById('resetChartFilters').onclick = function() {
    document.getElementById('chartDateFrom').value = '';
    document.getElementById('chartDateTo').value = '';
    document.getElementById('chartProductId').value = '';
    document.getElementById('chartUserId').value = '';
    fetchAndRenderCharts();
};
// Set default date range to last 30 days
window.addEventListener('DOMContentLoaded', function() {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - 29);
    document.getElementById('chartDateFrom').value = from.toISOString().slice(0,10);
    document.getElementById('chartDateTo').value = to.toISOString().slice(0,10);
    fetchAndRenderCharts();
});
</script>
<?php require_once '../includes/footer.php'; ?> 