-- Free SMTP Tester Database Schema
-- This schema stores successful SMTP configurations for analytics and reuse

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS smtp_tester CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smtp_tester;

-- Table for storing successful SMTP configurations
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

-- Table for storing test email logs
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

-- Table for storing admin messages
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

-- Insert default admin message
INSERT INTO admin_messages (message_content, message_type, is_active, display_order) VALUES 
('Welcome to the Free SMTP Tester! Test your email server configurations safely and securely.', 'info', 1, 1);
