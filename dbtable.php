<?php
/**
 * Free SMTP Tester - Simple Database Table Creator
 * Visit this page once to automatically create all required database tables
 * Configure your database credentials in config/config.php first
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Free SMTP Tester</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f7fa;
            color: #333;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            margin: 15px 0;
        }
        .info {
            background: #cce7ff;
            color: #004085;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #b3d7ff;
            margin: 15px 0;
        }
        .table-status {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
        .table-created {
            background: #d1f2eb;
            color: #0c5460;
            border: 1px solid #7bdcb5;
        }
        .table-exists {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .config-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Database Setup - Free SMTP Tester</h1>
            <p>Automatic database table creation utility</p>
        </div>

        <div class="config-info">
            <h3>📋 Current Database Configuration:</h3>
            <p><strong>Host:</strong> <?php echo htmlspecialchars(DB_HOST); ?></p>
            <p><strong>Database:</strong> <?php echo htmlspecialchars(DB_NAME); ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars(DB_USER); ?></p>
            <p><strong>Charset:</strong> <?php echo htmlspecialchars(DB_CHARSET); ?></p>
        </div>

        <?php
        $success = true;
        $messages = [];

        try {
            // Test database connection
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            echo '<div class="success">✅ Database connection successful!</div>';

            // SQL commands to create tables
            $sql_commands = [
                'smtp_configs' => "CREATE TABLE IF NOT EXISTS smtp_configs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    smtp_host VARCHAR(255) NOT NULL,
                    smtp_port INT NOT NULL,
                    username VARCHAR(255) NOT NULL,
                    encryption_type ENUM('none', 'ssl', 'tls', 'auto') DEFAULT 'auto',
                    from_name VARCHAR(255) DEFAULT NULL,
                    from_email VARCHAR(255) NOT NULL,
                    reply_to_email VARCHAR(255) DEFAULT NULL,
                    test_successful BOOLEAN DEFAULT TRUE,
                    connection_time DECIMAL(5,3) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    user_agent TEXT DEFAULT NULL,
                    
                    INDEX idx_smtp_host (smtp_host),
                    INDEX idx_smtp_port (smtp_port),
                    INDEX idx_created_at (created_at),
                    INDEX idx_test_successful (test_successful)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'email_logs' => "CREATE TABLE IF NOT EXISTS email_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    smtp_config_id INT,
                    recipient_email VARCHAR(255) NOT NULL,
                    subject VARCHAR(500) NOT NULL,
                    message_size INT DEFAULT NULL,
                    attachment_count INT DEFAULT 0,
                    send_successful BOOLEAN DEFAULT FALSE,
                    error_message TEXT DEFAULT NULL,
                    send_time DECIMAL(5,3) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    
                    FOREIGN KEY (smtp_config_id) REFERENCES smtp_configs(id) ON DELETE SET NULL,
                    INDEX idx_recipient (recipient_email),
                    INDEX idx_created_at (created_at),
                    INDEX idx_send_successful (send_successful)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'admin_messages' => "CREATE TABLE IF NOT EXISTS admin_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    message_content TEXT NOT NULL,
                    message_type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
                    is_active BOOLEAN DEFAULT TRUE,
                    display_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    
                    INDEX idx_is_active (is_active),
                    INDEX idx_display_order (display_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'rate_limits' => "CREATE TABLE IF NOT EXISTS rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    request_count INT DEFAULT 1,
                    last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    blocked_until TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    
                    UNIQUE KEY unique_ip (ip_address),
                    INDEX idx_ip_address (ip_address),
                    INDEX idx_blocked_until (blocked_until)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            ];

            echo '<h3>📋 Creating Database Tables:</h3>';

            // Create each table
            foreach ($sql_commands as $table_name => $sql) {
                try {
                    // Check if table exists
                    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                    $stmt->execute([$table_name]);
                    $table_exists = $stmt->rowCount() > 0;

                    if ($table_exists) {
                        echo '<div class="table-status table-exists">⚠️ Table "' . htmlspecialchars($table_name) . '" already exists - skipping</div>';
                    } else {
                        $pdo->exec($sql);
                        echo '<div class="table-status table-created">✅ Table "' . htmlspecialchars($table_name) . '" created successfully</div>';
                    }
                } catch (PDOException $e) {
                    echo '<div class="error">❌ Error creating table "' . htmlspecialchars($table_name) . '": ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $success = false;
                }
            }

            // Insert default admin message
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE message_type = 'info' AND is_active = 1");
                $stmt->execute();
                $count = $stmt->fetchColumn();

                if ($count == 0) {
                    $default_message = "Welcome to the Free SMTP Tester! Test your email server configurations safely and securely.";
                    $stmt = $pdo->prepare("INSERT INTO admin_messages (message_content, message_type, is_active, display_order) VALUES (?, 'info', 1, 1)");
                    $stmt->execute([$default_message]);
                    echo '<div class="table-status table-created">✅ Default admin message inserted</div>';
                } else {
                    echo '<div class="table-status table-exists">ℹ️ Default admin message already exists</div>';
                }
            } catch (PDOException $e) {
                echo '<div class="error">⚠️ Warning: Could not insert default admin message: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }

            if ($success) {
                echo '<div class="success">
                    <h3>🎉 Database Setup Complete!</h3>
                    <p>All database tables have been created successfully. Your SMTP Tester is ready to use!</p>
                </div>';
            }

        } catch (PDOException $e) {
            echo '<div class="error">
                <h3>❌ Database Connection Failed</h3>
                <p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                <p><strong>Solution:</strong> Please check your database configuration in <code>config/config.php</code></p>
                <ul>
                    <li>Verify database host, name, username, and password</li>
                    <li>Ensure the database exists and user has proper permissions</li>
                    <li>Check if MySQL service is running</li>
                </ul>
            </div>';
            $success = false;
        }
        ?>

        <div class="info">
            <h3>📝 Next Steps:</h3>
            <ol>
                <?php if ($success): ?>
                <li>✅ Database tables created successfully</li>
                <li>🗑️ Delete this file (<code>dbtable.php</code>) for security</li>
                <li>🚀 Visit your <a href="index.html">SMTP Tester</a> to start testing</li>
                <?php else: ?>
                <li>🔧 Fix the database configuration in <code>config/config.php</code></li>
                <li>🔄 Refresh this page to try again</li>
                <li>💡 Contact your hosting provider if you need database setup assistance</li>
                <?php endif; ?>
            </ol>
        </div>

        <div class="footer">
            <a href="index.html" class="btn">🏠 Go to SMTP Tester</a>
            <?php if ($success): ?>
            <a href="?verify=1" class="btn">🔍 Verify Tables</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['verify']) && $success): ?>
        <div class="info">
            <h3>🔍 Table Verification:</h3>
            <?php
            try {
                // Verify each table structure
                $tables = ['smtp_configs', 'email_logs', 'admin_messages', 'rate_limits'];
                foreach ($tables as $table) {
                    $stmt = $pdo->prepare("DESCRIBE $table");
                    $stmt->execute();
                    $columns = $stmt->fetchAll();
                    echo '<p>✅ Table <strong>' . htmlspecialchars($table) . '</strong>: ' . count($columns) . ' columns</p>';
                }
            } catch (PDOException $e) {
                echo '<p>❌ Verification error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p><small>Free SMTP Tester v<?php echo htmlspecialchars(APP_VERSION); ?> &copy; <?php echo date('Y'); ?> 0mail.Pro</small></p>
        </div>
    </div>
</body>
</html>