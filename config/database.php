<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'candy_talk_pos');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Initialize database tables
function initializeDatabase() {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        password VARCHAR(100) NOT NULL,
        role ENUM('Admin', 'Cashier') NOT NULL
    )");
    
    // Create inventory table
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
        product_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL
    )");
    
    // Create sales table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        sale_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sale_date DATETIME NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");
    
    // Create sale_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
        sale_item_id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(sale_id),
        FOREIGN KEY (product_id) REFERENCES inventory(product_id)
    )");
    
    // Insert default admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', 'System Administrator', $hashedPassword, 'Admin']);
    }
    
    // Insert sample products if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $sampleProducts = [
            ['Chocolate Bar', 'Delicious milk chocolate bar', 2.50, 100],
            ['Gummy Bears', 'Colorful fruit-flavored gummy bears', 1.75, 150],
            ['Lollipop', 'Classic round lollipop', 0.75, 200],
            ['Caramel Candy', 'Soft caramel candy', 1.25, 80],
            ['Mint Candy', 'Refreshing mint candy', 0.50, 120]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO inventory (name, description, price, quantity) VALUES (?, ?, ?, ?)");
        foreach ($sampleProducts as $product) {
            $stmt->execute($product);
        }
    }
}

// Call initialization if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) == 'database.php') {
    initializeDatabase();
    echo "Database initialized successfully!";
}

?> 