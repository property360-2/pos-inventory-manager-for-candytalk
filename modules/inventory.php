<?php
// Handle AJAX product search at the very top, before any output
session_start();
if (isset($_GET['search_products'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    require_once '../config/database.php';
    $pdo = getDBConnection();
    $search = trim($_GET['search_products']);
    $stmt = $pdo->prepare("SELECT product_id, name, description, price, quantity FROM inventory WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC LIMIT 20");
    $stmt->execute(["%$search%", "%$search%"]);
    $products = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($products);
    exit;
}

require_once '../includes/header.php';
$pdo = getDBConnection();

// Role-based access: Cashier can only view
$isAdmin = ($_SESSION['role'] === 'Admin');

// Fetch all products for display (no pagination needed with AJAX search)
$stmt = $pdo->prepare("SELECT * FROM inventory ORDER BY name ASC");
$stmt->execute();
$products = $stmt->fetchAll();

// Handle actions: add, edit, delete
$action = $_GET['action'] ?? '';
$message = '';

// Add product
if ($isAdmin && $action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $stmt = $pdo->prepare("INSERT INTO inventory (name, description, price, quantity) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $description, $price, $quantity]);
    $message = 'Product added successfully!';
}

// Edit product
if ($isAdmin && $action === 'edit' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $quantity = intval($_POST['quantity']);
        $stmt = $pdo->prepare("UPDATE inventory SET name=?, description=?, price=?, quantity=? WHERE product_id=?");
        $stmt->execute([$name, $description, $price, $quantity, $product_id]);
        $message = 'Product updated successfully!';
    }
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE product_id=?");
    $stmt->execute([$product_id]);
    $editProduct = $stmt->fetch();
}

// Delete product
if ($isAdmin && $action === 'delete' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE product_id=?");
    $stmt->execute([$product_id]);
    header('Location: inventory.php?msg=deleted');
    exit();
}
?>
<div class="container-fluid">
    <h2 class="mb-4">Inventory</h2>
    <?php if ($message || isset($_GET['msg'])): ?>
        <div class="alert alert-success"> <?php echo $message ?: 'Product deleted successfully!'; ?> </div>
    <?php endif; ?>
    <?php if ($isAdmin && ($action === 'add' || ($action === 'edit' && isset($editProduct)))): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <?php echo $action === 'add' ? 'Add New Product' : 'Edit Product'; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo $editProduct['name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price ($)</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?php echo $editProduct['price'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="0" required value="<?php echo $editProduct['quantity'] ?? ''; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo $editProduct['description'] ?? ''; ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Save
                        </button>
                        <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <div class="position-relative">
                    <input type="text" id="inventorySearch" class="form-control" placeholder="Search products..." autocomplete="off">
                    <div class="search-suggestions" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ddd; border-top:none; max-height:200px; overflow-y:auto; z-index:1000;"></div>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary" type="button" onclick="clearSearch()"><i class="fas fa-times"></i> Clear</button>
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary" type="button" onclick="toggleAdvancedSearch()"><i class="fas fa-filter"></i> Advanced</button>
            </div>
        </div>
        
        <!-- Advanced Search Panel -->
        <div id="advancedSearchPanel" class="card mb-3" style="display:none;">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Advanced Search Filters</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Price Range</label>
                        <div class="input-group">
                            <input type="number" id="minPrice" class="form-control" placeholder="Min" step="0.01" min="0">
                            <span class="input-group-text">-</span>
                            <input type="number" id="maxPrice" class="form-control" placeholder="Max" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Stock Level</label>
                        <select id="stockFilter" class="form-select">
                            <option value="">All Stock Levels</option>
                            <option value="in_stock">In Stock (>0)</option>
                            <option value="low_stock">Low Stock (<10)</option>
                            <option value="out_of_stock">Out of Stock (0)</option>
                            <option value="well_stocked">Well Stocked (≥10)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select id="sortBy" class="form-select">
                            <option value="name">Name (A-Z)</option>
                            <option value="name_desc">Name (Z-A)</option>
                            <option value="price_low">Price (Low to High)</option>
                            <option value="price_high">Price (High to Low)</option>
                            <option value="quantity_low">Quantity (Low to High)</option>
                            <option value="quantity_high">Quantity (High to Low)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quick Filters</label>
                        <div class="d-grid gap-1">
                            <button type="button" class="btn btn-sm btn-warning" onclick="filterLowStock()">
                                <i class="fas fa-exclamation-triangle"></i> Low Stock
                            </button>
                            <button type="button" class="btn btn-sm btn-success" onclick="filterInStock()">
                                <i class="fas fa-check"></i> In Stock
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <a href="inventory.php?action=add" class="btn btn-primary mb-3">
                <i class="fas fa-plus me-1"></i>Add Product
            </a>
        <?php endif; ?>
        <div id="loadingSpinner" class="text-center my-4" style="display:none;">
            <div class="spinner-border text-primary" role="status" aria-label="Loading..."></div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle" aria-label="Inventory Table" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Description</th>
                        <th scope="col">Price</th>
                        <th scope="col">Quantity</th>
                        <?php if ($isAdmin): ?><th scope="col">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <?php foreach ($products as $product): ?>
                        <tr tabindex="0" data-product-id="<?php echo $product['product_id']; ?>">
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['description']); ?></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td>
                                <?php echo $product['quantity']; ?>
                                <?php if ($product['quantity'] < 10): ?>
                                    <span class="badge bg-danger ms-1">Low</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td>
                                <a href="inventory.php?action=edit&id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                <a href="inventory.php?action=delete&id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noResults" class="alert alert-info" style="display:none;">
            <i class="fas fa-info-circle me-2"></i>No products found matching your search.
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
        .highlight {
            background-color: #fff3cd;
            font-weight: bold;
        }
        </style>
        
        <script>
        // Asynchronous search functionality
        let searchTimeout;
        let allProducts = <?php echo json_encode($products); ?>;
        let filteredProducts = [...allProducts];
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#inventorySearch')) {
                document.querySelector('.search-suggestions').style.display = 'none';
            }
        });
        
        document.getElementById('inventorySearch').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const searchTerm = e.target.value.trim();
            const suggestionsDiv = document.querySelector('.search-suggestions');
            
            if (searchTerm.length < 2) {
                suggestionsDiv.style.display = 'none';
                applyAdvancedFilters();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                // Filter products locally for instant results
                filteredProducts = allProducts.filter(product => 
                    product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    product.description.toLowerCase().includes(searchTerm.toLowerCase())
                );
                
                // Show suggestions
                suggestionsDiv.innerHTML = '';
                if (filteredProducts.length > 0) {
                    filteredProducts.slice(0, 10).forEach(product => {
                        const div = document.createElement('div');
                        div.className = 'search-suggestion-item';
                        div.innerHTML = `${product.name} - $${product.price} (${product.quantity} in stock)`;
                        div.onclick = function() {
                            document.getElementById('inventorySearch').value = product.name;
                            suggestionsDiv.style.display = 'none';
                            filterTableBySearch(product.name);
                        };
                        suggestionsDiv.appendChild(div);
                    });
                    suggestionsDiv.style.display = 'block';
                } else {
                    suggestionsDiv.innerHTML = '<div class="search-suggestion-item">No products found</div>';
                    suggestionsDiv.style.display = 'block';
                }
                
                // Apply advanced filters and update table
                applyAdvancedFilters();
            }, 300);
        });
        
        // Advanced search event listeners
        document.getElementById('minPrice').addEventListener('input', applyAdvancedFilters);
        document.getElementById('maxPrice').addEventListener('input', applyAdvancedFilters);
        document.getElementById('stockFilter').addEventListener('change', applyAdvancedFilters);
        document.getElementById('sortBy').addEventListener('change', applyAdvancedFilters);
        
        function filterTableBySearch(searchTerm) {
            filteredProducts = allProducts.filter(product => 
                product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                product.description.toLowerCase().includes(searchTerm.toLowerCase())
            );
            applyAdvancedFilters();
        }
        
        function applyAdvancedFilters() {
            const searchTerm = document.getElementById('inventorySearch').value.trim();
            const minPrice = parseFloat(document.getElementById('minPrice').value) || 0;
            const maxPrice = parseFloat(document.getElementById('maxPrice').value) || Infinity;
            const stockFilter = document.getElementById('stockFilter').value;
            const sortBy = document.getElementById('sortBy').value;
            
            // Start with search filter
            let products = searchTerm.length < 2 ? [...allProducts] : 
                allProducts.filter(product => 
                    product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    product.description.toLowerCase().includes(searchTerm.toLowerCase())
                );
            
            // Apply price filter
            products = products.filter(product => {
                const price = parseFloat(product.price);
                return price >= minPrice && price <= maxPrice;
            });
            
            // Apply stock filter
            if (stockFilter) {
                products = products.filter(product => {
                    const quantity = parseInt(product.quantity);
                    switch (stockFilter) {
                        case 'in_stock': return quantity > 0;
                        case 'low_stock': return quantity > 0 && quantity < 10;
                        case 'out_of_stock': return quantity === 0;
                        case 'well_stocked': return quantity >= 10;
                        default: return true;
                    }
                });
            }
            
            // Apply sorting
            products.sort((a, b) => {
                switch (sortBy) {
                    case 'name':
                        return a.name.localeCompare(b.name);
                    case 'name_desc':
                        return b.name.localeCompare(a.name);
                    case 'price_low':
                        return parseFloat(a.price) - parseFloat(b.price);
                    case 'price_high':
                        return parseFloat(b.price) - parseFloat(a.price);
                    case 'quantity_low':
                        return parseInt(a.quantity) - parseInt(b.quantity);
                    case 'quantity_high':
                        return parseInt(b.quantity) - parseInt(a.quantity);
                    default:
                        return 0;
                }
            });
            
            filteredProducts = products;
            updateTableDisplay();
        }
        
        function updateTableDisplay() {
            const tbody = document.getElementById('inventoryTableBody');
            const noResults = document.getElementById('noResults');
            
            tbody.innerHTML = '';
            
            if (filteredProducts.length === 0) {
                noResults.style.display = 'block';
                return;
            }
            
            noResults.style.display = 'none';
            
            filteredProducts.forEach(product => {
                const row = document.createElement('tr');
                row.setAttribute('data-product-id', product.product_id);
                row.setAttribute('tabindex', '0');
                
                const lowStockBadge = product.quantity < 10 ? 
                    '<span class="badge bg-danger ms-1">Low</span>' : '';
                
                const adminActions = <?php echo $isAdmin ? 'true' : 'false'; ?> ? 
                    `<td>
                        <a href="inventory.php?action=edit&id=${product.product_id}" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                        <a href="inventory.php?action=delete&id=${product.product_id}" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></a>
                    </td>` : '';
                
                row.innerHTML = `
                    <td>${escapeHtml(product.name)}</td>
                    <td>${escapeHtml(product.description)}</td>
                    <td>$${parseFloat(product.price).toFixed(2)}</td>
                    <td>${product.quantity} ${lowStockBadge}</td>
                    ${adminActions}
                `;
                
                tbody.appendChild(row);
            });
        }
        
        function showAllProducts() {
            filteredProducts = [...allProducts];
            updateTableDisplay();
        }
        
        function clearSearch() {
            document.getElementById('inventorySearch').value = '';
            document.getElementById('minPrice').value = '';
            document.getElementById('maxPrice').value = '';
            document.getElementById('stockFilter').value = '';
            document.getElementById('sortBy').value = 'name';
            document.querySelector('.search-suggestions').style.display = 'none';
            showAllProducts();
        }
        
        function toggleAdvancedSearch() {
            const panel = document.getElementById('advancedSearchPanel');
            const button = document.querySelector('button[onclick="toggleAdvancedSearch()"]');
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
        
        function filterLowStock() {
            document.getElementById('stockFilter').value = 'low_stock';
            document.getElementById('inventorySearch').value = '';
            applyAdvancedFilters();
        }
        
        function filterInStock() {
            document.getElementById('stockFilter').value = 'in_stock';
            document.getElementById('inventorySearch').value = '';
            applyAdvancedFilters();
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