<?php
/**
 * Admin Message API
 * Handles loading admin messages from database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check if installation is complete
$install_lock = __DIR__ . '/../config/install.lock';
if (!file_exists($install_lock)) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Application not installed. Please run the installation wizard first.',
        'install_url' => '../install/'
    ]);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/db_config.php';

try {
    $db = Database::getInstance();
    
    // Get active admin message with highest priority
    $stmt = $db->prepare("
        SELECT message_content, message_type 
        FROM admin_messages 
        WHERE is_active = 1 
        ORDER BY display_order ASC, created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute();
    $message = $stmt->fetch();
    
    if ($message) {
        echo json_encode([
            'success' => true,
            'message' => [
                'content' => $message['message_content'],
                'type' => $message['message_type']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => [
                'content' => 'Welcome to the Free SMTP Tester! Test your email server configurations safely and securely.',
                'type' => 'info'
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Could not load admin message'
    ]);
}