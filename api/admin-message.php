<?php
/**
 * Admin Message API
 * Handles loading admin messages from database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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