<?php
/**
 * Installation Check and Redirect
 * Checks if the application is installed and redirects accordingly
 */

// Check if installation is complete
$install_lock = __DIR__ . '/config/install.lock';

if (!file_exists($install_lock)) {
    // Installation not complete, redirect to installer
    header('Location: install/index.php');
    exit('Installation required. Redirecting to installer...');
}

// Installation complete, redirect to main application
header('Location: index.html');
exit;
?>