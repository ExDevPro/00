<?php
/**
 * SMTP Testing API
 * Handles SMTP connection testing and email sending
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/db_config.php';

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class SMTPTestAPI {
    private $db;
    private $config;
    private $rateLimiter;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->config = $this->loadConfig();
            $this->rateLimiter = new RateLimiter();
        } catch (Exception $e) {
            $this->sendError('System initialization failed', 500);
        }
    }

    public function handleRequest() {
        try {
            // Rate limiting
            if (!$this->rateLimiter->checkLimit()) {
                $this->sendError('Rate limit exceeded. Please try again later.', 429);
            }

            $action = $_GET['action'] ?? 'send';
            
            switch ($action) {
                case 'test':
                    $this->testConnection();
                    break;
                case 'send':
                default:
                    $this->sendEmail();
                    break;
            }
        } catch (Exception $e) {
            error_log("SMTP Test API Error: " . $e->getMessage());
            $this->sendError('An unexpected error occurred', 500);
        }
    }

    private function testConnection() {
        $smtpConfig = $this->validateSMTPConfig();
        
        if (!$smtpConfig) {
            $this->sendError('Invalid SMTP configuration', 400);
        }

        try {
            $startTime = microtime(true);
            
            // Create transport with proxy if enabled
            $transport = $this->createTransport($smtpConfig);
            
            // Test connection
            $mailer = new Mailer($transport);
            
            // Try to get the transport
            $transport->start();
            $transport->stop();
            
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Store successful configuration
            $this->storeSMTPConfig($smtpConfig, $connectionTime);
            
            $this->sendSuccess([
                'message' => 'SMTP connection successful',
                'connection_time' => $connectionTime . 'ms',
                'server' => $smtpConfig['host'] . ':' . $smtpConfig['port']
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Connection failed: ' . $this->sanitizeErrorMessage($e->getMessage()), 400);
        }
    }

    private function sendEmail() {
        $smtpConfig = $this->validateSMTPConfig();
        $emailData = $this->validateEmailData();
        
        if (!$smtpConfig || !$emailData) {
            $this->sendError('Invalid configuration or email data', 400);
        }

        try {
            $startTime = microtime(true);
            
            // Create transport
            $transport = $this->createTransport($smtpConfig);
            $mailer = new Mailer($transport);
            
            // Create email
            $email = (new Email())
                ->from($smtpConfig['from_email'])
                ->to($emailData['recipient'])
                ->subject($emailData['subject']);

            // Set optional fields
            if (!empty($smtpConfig['from_name'])) {
                $email->from($smtpConfig['from_email'], $smtpConfig['from_name']);
            }

            if (!empty($smtpConfig['reply_to'])) {
                $email->replyTo($smtpConfig['reply_to']);
            }

            // Set message content
            if (!empty($emailData['message'])) {
                $email->html($emailData['message']);
                $email->text(strip_tags($emailData['message']));
            }

            // Handle attachments
            $attachmentCount = 0;
            if (!empty($_FILES['attachments'])) {
                $attachmentCount = $this->processAttachments($email, $_FILES['attachments']);
            }

            // Send email
            $mailer->send($email);
            
            $sendTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log the successful send
            $this->logEmailSend($smtpConfig, $emailData, $attachmentCount, $sendTime, true);
            
            $this->sendSuccess([
                'message' => 'Email sent successfully',
                'send_time' => $sendTime . 'ms',
                'recipient' => $emailData['recipient'],
                'attachments' => $attachmentCount
            ]);
            
        } catch (Exception $e) {
            // Log the failed send
            $this->logEmailSend($smtpConfig, $emailData, 0, 0, false, $e->getMessage());
            
            $this->sendError('Failed to send email: ' . $this->sanitizeErrorMessage($e->getMessage()), 400);
        }
    }

    private function validateSMTPConfig() {
        $required = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'from_email'];
        $config = [];

        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                return false;
            }
            $config[str_replace('smtp_', '', $field)] = $this->sanitizeInput($_POST[$field]);
        }

        // Optional fields
        $config['from_name'] = $this->sanitizeInput($_POST['from_name'] ?? '');
        $config['reply_to'] = $this->sanitizeInput($_POST['reply_to'] ?? '');
        $config['auth'] = $this->sanitizeInput($_POST['smtp_auth'] ?? 'auto');

        // Validate port
        $config['port'] = (int)$config['port'];
        if ($config['port'] < 1 || $config['port'] > 65535) {
            return false;
        }

        // Validate email addresses
        if (!filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!empty($config['reply_to']) && !filter_var($config['reply_to'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return $config;
    }

    private function validateEmailData() {
        if (empty($_POST['recipient_email'])) {
            return false;
        }

        $data = [
            'recipient' => $this->sanitizeInput($_POST['recipient_email']),
            'subject' => $this->sanitizeInput($_POST['email_subject'] ?? 'Test Email'),
            'message' => $_POST['email_message'] ?? ''
        ];

        // Validate recipient email
        if (!filter_var($data['recipient'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Sanitize HTML content
        $data['message'] = $this->sanitizeHTML($data['message']);

        return $data;
    }

    private function createTransport($config) {
        // Determine encryption
        $encryption = null;
        switch ($config['auth']) {
            case 'ssl':
                $encryption = 'ssl';
                break;
            case 'tls':
                $encryption = 'tls';
                break;
            case 'auto':
            default:
                $encryption = ($config['port'] == 465) ? 'ssl' : 'tls';
                break;
        }

        $dsn = sprintf(
            'smtp://%s:%s@%s:%d',
            urlencode($config['username']),
            urlencode($config['password']),
            $config['host'],
            $config['port']
        );

        if ($encryption && $encryption !== 'none') {
            $dsn .= '?encryption=' . $encryption;
        }

        $transport = Transport::fromDsn($dsn);

        // Apply proxy if enabled
        if (PROXY_ENABLED && FEATURE_PROXY_SUPPORT) {
            $proxy = $this->getProxy();
            if ($proxy) {
                // Note: Proxy implementation would need additional configuration
                // This is a placeholder for proxy functionality
            }
        }

        return $transport;
    }

    private function processAttachments($email, $files) {
        $count = 0;
        $totalSize = 0;

        if (!FEATURE_FILE_ATTACHMENTS) {
            return 0;
        }

        foreach ($files['name'] as $index => $filename) {
            if ($files['error'][$index] !== UPLOAD_ERR_OK) {
                continue;
            }

            $fileSize = $files['size'][$index];
            $filePath = $files['tmp_name'][$index];
            
            // Check file size
            if ($fileSize > MAX_ATTACHMENT_SIZE) {
                continue;
            }

            // Check total size
            if ($totalSize + $fileSize > MAX_ATTACHMENT_SIZE) {
                break;
            }

            // Check file type
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, ALLOWED_ATTACHMENT_TYPES)) {
                continue;
            }

            // Read file content
            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            // Add attachment
            $email->addPart(new DataPart($content, $filename));
            
            $count++;
            $totalSize += $fileSize;

            // Store attachment temporarily
            $this->storeAttachment($filename, $content);
        }

        return $count;
    }

    private function storeAttachment($filename, $content) {
        $attachmentPath = ATTACHMENT_PATH . date('Y-m-d') . '/';
        
        if (!is_dir($attachmentPath)) {
            mkdir($attachmentPath, 0755, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9.-]/', '_', $filename);
        $fullPath = $attachmentPath . time() . '_' . $safeName;
        
        file_put_contents($fullPath, $content);
    }

    private function storeSMTPConfig($config, $connectionTime) {
        try {
            // Check if this exact configuration already exists
            $stmt = $this->db->prepare("
                SELECT id FROM smtp_configs 
                WHERE smtp_host = ? AND smtp_port = ? AND username = ? 
                AND test_successful = 1 
                LIMIT 1
            ");
            
            $stmt->execute([$config['host'], $config['port'], $config['username']]);
            
            if ($stmt->fetch()) {
                return; // Configuration already exists
            }

            // Store new successful configuration
            $stmt = $this->db->prepare("
                INSERT INTO smtp_configs (
                    smtp_host, smtp_port, username, encryption_type,
                    from_name, from_email, reply_to_email, test_successful,
                    connection_time, ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
            ");

            $stmt->execute([
                $config['host'],
                $config['port'],
                $config['username'],
                $config['auth'],
                $config['from_name'],
                $config['from_email'],
                $config['reply_to'],
                $connectionTime / 1000, // Convert to seconds
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

        } catch (Exception $e) {
            error_log("Failed to store SMTP config: " . $e->getMessage());
        }
    }

    private function logEmailSend($smtpConfig, $emailData, $attachmentCount, $sendTime, $success, $errorMessage = null) {
        try {
            // Get SMTP config ID if it exists
            $stmt = $this->db->prepare("
                SELECT id FROM smtp_configs 
                WHERE smtp_host = ? AND smtp_port = ? AND username = ? 
                LIMIT 1
            ");
            
            $stmt->execute([$smtpConfig['host'], $smtpConfig['port'], $smtpConfig['username']]);
            $smtpConfigId = $stmt->fetchColumn() ?: null;

            // Log the email send attempt
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (
                    smtp_config_id, recipient_email, subject, message_size,
                    attachment_count, send_successful, error_message,
                    send_time, ip_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $messageSize = strlen($emailData['message']);
            $sendTimeSeconds = $success ? $sendTime / 1000 : null;

            $stmt->execute([
                $smtpConfigId,
                $emailData['recipient'],
                $emailData['subject'],
                $messageSize,
                $attachmentCount,
                $success ? 1 : 0,
                $errorMessage,
                $sendTimeSeconds,
                $this->getClientIP()
            ]);

        } catch (Exception $e) {
            error_log("Failed to log email send: " . $e->getMessage());
        }
    }

    private function getProxy() {
        if (!file_exists(PROXY_FILE)) {
            return null;
        }

        $proxies = array_map('str_getcsv', file(PROXY_FILE, FILE_SKIP_EMPTY_LINES));
        
        if (empty($proxies)) {
            return null;
        }

        // Get random proxy
        $proxy = $proxies[array_rand($proxies)];
        
        if (count($proxy) >= 2) {
            return [
                'host' => $proxy[0],
                'port' => $proxy[1],
                'username' => $proxy[2] ?? null,
                'password' => $proxy[3] ?? null
            ];
        }

        return null;
    }

    private function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }

    private function sanitizeHTML($html) {
        // Basic HTML sanitization - in production, use a proper HTML purifier
        $allowed_tags = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a>';
        return strip_tags($html, $allowed_tags);
    }

    private function sanitizeErrorMessage($message) {
        // Remove sensitive information from error messages
        $message = preg_replace('/password[^:]*:[^;]*/i', 'password: [HIDDEN]', $message);
        $message = preg_replace('/auth[^:]*:[^;]*/i', 'auth: [HIDDEN]', $message);
        return $message;
    }

    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function loadConfig() {
        return [
            'max_attachment_size' => MAX_ATTACHMENT_SIZE,
            'allowed_types' => ALLOWED_ATTACHMENT_TYPES,
            'rate_limit' => RATE_LIMIT_TESTS,
            'rate_window' => RATE_LIMIT_WINDOW
        ];
    }

    private function sendSuccess($data) {
        http_response_code(200);
        echo json_encode(['success' => true] + $data);
        exit;
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

class RateLimiter {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->cleanup();
    }

    public function checkLimit() {
        if (!FEATURE_RATE_LIMITING) {
            return true;
        }

        $ip = $this->getClientIP();
        $windowStart = time() - RATE_LIMIT_WINDOW;

        try {
            // Count requests in current window
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM rate_limits 
                WHERE ip_address = ? AND created_at > ?
            ");
            
            $stmt->execute([$ip, date('Y-m-d H:i:s', $windowStart)]);
            $count = $stmt->fetchColumn();

            if ($count >= RATE_LIMIT_TESTS) {
                return false;
            }

            // Record this request
            $stmt = $this->db->prepare("
                INSERT INTO rate_limits (ip_address, created_at) VALUES (?, NOW())
            ");
            $stmt->execute([$ip]);

            return true;

        } catch (Exception $e) {
            error_log("Rate limiter error: " . $e->getMessage());
            return true; // Allow on error
        }
    }

    private function cleanup() {
        try {
            // Create rate_limits table if it doesn't exist
            $this->db->prepare("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ip_time (ip_address, created_at)
                ) ENGINE=InnoDB
            ")->execute();

            // Clean old entries
            $cleanupTime = time() - (RATE_LIMIT_WINDOW * 2);
            $stmt = $this->db->prepare("
                DELETE FROM rate_limits WHERE created_at < ?
            ");
            $stmt->execute([date('Y-m-d H:i:s', $cleanupTime)]);

        } catch (Exception $e) {
            error_log("Rate limiter cleanup error: " . $e->getMessage());
        }
    }

    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api = new SMTPTestAPI();
    $api->handleRequest();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}