<?php
// Handle AJAX sales search at the very top, before any output
session_start();
if (isset($_GET['search_sales'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    require_once '../config/database.php';
    $pdo = getDBConnection();
    $search = trim($_GET['search_sales']);
    $date = trim($_GET['date'] ?? '');
    
    $where = '1=1';
    $params = [];
    if ($search !== '') {
        $where .= ' AND (u.name LIKE ? OR s.sale_id LIKE ? OR s.sale_date LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($date !== '') {
        $where .= ' AND DATE(s.sale_date) = ?';
        $params[] = $date;
    }
    
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as cashier_name
        FROM sales s
        JOIN users u ON s.user_id = u.user_id
        WHERE $where
        ORDER BY s.sale_date DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($sales);
    exit;
}

// Handle AJAX product search at the very top, before any output
if (isset($_GET['search_products'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    require_once '../config/database.php';
    $pdo = getDBConnection();
    $search = trim($_GET['search_products']);
    $stmt = $pdo->prepare("SELECT product_id, name, price, quantity FROM inventory WHERE name LIKE ? AND quantity > 0 ORDER BY name ASC LIMIT 10");
    $stmt->execute(["%$search%"]);
    $products = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($products);
    exit;
}

require_once '../includes/header.php';
$pdo = getDBConnection();
$isAdmin = ($_SESSION['role'] === 'Admin');
$action = $_GET['action'] ?? '';
$message = '';

// Handle view sale details
if ($action === 'view' && isset($_GET['id'])) {
    $sale_id = intval($_GET['id']);
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as cashier_name
        FROM sales s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.sale_id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();
    
    if ($sale) {
        $stmt = $pdo->prepare("
            SELECT si.*, i.name as product_name, i.price as unit_price
            FROM sale_items si
            JOIN inventory i ON si.product_id = i.product_id
            WHERE si.sale_id = ?
        ");
        $stmt->execute([$sale_id]);
        $saleItems = $stmt->fetchAll();
    }
}

// Fetch all sales for display (no pagination needed with AJAX search)
$stmt = $pdo->prepare("
    SELECT s.*, u.name as cashier_name
    FROM sales s
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.sale_date DESC
");
$stmt->execute();
$sales = $stmt->fetchAll();

// Fetch products for add form
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE quantity > 0 ORDER BY name ASC");
$stmt->execute();
$products = $stmt->fetchAll();

// Handle add sale
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $sale_date = date('Y-m-d H:i:s');
    $items = $_POST['items'] ?? [];
    $total_amount = 0;
    $valid_items = [];
    foreach ($items as $item) {
        $product_id = intval($item['product_id'] ?? 0);
        $quantity = intval($item['quantity'] ?? 0);
        $subtotal = floatval($item['subtotal'] ?? 0);
        if ($product_id > 0 && $quantity > 0) {
            $total_amount += $subtotal;
            $valid_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];
        }
    }
    if (count($valid_items) > 0) {
        // Insert sale
        $stmt = $pdo->prepare("INSERT INTO sales (user_id, sale_date, total_amount) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $sale_date, $total_amount]);
        $sale_id = $pdo->lastInsertId();
        // Insert sale items and update inventory
        foreach ($valid_items as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $subtotal = $item['subtotal'];
            $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sale_id, $product_id, $quantity, $subtotal]);
            // Update inventory
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?");
            $stmt->execute([$quantity, $product_id]);
        }
        $message = 'Sale recorded successfully!';
    } else {
        $message = '<span class="text-danger">No valid products selected for sale.</span>';
    }
}

// Handle delete sale (Admin only)
if ($isAdmin && $action === 'delete' && isset($_GET['id'])) {
    $sale_id = intval($_GET['id']);
    // Restore inventory before deleting
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll();
    foreach ($items as $item) {
        $stmt2 = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ?");
        $stmt2->execute([$item['quantity'], $item['product_id']]);
    }
    // Delete sale items and sale
    $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$sale_id]);
    $pdo->prepare("DELETE FROM sales WHERE sale_id = ?")->execute([$sale_id]);
    $message = 'Sale deleted and inventory restored.';
}
?>
<div class="container-fluid">
    <h2 class="mb-4">Sales</h2>
    <?php if ($message): ?>
        <div class="alert alert-success"> <?php echo $message; ?> </div>
    <?php endif; ?>
    
    <?php if ($action === 'view' && isset($sale)): ?>
        <!-- Sale Details View -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sale Details - #<?php echo $sale['sale_id']; ?></h5>
                <a href="sales.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Back to Sales</a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <strong>Sale ID:</strong> #<?php echo $sale['sale_id']; ?><br>
                        <strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($sale['sale_date'])); ?><br>
                        <strong>Cashier:</strong> <?php echo htmlspecialchars($sale['cashier_name']); ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <strong>Total Amount:</strong> $<?php echo number_format($sale['total_amount'], 2); ?>
                    </div>
                </div>
                
                <h6>Sale Items:</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saleItems as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                <td><strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="mt-3">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Receipt</button>
                    <a href="sales.php" class="btn btn-secondary">Back to Sales</a>
                </div>
            </div>
        </div>
    <?php elseif ($action === 'add'): ?>
        <!-- Add Sale Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">New Sale</div>
            <div class="card-body">
                <form method="POST" id="saleForm">
                    <div id="sale-items">
                        <div class="row mb-2 sale-item-row">
                            <div class="col-md-5">
                                <div class="position-relative">
                                    <input type="text" class="form-control product-search" placeholder="Search products..." autocomplete="off">
                                    <input type="hidden" name="items[0][product_id]" class="product-id-input" required>
                                    <div class="product-suggestions" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ddd; border-top:none; max-height:200px; overflow-y:auto; z-index:1000;"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="items[0][quantity]" class="form-control quantity-input" min="1" value="1" required>
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="items[0][subtotal]" class="form-control subtotal-input" readonly>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-danger remove-item" disabled><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary mb-3" id="addItemBtn"><i class="fas fa-plus"></i> Add Item</button>
                    <div class="mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="text" id="totalAmount" class="form-control" readonly>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Save Sale</button>
                    <a href="sales.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
        <style>
        .product-suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .product-suggestion-item:hover {
            background-color: #f8f9fa;
        }
        .product-suggestion-item.selected {
            background-color: #007bff;
            color: white;
        }
        </style>
        <script>
        // Product search functionality
        let searchTimeout;
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.product-search')) {
                document.querySelectorAll('.product-suggestions').forEach(el => el.style.display = 'none');
            }
        });
        
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('product-search')) {
                clearTimeout(searchTimeout);
                const searchTerm = e.target.value.trim();
                const suggestionsDiv = e.target.parentNode.querySelector('.product-suggestions');
                
                if (searchTerm.length < 2) {
                    suggestionsDiv.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    fetch(`sales.php?search_products=${encodeURIComponent(searchTerm)}`)
                        .then(response => response.json())
                        .then(products => {
                            suggestionsDiv.innerHTML = '';
                            if (products.length > 0) {
                                products.forEach(product => {
                                    const div = document.createElement('div');
                                    div.className = 'product-suggestion-item';
                                    div.innerHTML = `${product.name} - $${product.price} (${product.quantity} in stock)`;
                                    div.dataset.productId = product.product_id;
                                    div.dataset.price = product.price;
                                    div.dataset.max = product.quantity;
                                    div.onclick = function() {
                                        selectProduct(e.target, product);
                                    };
                                    suggestionsDiv.appendChild(div);
                                });
                                suggestionsDiv.style.display = 'block';
                            } else {
                                suggestionsDiv.innerHTML = '<div class="product-suggestion-item">No products found</div>';
                                suggestionsDiv.style.display = 'block';
                            }
                        });
                }, 300);
            }
        });
        
        function selectProduct(input, product) {
            input.value = product.name;
            input.parentNode.querySelector('.product-id-input').value = product.product_id;
            input.parentNode.querySelector('.product-suggestions').style.display = 'none';
            // Set price and max stock on hidden input for later use
            const row = input.closest('.sale-item-row');
            const productIdInput = row.querySelector('.product-id-input');
            productIdInput.dataset.price = product.price;
            productIdInput.dataset.max = product.quantity;
            // Set quantity input min/max
            const qtyInput = row.querySelector('.quantity-input');
            qtyInput.min = 1;
            qtyInput.max = product.quantity > 0 ? product.quantity : 1;
            if (parseInt(qtyInput.value) > parseInt(qtyInput.max)) {
                qtyInput.value = qtyInput.max;
            }
            if (parseInt(qtyInput.value) < 1) {
                qtyInput.value = 1;
            }
            updateSubtotal(row);
            updateTotal();
        }
        
        // Dynamic add/remove items and auto-calculate subtotal/total
        let itemIndex = 1;
        document.getElementById('addItemBtn').onclick = function() {
            const row = document.querySelector('.sale-item-row').cloneNode(true);
            row.querySelectorAll('input').forEach(el => {
                if (el.name.includes('product_id')) {
                    el.name = `items[${itemIndex}][product_id]`;
                    el.value = '';
                }
                if (el.name.includes('quantity')) {
                    el.name = `items[${itemIndex}][quantity]`;
                    el.value = 1;
                }
                if (el.name.includes('subtotal')) {
                    el.name = `items[${itemIndex}][subtotal]`;
                    el.value = '';
                }
                if (el.classList.contains('product-search')) {
                    el.value = '';
                    el.name = '';
                }
            });
            row.querySelector('.remove-item').disabled = false;
            document.getElementById('sale-items').appendChild(row);
            itemIndex++;
        };
        
        document.getElementById('sale-items').addEventListener('click', function(e) {
            if (e.target.closest('.remove-item')) {
                e.target.closest('.sale-item-row').remove();
                updateTotal();
            }
        });
        
        document.getElementById('sale-items').addEventListener('change', function(e) {
            if (e.target.classList.contains('quantity-input')) {
                updateSubtotal(e.target.closest('.sale-item-row'));
                updateTotal();
            }
        });
        
        function updateSubtotal(row) {
            const productIdInput = row.querySelector('.product-id-input');
            const price = parseFloat(productIdInput.dataset.price || 0);
            const max = parseInt(productIdInput.dataset.max || 0);
            const qtyInput = row.querySelector('.quantity-input');
            let qty = parseInt(qtyInput.value) || 1;
            if (qty > max) qty = max;
            qtyInput.max = max;
            qtyInput.value = qty;
            row.querySelector('.subtotal-input').value = (price * qty).toFixed(2);
        }
        
        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.subtotal-input').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('totalAmount').value = total.toFixed(2);
        }
        
        // Prevent form submission if any product_id is missing
        document.getElementById('saleForm').addEventListener('submit', function(e) {
            let valid = true;
            document.querySelectorAll('.product-id-input').forEach(function(input) {
                if (!input.value || isNaN(parseInt(input.value))) {
                    valid = false;
                }
            });
            if (!valid) {
                e.preventDefault();
                alert('Please select a valid product for each sale item.');
            }
        });
        </script>
    <?php else: ?>
        <!-- Sales List -->
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <div class="position-relative">
                    <input type="text" id="salesSearch" class="form-control" placeholder="Search sales..." autocomplete="off">
                    <div class="search-suggestions" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ddd; border-top:none; max-height:200px; overflow-y:auto; z-index:1000;"></div>
                </div>
            </div>
            <div class="col-md-2">
                <input type="date" id="salesDate" class="form-control" placeholder="Filter by date">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary" type="button" onclick="clearSalesSearch()"><i class="fas fa-times"></i> Clear</button>
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary" type="button" onclick="toggleAdvancedSalesSearch()"><i class="fas fa-filter"></i> Advanced</button>
            </div>
            <div class="col-md-2">
                <a href="sales.php?action=add" class="btn btn-success"><i class="fas fa-plus"></i> New Sale</a>
            </div>
        </div>
        
        <!-- Advanced Sales Search Panel -->
        <div id="advancedSalesSearchPanel" class="card mb-3" style="display:none;">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Advanced Sales Search Filters</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" id="startDate" class="form-control">
                            <span class="input-group-text">to</span>
                            <input type="date" id="endDate" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount Range</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="minAmount" class="form-control" placeholder="Min" step="0.01" min="0">
                            <span class="input-group-text">-</span>
                            <input type="number" id="maxAmount" class="form-control" placeholder="Max" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cashier</label>
                        <select id="cashierFilter" class="form-select">
                            <option value="">All Cashiers</option>
                            <?php 
                            $cashiers = array_unique(array_column($sales, 'cashier_name'));
                            foreach ($cashiers as $cashier): 
                            ?>
                                <option value="<?php echo htmlspecialchars($cashier); ?>"><?php echo htmlspecialchars($cashier); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select id="salesSortBy" class="form-select">
                            <option value="date_desc">Date (Newest First)</option>
                            <option value="date_asc">Date (Oldest First)</option>
                            <option value="amount_high">Amount (High to Low)</option>
                            <option value="amount_low">Amount (Low to High)</option>
                            <option value="cashier">Cashier Name</option>
                            <option value="sale_id">Sale ID</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <label class="form-label">Quick Filters</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-info" onclick="filterToday()">
                                <i class="fas fa-calendar-day"></i> Today
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" onclick="filterThisWeek()">
                                <i class="fas fa-calendar-week"></i> This Week
                            </button>
                            <button type="button" class="btn btn-sm btn-success" onclick="filterThisMonth()">
                                <i class="fas fa-calendar-alt"></i> This Month
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount Filters</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="filterHighValue()">
                                <i class="fas fa-dollar-sign"></i> High Value (>$50)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterLowValue()">
                                <i class="fas fa-dollar-sign"></i> Low Value (<$10)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="loadingSpinner" class="text-center my-4" style="display:none;">
            <div class="spinner-border text-primary" role="status" aria-label="Loading..."></div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle" aria-label="Sales Table" id="salesTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Sale ID</th>
                        <th scope="col">Date</th>
                        <th scope="col">Cashier</th>
                        <th scope="col">Total Amount</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="salesTableBody">
                    <?php foreach ($sales as $sale): ?>
                        <tr tabindex="0" data-sale-id="<?php echo $sale['sale_id']; ?>">
                            <td><?php echo $sale['sale_id']; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($sale['sale_date'])); ?></td>
                            <td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                            <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                            <td>
                                <a href="sales.php?action=view&id=<?php echo $sale['sale_id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                <?php if ($isAdmin): ?>
                                <a href="sales.php?action=delete&id=<?php echo $sale['sale_id']; ?>" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noResults" class="alert alert-info" style="display:none;">
            <i class="fas fa-info-circle me-2"></i>No sales found matching your search.
        </div>
        
        <style>
        .search-suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .search-suggestion-item:hover {
            background-color: #f8f9fa;
        }
        .search-suggestion-item.selected {
            background-color: #007bff;
            color: white;
        }
        </style>
        
        <script>
        // Asynchronous sales search functionality
        let salesSearchTimeout;
        let allSales = <?php echo json_encode($sales); ?>;
        let filteredSales = [...allSales];
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#salesSearch')) {
                document.querySelector('.search-suggestions').style.display = 'none';
            }
        });
        
        document.getElementById('salesSearch').addEventListener('input', function(e) {
            clearTimeout(salesSearchTimeout);
            const searchTerm = e.target.value.trim();
            const dateFilter = document.getElementById('salesDate').value;
            
            salesSearchTimeout = setTimeout(() => {
                performSalesSearch(searchTerm, dateFilter);
            }, 300);
        });
        
        document.getElementById('salesDate').addEventListener('change', function(e) {
            const searchTerm = document.getElementById('salesSearch').value.trim();
            const dateFilter = e.target.value;
            performSalesSearch(searchTerm, dateFilter);
        });
        
        // Advanced search event listeners
        document.getElementById('startDate').addEventListener('change', applyAdvancedSalesFilters);
        document.getElementById('endDate').addEventListener('change', applyAdvancedSalesFilters);
        document.getElementById('minAmount').addEventListener('input', applyAdvancedSalesFilters);
        document.getElementById('maxAmount').addEventListener('input', applyAdvancedSalesFilters);
        document.getElementById('cashierFilter').addEventListener('change', applyAdvancedSalesFilters);
        document.getElementById('salesSortBy').addEventListener('change', applyAdvancedSalesFilters);
        
        function performSalesSearch(searchTerm, dateFilter) {
            const params = new URLSearchParams();
            if (searchTerm) params.append('search_sales', searchTerm);
            if (dateFilter) params.append('date', dateFilter);
            
            fetch(`sales.php?${params.toString()}`)
                .then(response => response.json())
                .then(sales => {
                    filteredSales = sales;
                    applyAdvancedSalesFilters();
                })
                .catch(error => {
                    console.error('Error searching sales:', error);
                    // Fallback to local filtering
                    filterSalesLocally(searchTerm, dateFilter);
                });
        }
        
        function filterSalesLocally(searchTerm, dateFilter) {
            filteredSales = allSales.filter(sale => {
                const matchesSearch = !searchTerm || 
                    sale.sale_id.toString().includes(searchTerm) ||
                    sale.cashier_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    sale.sale_date.includes(searchTerm);
                
                const matchesDate = !dateFilter || 
                    sale.sale_date.startsWith(dateFilter);
                
                return matchesSearch && matchesDate;
            });
            applyAdvancedSalesFilters();
        }
        
        function applyAdvancedSalesFilters() {
            const searchTerm = document.getElementById('salesSearch').value.trim();
            const singleDate = document.getElementById('salesDate').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const minAmount = parseFloat(document.getElementById('minAmount').value) || 0;
            const maxAmount = parseFloat(document.getElementById('maxAmount').value) || Infinity;
            const cashierFilter = document.getElementById('cashierFilter').value;
            const sortBy = document.getElementById('salesSortBy').value;
            
            // Start with basic search
            let sales = searchTerm.length < 2 ? [...allSales] : 
                allSales.filter(sale => 
                    sale.sale_id.toString().includes(searchTerm) ||
                    sale.cashier_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    sale.sale_date.includes(searchTerm)
                );
            
            // Apply date filters
            if (singleDate) {
                sales = sales.filter(sale => sale.sale_date.startsWith(singleDate));
            } else if (startDate || endDate) {
                sales = sales.filter(sale => {
                    const saleDate = sale.sale_date.split(' ')[0]; // Get date part only
                    if (startDate && endDate) {
                        return saleDate >= startDate && saleDate <= endDate;
                    } else if (startDate) {
                        return saleDate >= startDate;
                    } else if (endDate) {
                        return saleDate <= endDate;
                    }
                    return true;
                });
            }
            
            // Apply amount filter
            sales = sales.filter(sale => {
                const amount = parseFloat(sale.total_amount);
                return amount >= minAmount && amount <= maxAmount;
            });
            
            // Apply cashier filter
            if (cashierFilter) {
                sales = sales.filter(sale => sale.cashier_name === cashierFilter);
            }
            
            // Apply sorting
            sales.sort((a, b) => {
                switch (sortBy) {
                    case 'date_desc':
                        return new Date(b.sale_date) - new Date(a.sale_date);
                    case 'date_asc':
                        return new Date(a.sale_date) - new Date(b.sale_date);
                    case 'amount_high':
                        return parseFloat(b.total_amount) - parseFloat(a.total_amount);
                    case 'amount_low':
                        return parseFloat(a.total_amount) - parseFloat(b.total_amount);
                    case 'cashier':
                        return a.cashier_name.localeCompare(b.cashier_name);
                    case 'sale_id':
                        return a.sale_id - b.sale_id;
                    default:
                        return 0;
                }
            });
            
            filteredSales = sales;
            updateSalesTableDisplay();
        }
        
        function updateSalesTableDisplay() {
            const tbody = document.getElementById('salesTableBody');
            const noResults = document.getElementById('noResults');
            
            tbody.innerHTML = '';
            
            if (filteredSales.length === 0) {
                noResults.style.display = 'block';
                return;
            }
            
            noResults.style.display = 'none';
            
            filteredSales.forEach(sale => {
                const row = document.createElement('tr');
                row.setAttribute('data-sale-id', sale.sale_id);
                row.setAttribute('tabindex', '0');
                
                const adminActions = <?php echo $isAdmin ? 'true' : 'false'; ?> ? 
                    `<a href="sales.php?action=delete&id=${sale.sale_id}" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></a>` : '';
                
                row.innerHTML = `
                    <td>${sale.sale_id}</td>
                    <td>${new Date(sale.sale_date).toLocaleString('en-US', {year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'})}</td>
                    <td>${escapeHtml(sale.cashier_name)}</td>
                    <td>$${parseFloat(sale.total_amount).toFixed(2)}</td>
                    <td>
                        <a href="sales.php?action=view&id=${sale.sale_id}" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                        ${adminActions}
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        function clearSalesSearch() {
            document.getElementById('salesSearch').value = '';
            document.getElementById('salesDate').value = '';
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('minAmount').value = '';
            document.getElementById('maxAmount').value = '';
            document.getElementById('cashierFilter').value = '';
            document.getElementById('salesSortBy').value = 'date_desc';
            document.querySelector('.search-suggestions').style.display = 'none';
            filteredSales = [...allSales];
            updateSalesTableDisplay();
        }
        
        function toggleAdvancedSalesSearch() {
            const panel = document.getElementById('advancedSalesSearchPanel');
            const button = document.querySelector('button[onclick="toggleAdvancedSalesSearch()"]');
            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                button.innerHTML = '<i class="fas fa-times"></i> Hide Advanced';
                button.classList.remove('btn-secondary');
                button.classList.add('btn-info');
            } else {
                panel.style.display = 'none';
                button.innerHTML = '<i class="fas fa-filter"></i> Advanced';
                button.classList.remove('btn-info');
                button.classList.add('btn-secondary');
            }
        }
        
        function filterToday() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('startDate').value = today;
            document.getElementById('endDate').value = today;
            document.getElementById('salesDate').value = '';
            applyAdvancedSalesFilters();
        }
        
        function filterThisWeek() {
            const today = new Date();
            const startOfWeek = new Date(today.setDate(today.getDate() - today.getDay()));
            const endOfWeek = new Date(today.setDate(today.getDate() - today.getDay() + 6));
            
            document.getElementById('startDate').value = startOfWeek.toISOString().split('T')[0];
            document.getElementById('endDate').value = endOfWeek.toISOString().split('T')[0];
            document.getElementById('salesDate').value = '';
            applyAdvancedSalesFilters();
        }
        
        function filterThisMonth() {
            const today = new Date();
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            document.getElementById('startDate').value = startOfMonth.toISOString().split('T')[0];
            document.getElementById('endDate').value = endOfMonth.toISOString().split('T')[0];
            document.getElementById('salesDate').value = '';
            applyAdvancedSalesFilters();
        }
        
        function filterHighValue() {
            document.getElementById('minAmount').value = '50';
            document.getElementById('maxAmount').value = '';
            applyAdvancedSalesFilters();
        }
        
        function filterLowValue() {
            document.getElementById('minAmount').value = '';
            document.getElementById('maxAmount').value = '10';
            applyAdvancedSalesFilters();
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        </script>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?> 