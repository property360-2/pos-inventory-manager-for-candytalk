<?php
// Handle AJAX users search at the very top, before any output
session_start();
if (isset($_GET['search_users'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    require_once '../config/database.php';
    $pdo = getDBConnection();
    $search = trim($_GET['search_users']);
    $stmt = $pdo->prepare("SELECT user_id, username, name, role FROM users WHERE username LIKE ? OR name LIKE ? ORDER BY name ASC LIMIT 20");
    $stmt->execute(["%$search%", "%$search%"]);
    $users = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}

require_once '../includes/header.php';
if ($_SESSION['role'] !== 'Admin') {
    header('Location: ../dashboard.php');
    exit();
}
$pdo = getDBConnection();
$action = $_GET['action'] ?? '';
$message = '';

// Add user
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $role = $_POST['role'] === 'Admin' ? 'Admin' : 'Cashier';
    $password = $_POST['password'];
    if ($username && $name && $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $name, $hashed, $role]);
        $message = 'User added successfully!';
    }
}

// Edit user
if ($action === 'edit' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $role = $_POST['role'] === 'Admin' ? 'Admin' : 'Cashier';
        $password = $_POST['password'];
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name=?, role=?, password=? WHERE user_id=?");
            $stmt->execute([$name, $role, $hashed, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name=?, role=? WHERE user_id=?");
            $stmt->execute([$name, $role, $user_id]);
        }
        $message = 'User updated successfully!';
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
    $stmt->execute([$user_id]);
    $editUser = $stmt->fetch();
}

// Delete user
if ($action === 'delete' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$user_id]);
    $message = 'User deleted successfully!';
}

// Fetch all users for display (no pagination needed with AJAX search)
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY name ASC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<div class="container-fluid">
    <h2 class="mb-4">User Management</h2>
    <?php if ($message): ?>
        <div class="alert alert-success"> <?php echo $message; ?> </div>
    <?php endif; ?>
    <?php if ($action === 'add' || ($action === 'edit' && isset($editUser))): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <?php echo $action === 'add' ? 'Add New User' : 'Edit User'; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required value="<?php echo $editUser['username'] ?? ''; ?>" <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo $editUser['name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="Admin" <?php if (($editUser['role'] ?? '') === 'Admin') echo 'selected'; ?>>Admin</option>
                                <option value="Cashier" <?php if (($editUser['role'] ?? '') === 'Cashier') echo 'selected'; ?>>Cashier</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <?php if ($action === 'edit') echo '(leave blank to keep current)'; ?></label>
                            <input type="password" name="password" class="form-control" <?php if ($action === 'add') echo 'required'; ?>>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Save
                        </button>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <div class="position-relative">
                    <input type="text" id="usersSearch" class="form-control" placeholder="Search users..." autocomplete="off">
                    <div class="search-suggestions" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ddd; border-top:none; max-height:200px; overflow-y:auto; z-index:1000;"></div>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary" type="button" onclick="clearUsersSearch()"><i class="fas fa-times"></i> Clear</button>
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary" type="button" onclick="toggleAdvancedUsersSearch()"><i class="fas fa-filter"></i> Advanced</button>
            </div>
        </div>
        
        <!-- Advanced Users Search Panel -->
        <div id="advancedUsersSearchPanel" class="card mb-3" style="display:none;">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Advanced Users Search Filters</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Role Filter</label>
                        <select id="roleFilter" class="form-select">
                            <option value="">All Roles</option>
                            <option value="Admin">Admin</option>
                            <option value="Cashier">Cashier</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select id="usersSortBy" class="form-select">
                            <option value="name">Name (A-Z)</option>
                            <option value="name_desc">Name (Z-A)</option>
                            <option value="username">Username (A-Z)</option>
                            <option value="username_desc">Username (Z-A)</option>
                            <option value="role">Role</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quick Filters</label>
                        <div class="d-grid gap-1">
                            <button type="button" class="btn btn-sm btn-danger" onclick="filterAdmins()">
                                <i class="fas fa-user-shield"></i> Admins Only
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="filterCashiers()">
                                <i class="fas fa-user"></i> Cashiers Only
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="searchUsername" checked>
                            <label class="form-check-label" for="searchUsername">
                                Search Username
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="searchName" checked>
                            <label class="form-check-label" for="searchName">
                                Search Name
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <a href="users.php?action=add" class="btn btn-primary mb-3">
            <i class="fas fa-plus me-1"></i>Add User
        </a>
        <div id="loadingSpinner" class="text-center my-4" style="display:none;">
            <div class="spinner-border text-primary" role="status" aria-label="Loading..."></div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle" aria-label="Users Table" id="usersTable">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php foreach ($users as $user): ?>
                        <tr tabindex="0" data-user-id="<?php echo $user['user_id']; ?>">
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><span class="badge bg-<?php echo $user['role'] === 'Admin' ? 'danger' : 'primary'; ?>"><?php echo $user['role']; ?></span></td>
                            <td>
                                <a href="users.php?action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                <a href="users.php?action=delete&id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="noResults" class="alert alert-info" style="display:none;">
            <i class="fas fa-info-circle me-2"></i>No users found matching your search.
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
        // Asynchronous users search functionality
        let usersSearchTimeout;
        let allUsers = <?php echo json_encode($users); ?>;
        let filteredUsers = [...allUsers];
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#usersSearch')) {
                document.querySelector('.search-suggestions').style.display = 'none';
            }
        });
        
        document.getElementById('usersSearch').addEventListener('input', function(e) {
            clearTimeout(usersSearchTimeout);
            const searchTerm = e.target.value.trim();
            
            if (searchTerm.length < 2) {
                document.querySelector('.search-suggestions').style.display = 'none';
                applyAdvancedUsersFilters();
                return;
            }
            
            usersSearchTimeout = setTimeout(() => {
                // Filter users locally for instant results
                const searchUsername = document.getElementById('searchUsername').checked;
                const searchName = document.getElementById('searchName').checked;
                
                filteredUsers = allUsers.filter(user => {
                    let matches = false;
                    if (searchUsername && user.username.toLowerCase().includes(searchTerm.toLowerCase())) {
                        matches = true;
                    }
                    if (searchName && user.name.toLowerCase().includes(searchTerm.toLowerCase())) {
                        matches = true;
                    }
                    return matches;
                });
                
                // Show suggestions
                const suggestionsDiv = document.querySelector('.search-suggestions');
                suggestionsDiv.innerHTML = '';
                if (filteredUsers.length > 0) {
                    filteredUsers.slice(0, 10).forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'search-suggestion-item';
                        div.innerHTML = `${user.name} (${user.username}) - ${user.role}`;
                        div.onclick = function() {
                            document.getElementById('usersSearch').value = user.name;
                            suggestionsDiv.style.display = 'none';
                            filterUsersBySearch(user.name);
                        };
                        suggestionsDiv.appendChild(div);
                    });
                    suggestionsDiv.style.display = 'block';
                } else {
                    suggestionsDiv.innerHTML = '<div class="search-suggestion-item">No users found</div>';
                    suggestionsDiv.style.display = 'block';
                }
                
                // Apply advanced filters and update table
                applyAdvancedUsersFilters();
            }, 300);
        });
        
        // Advanced search event listeners
        document.getElementById('roleFilter').addEventListener('change', applyAdvancedUsersFilters);
        document.getElementById('usersSortBy').addEventListener('change', applyAdvancedUsersFilters);
        document.getElementById('searchUsername').addEventListener('change', applyAdvancedUsersFilters);
        document.getElementById('searchName').addEventListener('change', applyAdvancedUsersFilters);
        
        function filterUsersBySearch(searchTerm) {
            const searchUsername = document.getElementById('searchUsername').checked;
            const searchName = document.getElementById('searchName').checked;
            
            filteredUsers = allUsers.filter(user => {
                let matches = false;
                if (searchUsername && user.username.toLowerCase().includes(searchTerm.toLowerCase())) {
                    matches = true;
                }
                if (searchName && user.name.toLowerCase().includes(searchTerm.toLowerCase())) {
                    matches = true;
                }
                return matches;
            });
            applyAdvancedUsersFilters();
        }
        
        function applyAdvancedUsersFilters() {
            const searchTerm = document.getElementById('usersSearch').value.trim();
            const roleFilter = document.getElementById('roleFilter').value;
            const sortBy = document.getElementById('usersSortBy').value;
            const searchUsername = document.getElementById('searchUsername').checked;
            const searchName = document.getElementById('searchName').checked;
            
            // Start with search filter
            let users = searchTerm.length < 2 ? [...allUsers] : 
                allUsers.filter(user => {
                    let matches = false;
                    if (searchUsername && user.username.toLowerCase().includes(searchTerm.toLowerCase())) {
                        matches = true;
                    }
                    if (searchName && user.name.toLowerCase().includes(searchTerm.toLowerCase())) {
                        matches = true;
                    }
                    return matches;
                });
            
            // Apply role filter
            if (roleFilter) {
                users = users.filter(user => user.role === roleFilter);
            }
            
            // Apply sorting
            users.sort((a, b) => {
                switch (sortBy) {
                    case 'name':
                        return a.name.localeCompare(b.name);
                    case 'name_desc':
                        return b.name.localeCompare(a.name);
                    case 'username':
                        return a.username.localeCompare(b.username);
                    case 'username_desc':
                        return b.username.localeCompare(a.username);
                    case 'role':
                        return a.role.localeCompare(b.role);
                    default:
                        return 0;
                }
            });
            
            filteredUsers = users;
            updateUsersTableDisplay();
        }
        
        function updateUsersTableDisplay() {
            const tbody = document.getElementById('usersTableBody');
            const noResults = document.getElementById('noResults');
            
            tbody.innerHTML = '';
            
            if (filteredUsers.length === 0) {
                noResults.style.display = 'block';
                return;
            }
            
            noResults.style.display = 'none';
            
            filteredUsers.forEach(user => {
                const row = document.createElement('tr');
                row.setAttribute('data-user-id', user.user_id);
                row.setAttribute('tabindex', '0');
                
                const roleBadgeClass = user.role === 'Admin' ? 'danger' : 'primary';
                
                row.innerHTML = `
                    <td>${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.name)}</td>
                    <td><span class="badge bg-${roleBadgeClass}">${user.role}</span></td>
                    <td>
                        <a href="users.php?action=edit&id=${user.user_id}" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                        <a href="users.php?action=delete&id=${user.user_id}" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?');"><i class="fas fa-trash"></i></a>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        function showAllUsers() {
            filteredUsers = [...allUsers];
            updateUsersTableDisplay();
        }
        
        function clearUsersSearch() {
            document.getElementById('usersSearch').value = '';
            document.getElementById('roleFilter').value = '';
            document.getElementById('usersSortBy').value = 'name';
            document.getElementById('searchUsername').checked = true;
            document.getElementById('searchName').checked = true;
            document.querySelector('.search-suggestions').style.display = 'none';
            showAllUsers();
        }
        
        function toggleAdvancedUsersSearch() {
            const panel = document.getElementById('advancedUsersSearchPanel');
            const button = document.querySelector('button[onclick="toggleAdvancedUsersSearch()"]');
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
        
        function filterAdmins() {
            document.getElementById('roleFilter').value = 'Admin';
            document.getElementById('usersSearch').value = '';
            applyAdvancedUsersFilters();
        }
        
        function filterCashiers() {
            document.getElementById('roleFilter').value = 'Cashier';
            document.getElementById('usersSearch').value = '';
            applyAdvancedUsersFilters();
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