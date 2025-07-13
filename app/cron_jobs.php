<?php
/**
 * Cron Jobs for Cleanup Tasks
 * Run this script periodically to clean up old files and data
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/db_config.php';

class CleanupManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function runCleanup() {
        echo "Starting cleanup tasks...\n";
        
        $this->cleanupAttachments();
        $this->cleanupLogs();
        $this->cleanupOldSMTPData();
        $this->cleanupRateLimits();
        
        echo "Cleanup completed.\n";
    }

    private function cleanupAttachments() {
        echo "Cleaning up old attachments...\n";
        
        $attachmentPath = ATTACHMENT_PATH;
        if (!is_dir($attachmentPath)) {
            return;
        }

        $cutoffDate = time() - (CLEANUP_ATTACHMENTS_DAYS * 24 * 60 * 60);
        $this->cleanupDirectory($attachmentPath, $cutoffDate);
        
        echo "Attachments cleanup completed.\n";
    }

    private function cleanupLogs() {
        echo "Cleaning up old log files...\n";
        
        $logPath = LOG_PATH;
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
            return;
        }

        $cutoffDate = time() - (CLEANUP_LOGS_DAYS * 24 * 60 * 60);
        $this->cleanupDirectory($logPath, $cutoffDate);
        
        echo "Log files cleanup completed.\n";
    }

    private function cleanupOldSMTPData() {
        echo "Cleaning up old SMTP data...\n";
        
        try {
            $cutoffDate = date('Y-m-d H:i:s', time() - (CLEANUP_SMTP_DATA_DAYS * 24 * 60 * 60));
            
            // Clean old email logs
            $stmt = $this->db->prepare("DELETE FROM email_logs WHERE created_at < ?");
            $emailLogsDeleted = $stmt->execute([$cutoffDate]) ? $stmt->rowCount() : 0;
            
            // Clean old SMTP configs (keep successful ones longer)
            $longCutoffDate = date('Y-m-d H:i:s', time() - (CLEANUP_SMTP_DATA_DAYS * 2 * 24 * 60 * 60));
            $stmt = $this->db->prepare("DELETE FROM smtp_configs WHERE created_at < ? AND test_successful = 0");
            $failedConfigsDeleted = $stmt->execute([$cutoffDate]) ? $stmt->rowCount() : 0;
            
            $stmt = $this->db->prepare("DELETE FROM smtp_configs WHERE created_at < ?");
            $oldConfigsDeleted = $stmt->execute([$longCutoffDate]) ? $stmt->rowCount() : 0;
            
            echo "Deleted $emailLogsDeleted email logs, $failedConfigsDeleted failed configs, $oldConfigsDeleted old configs.\n";
            
        } catch (Exception $e) {
            echo "Error cleaning SMTP data: " . $e->getMessage() . "\n";
        }
    }

    private function cleanupRateLimits() {
        echo "Cleaning up old rate limit entries...\n";
        
        try {
            $cutoffDate = date('Y-m-d H:i:s', time() - (RATE_LIMIT_WINDOW * 2));
            $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE created_at < ?");
            $deleted = $stmt->execute([$cutoffDate]) ? $stmt->rowCount() : 0;
            
            echo "Deleted $deleted rate limit entries.\n";
            
        } catch (Exception $e) {
            echo "Error cleaning rate limits: " . $e->getMessage() . "\n";
        }
    }

    private function cleanupDirectory($path, $cutoffDate) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $deletedFiles = 0;
        $deletedDirs = 0;

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoffDate) {
                if (unlink($file->getPathname())) {
                    $deletedFiles++;
                }
            } elseif ($file->isDir() && !in_array($file->getBasename(), ['.', '..'])) {
                // Try to remove empty directories
                if (@rmdir($file->getPathname())) {
                    $deletedDirs++;
                }
            }
        }

        echo "Deleted $deletedFiles files and $deletedDirs directories from $path.\n";
    }

    public function generateReport() {
        echo "=== Cleanup Report ===\n";
        
        try {
            // SMTP configs statistics
            $stmt = $this->db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN test_successful = 1 THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent
                FROM smtp_configs");
            $stmt->execute();
            $smtpStats = $stmt->fetch();
            
            // Email logs statistics
            $stmt = $this->db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN send_successful = 1 THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as last_24h
                FROM email_logs");
            $stmt->execute();
            $emailStats = $stmt->fetch();
            
            echo "SMTP Configs: {$smtpStats['total']} total, {$smtpStats['successful']} successful, {$smtpStats['recent']} in last 7 days\n";
            echo "Email Logs: {$emailStats['total']} total, {$emailStats['successful']} successful, {$emailStats['last_24h']} in last 24 hours\n";
            
            // Directory sizes
            $attachmentSize = $this->getDirectorySize(ATTACHMENT_PATH);
            $logSize = $this->getDirectorySize(LOG_PATH);
            
            echo "Attachment storage: " . $this->formatBytes($attachmentSize) . "\n";
            echo "Log storage: " . $this->formatBytes($logSize) . "\n";
            
        } catch (Exception $e) {
            echo "Error generating report: " . $e->getMessage() . "\n";
        }
        
        echo "======================\n";
    }

    private function getDirectorySize($path) {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $cleanup = new CleanupManager();
    
    $command = $argv[1] ?? 'cleanup';
    
    switch ($command) {
        case 'cleanup':
            $cleanup->runCleanup();
            break;
        case 'report':
            $cleanup->generateReport();
            break;
        default:
            echo "Usage: php cron_jobs.php [cleanup|report]\n";
            echo "  cleanup - Run all cleanup tasks\n";
            echo "  report  - Generate cleanup report\n";
            break;
    }
} else {
    // Web interface (for manual testing)
    header('Content-Type: text/plain');
    $cleanup = new CleanupManager();
    $cleanup->generateReport();
}
