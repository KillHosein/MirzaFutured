<?php
/**
 * Cron Job - Cleanup Old Data
 * Run weekly to clean up old data and optimize database
 */

require_once __DIR__ . '/../config.php';

try {
    // Clean up old notifications (older than 90 days)
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND is_deleted = 1");
    $deletedNotifications = $stmt->execute() ? $stmt->rowCount() : 0;
    
    // Clean up old system logs (older than 30 days)
    $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $deletedLogs = $stmt->execute() ? $stmt->rowCount() : 0;
    
    // Clean up old admin logs (older than 180 days)
    $stmt = $pdo->prepare("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
    $deletedAdminLogs = $stmt->execute() ? $stmt->rowCount() : 0;
    
    // Clean up expired sessions
    $stmt = $pdo->prepare("DELETE FROM registration_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND completed = 1");
    $deletedSessions = $stmt->execute() ? $stmt->rowCount() : 0;
    
    // Clean up old financial sessions
    $stmt = $pdo->prepare("DELETE FROM financial_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) AND completed = 1");
    $deletedFinancialSessions = $stmt->execute() ? $stmt->rowCount() : 0;
    
    // Optimize tables
    $tables = ['users', 'transactions', 'notifications', 'system_logs', 'admin_logs'];
    foreach ($tables as $table) {
        $pdo->exec("OPTIMIZE TABLE $table");
    }
    
    // Log the cleanup results
    error_log("[CRON] Cleanup completed at " . date('Y-m-d H:i:s'));
    error_log("[CRON] Deleted notifications: $deletedNotifications");
    error_log("[CRON] Deleted system logs: $deletedLogs");
    error_log("[CRON] Deleted admin logs: $deletedAdminLogs");
    error_log("[CRON] Deleted sessions: $deletedSessions");
    error_log("[CRON] Deleted financial sessions: $deletedFinancialSessions");
    
    echo "Cleanup completed successfully\n";
    echo "Deleted notifications: $deletedNotifications\n";
    echo "Deleted system logs: $deletedLogs\n";
    echo "Deleted admin logs: $deletedAdminLogs\n";
    echo "Deleted sessions: $deletedSessions\n";
    echo "Deleted financial sessions: $deletedFinancialSessions\n";
    
} catch (Exception $e) {
    error_log("[CRON] Error during cleanup: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>