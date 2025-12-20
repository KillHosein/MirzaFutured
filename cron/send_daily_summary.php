<?php
/**
 * Cron Job - Send Daily Summary
 * Run daily at 2 AM to send daily summaries to users
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/NotificationSystem.php';

try {
    $notificationSystem = new NotificationSystem($pdo);
    
    // Process daily summary notifications
    $count = $notificationSystem->processDailySummaryNotifications();
    
    // Log the result
    error_log("[CRON] Sent $count daily summaries at " . date('Y-m-d H:i:s'));
    
    echo "Successfully sent $count daily summaries\n";
    
} catch (Exception $e) {
    error_log("[CRON] Error sending daily summaries: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>