# Database Setup Instructions for Free SMTP Tester

## Quick Setup for Plesk Users

1. **Log into your Plesk control panel**
2. **Navigate to your database management interface** (usually phpMyAdmin)
3. **Select your database** where you want to install the SMTP Tester tables
4. **Copy and paste the SQL code below** into the SQL query interface

## SQL Code to Execute

Copy the entire content of the `manual_database_setup.sql` file and paste it into your database management interface.

**Or copy this direct code:**

```sql
-- Table 1: SMTP Configurations Storage
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

-- Insert default welcome message
INSERT INTO admin_messages (message_content, message_type, is_active, display_order) VALUES 
('Welcome to the Free SMTP Tester! Test your email server configurations safely and securely.', 'info', 1, 1);
```

## What These Tables Do

1. **smtp_configs** - Stores successful SMTP server configurations for analytics
2. **email_logs** - Logs all email sending attempts for monitoring
3. **admin_messages** - Manages dynamic messages shown on the application
4. **rate_limits** - Protects against abuse by rate limiting requests

## Verification

After running the SQL, you should see 4 new tables in your database. You can verify by running:

```sql
SHOW TABLES;
```

## Alternative Files

- Use `manual_database_setup.sql` for manual copy-paste setup
- Use `database_schema.sql` for automated deployment scripts

## Need Help?

If you encounter any issues:
1. Make sure your database supports MySQL/MariaDB
2. Ensure you have CREATE TABLE privileges
3. Check that utf8mb4 charset is supported
4. Contact your hosting provider if tables fail to create