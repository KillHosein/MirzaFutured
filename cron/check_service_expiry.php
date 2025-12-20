<?php
/**
 * Cron Job - Check Service Expiry
 * Run every hour to check for expiring services and send notifications
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/NotificationSystem.php';

try {
    $notificationSystem = new NotificationSystem($pdo);
    
    // Process service expiry notifications
    $count = $notificationSystem->processServiceExpiryNotifications();
    
    // Log the result
    error_log("[CRON] Sent $count service expiry notifications at " . date('Y-m-d H:i:s'));
    
    echo "Successfully sent $count service expiry notifications\n";
    
} catch (Exception $e) {
    error_log("[CRON] Error checking service expiry: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>