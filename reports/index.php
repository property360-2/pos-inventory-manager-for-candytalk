<?php
require_once '../includes/header.php';
$pdo = getDBConnection();

// Fetch products and users for filters (only for report generation, not charts)
$stmt = $pdo->prepare("SELECT product_id, name FROM inventory ORDER BY name ASC");
$stmt->execute();
$products = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT user_id, name FROM users ORDER BY name ASC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<div class="container-fluid">
    <h2 class="mb-4">Reports & Forecasting</h2>
    
    <!-- Real-time Analytics Dashboard -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-chart-line me-2"></i>Sales & Revenue Trends
                    <small class="float-end">Auto-updating</small>
                </div>
                <div class="card-body">
                    <canvas id="salesRevenueChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-chart-bar me-2"></i>Top Selling Products
                    <small class="float-end">Auto-updating</small>
                </div>
                <div class="card-body">
                    <canvas id="topProductsChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Forecasting Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-crystal-ball me-2"></i>Sales Forecast (Next 30 Days)
                    <small class="float-end">AI-powered</small>
                </div>
                <div class="card-body">
                    <canvas id="salesForecastChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-warehouse me-2"></i>Inventory Forecast
                    <small class="float-end">Stock predictions</small>
                </div>
                <div class="card-body">
                    <canvas id="inventoryForecastChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Analytics -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alerts
                    <small class="float-end">Real-time</small>
                </div>
                <div class="card-body">
                    <canvas id="lowStockChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-chart-pie me-2"></i>Revenue Distribution
                    <small class="float-end">Auto-updating</small>
                </div>
                <div class="card-body">
                    <canvas id="revenueDistributionChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Generation Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Generate Reports</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Sales Report</h6>
                    <form class="row g-3" method="GET" action="generate.php">
                        <input type="hidden" name="type" value="sales">
                        <div class="col-md-6">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select">
                                <option value="">All Products</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">User</label>
                            <select name="user_id" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Format</label>
                            <select name="format" class="form-select" required>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        <div class="col-md-6 align-self-end">
                            <button type="submit" class="btn btn-success"><i class="fas fa-download me-1"></i>Download</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-6">
                    <h6>Inventory Report</h6>
                    <form class="row g-3" method="GET" action="generate.php">
                        <input type="hidden" name="type" value="inventory">
                        <div class="col-md-6">
                            <label class="form-label">Format</label>
                            <select name="format" class="form-select" required>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        <div class="col-md-6 align-self-end">
                            <button type="submit" class="btn btn-success"><i class="fas fa-download me-1"></i>Download</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let salesRevenueChart, topProductsChart, salesForecastChart, inventoryForecastChart, lowStockChart, revenueDistributionChart;
let chartUpdateInterval;

// Auto-updating charts every 30 seconds
function startAutoUpdates() {
    chartUpdateInterval = setInterval(updateAllCharts, 30000); // 30 seconds
}

function stopAutoUpdates() {
    if (chartUpdateInterval) {
        clearInterval(chartUpdateInterval);
    }
}

function updateAllCharts() {
    updateSalesRevenueChart();
    updateTopProductsChart();
    updateSalesForecastChart();
    updateInventoryForecastChart();
    updateLowStockChart();
    updateRevenueDistributionChart();
}

// Sales & Revenue Chart
function updateSalesRevenueChart() {
    fetch('forecast_data.php?type=sales_revenue')
        .then(response => response.json())
        .then(data => {
            if (salesRevenueChart) salesRevenueChart.destroy();
            
            const ctx = document.getElementById('salesRevenueChart').getContext('2d');
            salesRevenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Sales Count',
                            data: data.sales_count,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Revenue ($)',
                            data: data.revenue,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            title: { display: true, text: 'Sales Count' } 
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: { display: true, text: 'Revenue ($)' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        })
        .catch(error => console.error('Error updating sales chart:', error));
}

// Top Products Chart
function updateTopProductsChart() {
    fetch('forecast_data.php?type=top_products')
        .then(response => response.json())
        .then(data => {
            if (topProductsChart) topProductsChart.destroy();
            
            const ctx = document.getElementById('topProductsChart').getContext('2d');
            topProductsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Units Sold',
                        data: data.units,
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
        })
        .catch(error => console.error('Error updating top products chart:', error));
}

// Sales Forecast Chart
function updateSalesForecastChart() {
    fetch('forecast_data.php?type=sales_forecast')
        .then(response => response.json())
        .then(data => {
            if (salesForecastChart) salesForecastChart.destroy();
            
            const ctx = document.getElementById('salesForecastChart').getContext('2d');
            salesForecastChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Historical Sales (Last 30 Days)',
                            data: data.historical,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            borderWidth: 4,
                            tension: 0.4,
                            fill: false,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Forecasted Sales (Next 30 Days)',
                            data: data.forecast,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            borderWidth: 4,
                            borderDash: [10, 5],
                            tension: 0.4,
                            fill: false,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointStyle: 'circle'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { 
                        legend: { 
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    if (context.parsed.y !== null) {
                                        return context.dataset.label + ': ' + context.parsed.y + ' sales';
                                    }
                                    return null;
                                }
                            }
                        }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Sales' }
                        },
                        x: {
                            grid: {
                                color: function(context) {
                                    // Add a vertical line to separate historical from forecast
                                    if (context.tick.value === 29) {
                                        return 'rgba(255, 0, 0, 0.5)';
                                    }
                                    return 'rgba(0, 0, 0, 0.1)';
                                }
                            },
                            ticks: {
                                callback: function(value, index) {
                                    // Add visual separator between historical and forecast
                                    if (index === 29) {
                                        return '│ ' + this.getLabelForValue(value);
                                    }
                                    return this.getLabelForValue(value);
                                }
                            }
                        }
                    },
                    elements: {
                        point: {
                            hoverBackgroundColor: function(context) {
                                return context.dataset.borderColor;
                            }
                        }
                    }
                }
            });
        })
        .catch(error => console.error('Error updating sales forecast chart:', error));
}

// Inventory Forecast Chart - Simplified
function updateInventoryForecastChart() {
    fetch('forecast_data.php?type=inventory_forecast')
        .then(response => response.json())
        .then(data => {
            if (inventoryForecastChart) inventoryForecastChart.destroy();
            
            const ctx = document.getElementById('inventoryForecastChart').getContext('2d');
            
            // Create color-coded bars based on stock status
            const colors = data.stock_status.map(status => {
                switch(status) {
                    case 'Out of Stock': return 'rgba(255, 99, 132, 0.8)';
                    case 'Low Stock': return 'rgba(255, 206, 86, 0.8)';
                    case 'Medium Stock': return 'rgba(54, 162, 235, 0.8)';
                    case 'Well Stocked': return 'rgba(75, 192, 192, 0.8)';
                    case 'No Sales': return 'rgba(201, 203, 207, 0.8)';
                    default: return 'rgba(201, 203, 207, 0.8)';
                }
            });
            
            inventoryForecastChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Current Stock',
                        data: data.current_stock,
                        backgroundColor: colors,
                        borderColor: colors.map(color => color.replace('0.8', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterBody: function(context) {
                                    const index = context[0].dataIndex;
                                    const daysUntilEmpty = data.days_until_empty[index];
                                    const status = data.stock_status[index];
                                    
                                    let message = `Status: ${status}`;
                                    if (daysUntilEmpty > 0 && daysUntilEmpty < 999) {
                                        message += `\nDays until empty: ${daysUntilEmpty}`;
                                    } else if (daysUntilEmpty === 999) {
                                        message += '\nNo recent sales';
                                    } else if (daysUntilEmpty === 0) {
                                        message += '\nOut of stock';
                                    }
                                    
                                    return message;
                                }
                            }
                        }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            title: { display: true, text: 'Current Stock Level' }
                        }
                    }
                }
            });
        })
        .catch(error => console.error('Error updating inventory forecast chart:', error));
}

// Low Stock Chart
function updateLowStockChart() {
    fetch('forecast_data.php?type=low_stock')
        .then(response => response.json())
        .then(data => {
            if (lowStockChart) lowStockChart.destroy();
            
            const ctx = document.getElementById('lowStockChart').getContext('2d');
            lowStockChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        })
        .catch(error => console.error('Error updating low stock chart:', error));
}

// Revenue Distribution Chart
function updateRevenueDistributionChart() {
    fetch('forecast_data.php?type=revenue_distribution')
        .then(response => response.json())
        .then(data => {
            if (revenueDistributionChart) revenueDistributionChart.destroy();
            
            const ctx = document.getElementById('revenueDistributionChart').getContext('2d');
            revenueDistributionChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        })
        .catch(error => console.error('Error updating revenue distribution chart:', error));
}

// Initialize all charts on page load
window.addEventListener('DOMContentLoaded', function() {
    updateAllCharts();
    startAutoUpdates();
});

// Stop auto-updates when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoUpdates();
    } else {
        startAutoUpdates();
    }
});
</script>
<?php require_once '../includes/footer.php'; ?> 