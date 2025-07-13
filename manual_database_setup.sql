-- =============================================================================
-- FREE SMTP TESTER - MANUAL DATABASE SETUP
-- =============================================================================
-- Copy and paste this entire script into your Plesk database management interface
-- Execute all commands at once or run them one by one
-- =============================================================================

-- Table 1: SMTP Configurations Storage
-- Stores successful SMTP server configurations for analytics and reuse
CREATE TABLE IF NOT EXISTS smtp_configs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Email Test Logs
-- Stores logs of email sending attempts for monitoring and debugging
CREATE TABLE IF NOT EXISTS email_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: Admin Messages Management
-- Stores dynamic admin messages displayed on the application
CREATE TABLE IF NOT EXISTS admin_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_content TEXT NOT NULL,
    message_type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_is_active (is_active),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: Rate Limiting Protection
-- Protects against abuse by tracking request rates per IP
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    request_count INT DEFAULT 1,
    last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_ip (ip_address),
    INDEX idx_ip_address (ip_address),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- INITIAL DATA INSERTION
-- =============================================================================

-- Insert default welcome admin message
INSERT INTO admin_messages (message_content, message_type, is_active, display_order) VALUES 
('Welcome to the Free SMTP Tester! Test your email server configurations safely and securely.', 'info', 1, 1)
ON DUPLICATE KEY UPDATE 
message_content = VALUES(message_content),
updated_at = CURRENT_TIMESTAMP;

-- =============================================================================
-- VERIFICATION QUERIES (Optional - Run to verify tables were created)
-- =============================================================================

-- Check if all tables were created successfully
-- SHOW TABLES;

-- Check table structures
-- DESCRIBE smtp_configs;
-- DESCRIBE email_logs;
-- DESCRIBE admin_messages;
-- DESCRIBE rate_limits;

-- Check initial data
-- SELECT * FROM admin_messages;

-- =============================================================================
-- END OF SETUP SCRIPT
-- =============================================================================