<?php
/**
 * Free SMTP Tester - Installation Wizard
 * Web-based installation interface for automatic database setup
 */

session_start();

// Check if already installed
$config_file = __DIR__ . '/../config/config.php';
$install_lock = __DIR__ . '/../config/install.lock';

if (file_exists($install_lock)) {
    header('Location: ../index.html');
    exit('Installation already completed. Delete the install folder.');
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Validate database configuration
        $db_host = trim($_POST['db_host'] ?? '');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';
        
        if (empty($db_host)) $errors[] = 'Database host is required';
        if (empty($db_name)) $errors[] = 'Database name is required';
        if (empty($db_user)) $errors[] = 'Database username is required';
        
        if (empty($errors)) {
            // Test database connection
            try {
                $dsn = "mysql:host={$db_host};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
                // Store credentials in session for next step
                $_SESSION['db_config'] = [
                    'host' => $db_host,
                    'name' => $db_name,
                    'user' => $db_user,
                    'pass' => $db_pass
                ];
                
                header('Location: ?step=2');
                exit;
                
            } catch (PDOException $e) {
                $errors[] = 'Database connection failed: ' . $e->getMessage();
            }
        }
    } elseif ($step === 2 && isset($_SESSION['db_config'])) {
        // Create database and tables
        $db_config = $_SESSION['db_config'];
        
        try {
            // Connect to MySQL server
            $dsn = "mysql:host={$db_config['host']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db_config['name']}`");
            
            // Read and execute SQL schema
            $schema_file = __DIR__ . '/../database_schema.sql';
            if (file_exists($schema_file)) {
                $sql = file_get_contents($schema_file);
                
                // Remove CREATE DATABASE and USE statements since we already did that
                $sql = preg_replace('/^CREATE DATABASE.*?;/im', '', $sql);
                $sql = preg_replace('/^USE.*?;/im', '', $sql);
                
                // Split by semicolon and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        $pdo->exec($statement);
                    }
                }
            }
            
            // Update config file with database credentials
            updateConfigFile($config_file, $db_config);
            
            // Create install lock file
            file_put_contents($install_lock, date('Y-m-d H:i:s'));
            
            $success = true;
            
        } catch (Exception $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

function updateConfigFile($config_file, $db_config) {
    if (file_exists($config_file)) {
        $content = file_get_contents($config_file);
        
        // Update database configuration constants
        $content = preg_replace("/define\('DB_HOST',\s*'[^']*'\);/", "define('DB_HOST', '{$db_config['host']}');", $content);
        $content = preg_replace("/define\('DB_NAME',\s*'[^']*'\);/", "define('DB_NAME', '{$db_config['name']}');", $content);
        $content = preg_replace("/define\('DB_USER',\s*'[^']*'\);/", "define('DB_USER', '{$db_config['user']}');", $content);
        $content = preg_replace("/define\('DB_PASS',\s*'[^']*'\);/", "define('DB_PASS', '{$db_config['pass']}');", $content);
        
        file_put_contents($config_file, $content);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Tester - Installation Wizard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
</head>
<body>
    <div class="container">
        <div class="install-box">
            <div class="header">
                <h1>🚀 SMTP Tester Installation</h1>
                <p>Welcome! Let's set up your SMTP Tester application.</p>
            </div>

            <div class="progress-bar">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">
                    <span class="step-number">1</span>
                    <span class="step-label">Database Config</span>
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">
                    <span class="step-number">2</span>
                    <span class="step-label">Installation</span>
                </div>
                <div class="step <?php echo $success ? 'active' : ''; ?>">
                    <span class="step-number">3</span>
                    <span class="step-label">Complete</span>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <h3>⚠️ Errors Found</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <div class="step-content">
                    <h2>Step 1: Database Configuration</h2>
                    <p>Enter your database connection details. The installer will create the database and tables automatically.</p>
                    
                    <form method="POST" class="install-form">
                        <div class="form-group">
                            <label for="db_host">Database Host</label>
                            <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                            <small>Usually 'localhost' for shared hosting</small>
                        </div>

                        <div class="form-group">
                            <label for="db_name">Database Name</label>
                            <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'smtp_tester'); ?>" required>
                            <small>The database will be created if it doesn't exist</small>
                        </div>

                        <div class="form-group">
                            <label for="db_user">Database Username</label>
                            <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" required>
                            <small>Database user with CREATE privileges</small>
                        </div>

                        <div class="form-group">
                            <label for="db_pass">Database Password</label>
                            <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                            <small>Leave empty if no password is set</small>
                        </div>

                        <button type="submit" class="install-btn">Test Connection & Continue</button>
                    </form>
                </div>

            <?php elseif ($step === 2 && !$success): ?>
                <div class="step-content">
                    <h2>Step 2: Installing Database</h2>
                    <p>Click the button below to create the database tables and complete the installation.</p>
                    
                    <div class="db-info">
                        <h3>Database Information:</h3>
                        <ul>
                            <li><strong>Host:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['host']); ?></li>
                            <li><strong>Database:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['name']); ?></li>
                            <li><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['user']); ?></li>
                        </ul>
                    </div>

                    <form method="POST" class="install-form">
                        <button type="submit" class="install-btn">Create Database Tables</button>
                    </form>
                </div>

            <?php elseif ($success): ?>
                <div class="step-content success">
                    <h2>🎉 Installation Complete!</h2>
                    <p>Your SMTP Tester has been successfully installed and configured.</p>
                    
                    <div class="success-info">
                        <h3>What's next?</h3>
                        <ol>
                            <li><strong>Delete the install folder</strong> - For security, please delete the entire <code>/install</code> directory</li>
                            <li><strong>Access your SMTP Tester</strong> - Visit the main application to start testing</li>
                            <li><strong>Test your setup</strong> - Try sending a test email to verify everything works</li>
                        </ol>
                    </div>

                    <div class="action-buttons">
                        <a href="../index.html" class="install-btn">Go to SMTP Tester</a>
                        <a href="#" onclick="showDeleteInstructions()" class="secondary-btn">Show Delete Instructions</a>
                    </div>

                    <div id="delete-instructions" class="delete-instructions" style="display: none;">
                        <h3>🗑️ Delete Install Folder</h3>
                        <p>For security reasons, please delete the install folder using one of these methods:</p>
                        
                        <div class="method">
                            <h4>Method 1: Plesk File Manager</h4>
                            <ol>
                                <li>Login to your Plesk control panel</li>
                                <li>Go to File Manager</li>
                                <li>Navigate to your domain's root directory</li>
                                <li>Find and delete the <code>install</code> folder</li>
                            </ol>
                        </div>

                        <div class="method">
                            <h4>Method 2: FTP/SFTP</h4>
                            <ol>
                                <li>Connect to your server via FTP/SFTP</li>
                                <li>Navigate to the website root directory</li>
                                <li>Delete the <code>install</code> folder</li>
                            </ol>
                        </div>

                        <div class="method">
                            <h4>Method 3: SSH (if available)</h4>
                            <code>rm -rf /path/to/your/website/install</code>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="footer">
                <p>SMTP Tester v1.0.0 | <a href="https://0mail.pro" target="_blank">0mail.Pro</a></p>
            </div>
        </div>
    </div>

    <script>
        function showDeleteInstructions() {
            document.getElementById('delete-instructions').style.display = 'block';
        }

        // Auto-submit form after successful connection test
        <?php if ($step === 2 && !$_POST && isset($_SESSION['db_config'])): ?>
        setTimeout(function() {
            document.querySelector('form').submit();
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>