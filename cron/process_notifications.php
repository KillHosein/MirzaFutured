<?php
/**
 * Cron Job - Process Scheduled Notifications
 * Run every 5 minutes to process scheduled notifications
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/NotificationSystem.php';

try {
    $notificationSystem = new NotificationSystem($pdo);
    
    // Process scheduled notifications
    $count = $notificationSystem->processScheduledNotifications();
    
    // Log the result
    error_log("[CRON] Processed $count scheduled notifications at " . date('Y-m-d H:i:s'));
    
    echo "Successfully processed $count scheduled notifications\n";
    
} catch (Exception $e) {
    error_log("[CRON] Error processing notifications: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>