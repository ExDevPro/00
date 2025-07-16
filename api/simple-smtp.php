<?php
/**
 * Simple SMTP Testing API - No Dependencies Required
 * Handles SMTP connection testing and email sending using native PHP
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/db_config.php';

class SimpleSMTPTester {
    private $db;
    private $debugMode;
    private $proxyEnabled;
    private $debugLogs = [];

    public function __construct() {
        try {
            // Try to initialize database, but don't fail if it's not available
            try {
                $this->db = Database::getInstance();
            } catch (Exception $e) {
                $this->db = null;
                error_log("Database not available: " . $e->getMessage());
            }
            
            $this->debugMode = ($_POST['debug_mode'] ?? '0') === '1';
            $this->proxyEnabled = ($_POST['proxy_enabled'] ?? '0') === '1';
        } catch (Exception $e) {
            $this->sendError('System initialization failed: ' . $e->getMessage(), 500);
        }
    }

    public function handleRequest() {
        try {
            // Rate limiting check
            if (!$this->checkRateLimit()) {
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
            error_log("Simple SMTP API Error: " . $e->getMessage());
            $this->sendError('An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }

    private function testConnection() {
        $this->debugLog("Starting SMTP connection test");
        $config = $this->validateSMTPConfig();
        
        if (!$config) {
            $this->debugLog("SMTP configuration validation failed");
            $this->sendError('Invalid SMTP configuration', 400);
        }

        try {
            $startTime = microtime(true);
            
            // Test SMTP connection
            $socket = $this->connectToSMTP($config);
            if (!$socket) {
                $this->debugLog("Failed to connect to SMTP server");
                $this->sendError('Failed to connect to SMTP server', 400);
            }

            // Basic SMTP handshake
            $this->smtpCommand($socket, '', '220'); // Initial greeting
            $this->smtpCommand($socket, "EHLO " . gethostname(), ['250', '220']);
            
            // Test authentication if required
            if ($config['username'] && $config['password']) {
                $this->smtpCommand($socket, "AUTH LOGIN", '334');
                $this->smtpCommand($socket, base64_encode($config['username']), '334');
                $this->smtpCommand($socket, base64_encode($config['password']), '235');
            }
            
            $this->smtpCommand($socket, "QUIT", '221');
            fclose($socket);
            
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->debugLog("Connection test completed in {$connectionTime}ms");
            
            $this->sendSuccess([
                'message' => 'SMTP connection successful',
                'connection_time' => $connectionTime . 'ms',
                'server' => $config['host'] . ':' . $config['port'],
                'debug_logs' => $this->debugLogs
            ]);
            
        } catch (Exception $e) {
            $this->debugLog("SMTP connection failed: " . $e->getMessage());
            $this->sendError('Connection failed: ' . $e->getMessage(), 400);
        }
    }

    private function sendEmail() {
        $this->debugLog("Starting email send process");
        $config = $this->validateSMTPConfig();
        $emailData = $this->validateEmailData();
        
        if (!$config || !$emailData) {
            $this->debugLog("Configuration or email data validation failed");
            $this->sendError('Invalid configuration or email data', 400);
        }

        try {
            $startTime = microtime(true);
            
            // Connect to SMTP server
            $socket = $this->connectToSMTP($config);
            if (!$socket) {
                $this->sendError('Failed to connect to SMTP server', 400);
            }

            // SMTP transaction
            $this->smtpCommand($socket, '', '220'); // Initial greeting
            $this->smtpCommand($socket, "EHLO " . gethostname(), ['250', '220']);
            
            // Authentication
            if ($config['username'] && $config['password']) {
                $this->smtpCommand($socket, "AUTH LOGIN", '334');
                $this->smtpCommand($socket, base64_encode($config['username']), '334');
                $this->smtpCommand($socket, base64_encode($config['password']), '235');
            }
            
            // Send email
            $this->smtpCommand($socket, "MAIL FROM: <{$config['from_email']}>", '250');
            $this->smtpCommand($socket, "RCPT TO: <{$emailData['recipient']}>", '250');
            $this->smtpCommand($socket, "DATA", '354');
            
            // Email headers and body
            $message = $this->buildEmailMessage($config, $emailData);
            fputs($socket, $message . "\r\n.\r\n");
            $this->readResponse($socket, '250');
            
            $this->smtpCommand($socket, "QUIT", '221');
            fclose($socket);
            
            $sendTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->debugLog("Email sent successfully in {$sendTime}ms");
            
            $this->sendSuccess([
                'message' => 'Email sent successfully',
                'send_time' => $sendTime . 'ms',
                'recipient' => $emailData['recipient'],
                'debug_logs' => $this->debugLogs
            ]);
            
        } catch (Exception $e) {
            $this->debugLog("Email sending failed: " . $e->getMessage());
            $this->sendError('Failed to send email: ' . $e->getMessage(), 400);
        }
    }

    private function connectToSMTP($config) {
        $this->debugLog("Connecting to {$config['host']}:{$config['port']}");
        
        $context = stream_context_create();
        
        // Apply proxy settings if enabled
        if ($this->proxyEnabled && PROXY_ENABLED && FEATURE_PROXY_SUPPORT) {
            $proxy = $this->getProxy();
            if ($proxy) {
                $this->debugLog("Using proxy: {$proxy['host']}:{$proxy['port']}");
                // Note: For basic functionality, we'll log proxy usage but connect directly
                // Full proxy implementation would require more complex stream context setup
                $this->debugLog("Note: Proxy support is simplified - using direct connection for compatibility");
            } else {
                $this->debugLog("No proxy available - proceeding with direct connection");
            }
        } else {
            $this->debugLog("Proxy disabled or not configured - using direct connection");
        }
        
        // Determine connection type
        if ($config['port'] == 465) {
            // SSL connection
            $this->debugLog("Using SSL encryption");
            $socket = stream_socket_client(
                "ssl://{$config['host']}:{$config['port']}", 
                $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
            );
        } else {
            // Regular connection (may upgrade to TLS)
            $this->debugLog("Using plain connection (may upgrade to TLS)");
            $socket = stream_socket_client(
                "tcp://{$config['host']}:{$config['port']}", 
                $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
            );
        }
        
        if (!$socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }
        
        // Enable crypto for TLS on ports 587/25
        if ($config['port'] == 587 || $config['port'] == 25) {
            $this->debugLog("Starting TLS handshake");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
        
        return $socket;
    }

    private function smtpCommand($socket, $command, $expectedCode) {
        if ($command) {
            $this->debugLog("SMTP Command: $command");
            fputs($socket, $command . "\r\n");
        }
        
        return $this->readResponse($socket, $expectedCode);
    }

    private function readResponse($socket, $expectedCode) {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        $response = trim($response);
        $this->debugLog("SMTP Response: $response");
        
        $actualCode = substr($response, 0, 3);
        $expectedCodes = is_array($expectedCode) ? $expectedCode : [$expectedCode];
        
        if (!in_array($actualCode, $expectedCodes)) {
            throw new Exception("SMTP Error: Expected " . implode('/', $expectedCodes) . ", got $actualCode - $response");
        }
        
        return $response;
    }

    private function buildEmailMessage($config, $emailData) {
        $boundary = md5(time());
        $fromHeader = !empty($config['from_name']) 
            ? "\"{$config['from_name']}\" <{$config['from_email']}>"
            : $config['from_email'];
            
        $message = "From: $fromHeader\r\n";
        $message .= "To: {$emailData['recipient']}\r\n";
        $message .= "Subject: {$emailData['subject']}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        
        if (!empty($config['reply_to'])) {
            $message .= "Reply-To: {$config['reply_to']}\r\n";
        }
        
        $message .= "\r\n";
        
        // Plain text version
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= strip_tags($emailData['message']) . "\r\n";
        
        // HTML version
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $emailData['message'] . "\r\n";
        
        $message .= "--$boundary--\r\n";
        
        return $message;
    }

    private function validateSMTPConfig() {
        $required = ['smtp_host', 'smtp_port', 'from_email'];
        $config = [];

        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $this->debugLog("Missing required field: $field");
                return false;
            }
            $config[str_replace('smtp_', '', $field)] = trim($_POST[$field]);
        }

        // Optional fields
        $config['username'] = trim($_POST['smtp_username'] ?? '');
        $config['password'] = $_POST['smtp_password'] ?? '';
        $config['from_name'] = trim($_POST['from_name'] ?? '');
        $config['reply_to'] = trim($_POST['reply_to'] ?? '');

        // Validate port
        $config['port'] = (int)$config['port'];
        if ($config['port'] < 1 || $config['port'] > 65535) {
            $this->debugLog("Invalid port: {$config['port']}");
            return false;
        }

        // Validate email addresses
        if (!filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
            $this->debugLog("Invalid from_email: {$config['from_email']}");
            return false;
        }

        if (!empty($config['reply_to']) && !filter_var($config['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $this->debugLog("Invalid reply_to: {$config['reply_to']}");
            return false;
        }

        return $config;
    }

    private function validateEmailData() {
        if (empty($_POST['recipient_email'])) {
            $this->debugLog("Missing recipient_email");
            return false;
        }

        $data = [
            'recipient' => trim($_POST['recipient_email']),
            'subject' => trim($_POST['email_subject'] ?? 'Test Email'),
            'message' => $_POST['email_message'] ?? 'This is a test email from the SMTP Tester.'
        ];

        // Validate recipient email
        if (!filter_var($data['recipient'], FILTER_VALIDATE_EMAIL)) {
            $this->debugLog("Invalid recipient_email: {$data['recipient']}");
            return false;
        }

        return $data;
    }

    private function checkRateLimit() {
        if (!FEATURE_RATE_LIMITING || !$this->db) {
            return true;
        }

        try {
            $ip = $this->getClientIP();
            $windowStart = time() - RATE_LIMIT_WINDOW;

            // Check current rate limit status
            $stmt = $this->db->prepare("
                SELECT request_count, blocked_until, last_request 
                FROM rate_limits 
                WHERE ip_address = ?
            ");
            
            $stmt->execute([$ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Check if currently blocked
                if ($result['blocked_until'] && strtotime($result['blocked_until']) > time()) {
                    return false;
                }
                
                // Check if in current window
                if (strtotime($result['last_request']) > $windowStart) {
                    // In current window, check limit
                    if ($result['request_count'] >= RATE_LIMIT_TESTS) {
                        // Block for rate limit window
                        $blockUntil = date('Y-m-d H:i:s', time() + RATE_LIMIT_WINDOW);
                        $stmt = $this->db->prepare("
                            UPDATE rate_limits 
                            SET blocked_until = ?, request_count = request_count + 1, last_request = NOW()
                            WHERE ip_address = ?
                        ");
                        $stmt->execute([$blockUntil, $ip]);
                        return false;
                    } else {
                        // Increment counter
                        $stmt = $this->db->prepare("
                            UPDATE rate_limits 
                            SET request_count = request_count + 1, last_request = NOW()
                            WHERE ip_address = ?
                        ");
                        $stmt->execute([$ip]);
                    }
                } else {
                    // New window, reset counter
                    $stmt = $this->db->prepare("
                        UPDATE rate_limits 
                        SET request_count = 1, last_request = NOW(), blocked_until = NULL
                        WHERE ip_address = ?
                    ");
                    $stmt->execute([$ip]);
                }
            } else {
                // First request from this IP
                $stmt = $this->db->prepare("
                    INSERT INTO rate_limits (ip_address, request_count, last_request, created_at) 
                    VALUES (?, 1, NOW(), NOW())
                ");
                $stmt->execute([$ip]);
            }
            
            return true;

        } catch (Exception $e) {
            error_log("Rate limiter error: " . $e->getMessage());
            return true; // Allow on error
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

    private function getProxy() {
        if (!file_exists(PROXY_FILE)) {
            $this->debugLog("Proxy file not found: " . PROXY_FILE);
            return null;
        }

        $content = file_get_contents(PROXY_FILE);
        if (!$content) {
            $this->debugLog("Failed to read proxy file");
            return null;
        }

        $lines = explode("\n", trim($content));
        $proxies = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue; // Skip empty lines and comments
            }
            
            $parts = str_getcsv($line);
            if (count($parts) >= 2) {
                $proxies[] = [
                    'host' => $parts[0],
                    'port' => $parts[1],
                    'username' => $parts[2] ?? null,
                    'password' => $parts[3] ?? null,
                    'type' => $parts[4] ?? 'http'
                ];
            }
        }
        
        if (empty($proxies)) {
            $this->debugLog("No valid proxies found in proxy file");
            return null;
        }

        // Get random proxy
        $proxy = $proxies[array_rand($proxies)];
        $this->debugLog("Selected proxy: {$proxy['host']}:{$proxy['port']} ({$proxy['type']})");
        
        return $proxy;
    }

    private function debugLog($message) {
        if ($this->debugMode) {
            $this->debugLogs[] = date('H:i:s') . ' - ' . $message;
            error_log("Simple SMTP Debug: " . $message);
        }
    }

    private function sendSuccess($data) {
        http_response_code(200);
        $response = ['success' => true] + $data;
        
        if ($this->debugMode) {
            $response['debug_info'] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'proxy_enabled' => $this->proxyEnabled,
                'memory_usage' => memory_get_usage(true),
                'ip_address' => $this->getClientIP()
            ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        $response = ['success' => false, 'message' => $message];
        
        if ($this->debugMode) {
            $response['debug_logs'] = $this->debugLogs;
            $response['debug_info'] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'proxy_enabled' => $this->proxyEnabled,
                'memory_usage' => memory_get_usage(true),
                'ip_address' => $this->getClientIP()
            ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api = new SimpleSMTPTester();
    $api->handleRequest();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}