<?php
/**
 * SETUP SCRIPT - Run this ONCE to set up the database
 * Access: http://localhost/System/setup.php
 * 
 * This script will:
 * 1. Create the database
 * 2. Create all tables
 * 3. Insert default admin user
 * 
 * DELETE THIS FILE after successful setup for security!
 */

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'barangay_gis';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Barangay Mudiang GIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #ffd700;
        }
        .content {
            padding: 40px;
        }
        .step {
            padding: 20px;
            margin: 15px 0;
            border-radius: 10px;
            border-left: 5px solid #3498db;
        }
        .step.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .step.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .step.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        .step.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .step i {
            margin-right: 10px;
            font-size: 20px;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 10px 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .btn-danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .actions {
            text-align: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
<?php include __DIR__.'/ui_theme.php'; ?>
</head>
<body class="setup-page">
    <div class="container">
        <div class="header">
            <i class="fas fa-database"></i>
            <h1>Database Setup</h1>
            <p>Barangay Mudiang GIS Complaint System</p>
        </div>
        <div class="content">
            <?php
            try {
                // Step 1: Connect to MySQL server
                echo '<div class="step info"><i class="fas fa-plug"></i><strong>Step 1:</strong> Connecting to MySQL server...</div>';
                $pdo = new PDO("mysql:host=$host", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo '<div class="step success"><i class="fas fa-check-circle"></i>Successfully connected to MySQL server!</div>';
                
                // Step 2: Create database
                echo '<div class="step info"><i class="fas fa-database"></i><strong>Step 2:</strong> Creating database <code>barangay_gis</code>...</div>';
                $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
                $pdo->exec("USE $dbname");
                echo '<div class="step success"><i class="fas fa-check-circle"></i>Database created/selected successfully!</div>';
                
                // Step 3: Create users table
                echo '<div class="step info"><i class="fas fa-users"></i><strong>Step 3:</strong> Creating <code>users</code> table...</div>';
                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    full_name VARCHAR(100) NOT NULL,
                    email VARCHAR(100),
                    contact VARCHAR(50),
                    address VARCHAR(255),
                    role ENUM('admin', 'staff', 'resident') DEFAULT 'resident',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                echo '<div class="step success"><i class="fas fa-check-circle"></i>Users table created successfully!</div>';
                
                // Step 4: Create complaints table
                echo '<div class="step info"><i class="fas fa-clipboard-list"></i><strong>Step 4:</strong> Creating <code>complaints</code> table...</div>';
                $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT,
                    complaint_type VARCHAR(100) NOT NULL,
                    description TEXT NOT NULL,
                    latitude DECIMAL(10, 8) NOT NULL,
                    longitude DECIMAL(11, 8) NOT NULL,
                    address VARCHAR(255) NOT NULL,
                    complainant_name VARCHAR(100) NOT NULL,
                    complainant_contact VARCHAR(50) NOT NULL,
                    status ENUM('filed', 'acknowledged', 'processing', 'resolved') DEFAULT 'filed',
                    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
                    assigned_to VARCHAR(100),
                    resolution_notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )");
                echo '<div class="step success"><i class="fas fa-check-circle"></i>Complaints table created successfully!</div>';
                
                $pdo->exec(file_get_contents(__DIR__ . '/migrations/20261001_complaint_evidence.sql'));
                $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    complaint_id INT NULL,
                    message VARCHAR(255) NOT NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_notif_user (user_id,is_read),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
                )");
                $pdo->exec(file_get_contents(__DIR__ . '/migrations/20261001_complaint_messages.sql'));
                $pdo->exec(file_get_contents(__DIR__ . '/migrations/20261001_report_activity.sql'));
                $pdo->exec(file_get_contents(__DIR__ . '/migrations/20261001_workflow.sql'));
                foreach(explode(';',file_get_contents(__DIR__.'/migrations/20261006_resident_features.sql')) as $statement)if(trim($statement)!=='')$pdo->exec($statement);
                $pdo->exec(file_get_contents(__DIR__.'/migrations/20261007_announcement_notifications.sql'));
                // Step 5: Insert admin user
                echo '<div class="step info"><i class="fas fa-user-shield"></i><strong>Step 5:</strong> Creating default admin user...</div>';
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username=username");
                $stmt->execute(['admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@barangay.gov', 'admin']);
                echo '<div class="step success"><i class="fas fa-check-circle"></i>Admin user created successfully!</div>';
                
                // Success message
                echo '<div class="step success" style="border-left-width: 8px; font-size: 18px;">
                        <i class="fas fa-trophy"></i><strong>Setup Complete!</strong> Your database is ready to use.
                      </div>';
                
                echo '<div class="step warning">
                        <i class="fas fa-exclamation-triangle"></i><strong>Default Admin Credentials:</strong><br>
                        Username: <code>admin</code><br>
                        Password: <code>password</code><br>
                        <small>Please change this password after first login!</small>
                      </div>';
                
                echo '<div class="step warning">
                        <i class="fas fa-shield-alt"></i><strong>Security Warning:</strong><br>
                        Please DELETE this file (<code>setup.php</code>) immediately for security reasons!
                      </div>';
                
                echo '<div class="actions">
                        <a href="index.php" class="btn btn-success"><i class="fas fa-home"></i> Go to Home Page</a>
                        <a href="login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="register.php" class="btn"><i class="fas fa-user-plus"></i> Register</a>
                      </div>';
                
            } catch(PDOException $e) {
                echo '<div class="step error">
                        <i class="fas fa-times-circle"></i><strong>Error:</strong> ' . $e->getMessage() . '
                      </div>';
                echo '<div class="step info">
                        <i class="fas fa-info-circle"></i><strong>Troubleshooting:</strong><br>
                        1. Make sure XAMPP MySQL is running<br>
                        2. Check database credentials in this file<br>
                        3. Verify MySQL port (default: 3306)<br>
                        4. Check if database already exists
                      </div>';
            }
            ?>
        </div>
    </div>
</body>
</html>
