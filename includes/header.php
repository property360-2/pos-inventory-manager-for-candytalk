<?php
// Secure session cookie flags - must be set before session_start()
if (session_status() === PHP_SESSION_NONE) {
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Candy Talk POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #ee5a24;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        
        .sidebar-header {
            background: rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h4 {
            color: white;
            margin: 0;
            font-weight: 600;
        }
        
        .sidebar-header p {
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
            font-size: 0.9rem;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .nav-link.active {
            color: white;
            background: var(--primary-color);
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
            display: none;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .content-wrapper {
            padding: 2rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar" aria-label="Main Navigation">
        <div class="sidebar-header">
            <h4><i class="fas fa-candy-cane me-2"></i>Candy Talk</h4>
            <p>POS System</p>
        </div>
        <div class="nav flex-column" role="navigation" aria-label="Sidebar">
            <a href="../dashboard.php" class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" aria-current="<?php echo $current_page == 'dashboard' ? 'page' : 'false'; ?>">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="../modules/sales.php" class="nav-link <?php echo $current_page == 'sales' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>Sales
            </a>
            <a href="../modules/inventory.php" class="nav-link <?php echo $current_page == 'inventory' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>Inventory
            </a>
            <?php if ($_SESSION['role'] == 'Admin'): ?>
            <a href="../modules/users.php" class="nav-link <?php echo $current_page == 'users' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>Users
            </a>
            <?php endif; ?>
            <a href="../reports/index.php" class="nav-link <?php echo $current_page == 'index' && strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>Reports
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="sidebar-toggle me-3" id="sidebar-toggle" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h4>
            </div>
            
            <div class="user-info">
                <div class="d-none d-md-block">
                    <small class="text-muted">Welcome,</small><br>
                    <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
                    <span class="badge bg-<?php echo $_SESSION['role'] == 'Admin' ? 'danger' : 'primary'; ?> ms-2">
                        <?php echo $_SESSION['role']; ?>
                    </span>
                </div>
                <div class="user-avatar d-md-none">
                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-label="User Menu">
                        <i class="fas fa-user"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><span class="dropdown-item-text">
                            <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong><br>
                            <small class="text-muted"><?php echo $_SESSION['role']; ?></small>
                        </span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
        <script>
        // Global confirmation for destructive actions
        document.addEventListener('DOMContentLoaded', function() {
            document.body.addEventListener('click', function(e) {
                if (e.target.matches('a.btn-danger, button.btn-danger')) {
                    if (!confirm('Are you sure you want to proceed? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                }
            });
        });
        </script> 