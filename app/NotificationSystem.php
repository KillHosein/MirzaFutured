<?php
/**
 * Enhanced Notification System and User Alerts
 * Professional notification system for Telegram web application
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

class NotificationSystem {
    
    private $pdo;
    private $telegram;
    private $notificationQueue = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->telegram = new TelegramAPI();
    }
    
    /**
     * Send notification to user
     */
    public function sendNotification($userId, $type, $title, $message, $data = []) {
        try {
            // Store notification in database
            $notificationId = $this->createNotification($userId, $type, $title, $message, $data);
            
            // Send via Telegram
            $telegramResult = $this->sendTelegramNotification($userId, $type, $title, $message, $data);
            
            // Send via email if enabled
            if ($this->isEmailNotificationEnabled($userId)) {
                $this->sendEmailNotification($userId, $type, $title, $message, $data);
            }
            
            // Send via SMS if enabled
            if ($this->isSMSNotificationEnabled($userId)) {
                $this->sendSMSNotification($userId, $type, $title, $message, $data);
            }
            
            // Update notification status
            $this->updateNotificationStatus($notificationId, [
                'telegram_sent' => $telegramResult,
                'sent_at' => date('Y-m-d H:i:s')
            ]);
            
            return $notificationId;
            
        } catch (Exception $e) {
            error_log("Notification sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification in database
     */
    private function createNotification($userId, $type, $title, $message, $data = []) {
        if (!$this->tableExists('notifications')) {
            return null;
        }
        $sql = "INSERT INTO notifications (user_id, title, message, type, related_type, related_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $relatedType = $data['related_type'] ?? null;
        $relatedId = $data['related_id'] ?? null;
        $stmt->execute([$userId, $title, $message, $type, $relatedType, $relatedId]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Send Telegram notification
     */
    private function sendTelegramNotification($userId, $type, $title, $message, $data = []) {
        try {
            // Get user chat ID
            $chatId = $this->getUserChatId($userId);
            
            if (!$chatId) {
                return false;
            }
            
            // Format message based on type
            $formattedMessage = $this->formatTelegramMessage($type, $title, $message, $data);
            
            // Add action buttons if needed
            $keyboard = $this->getNotificationKeyboard($type, $data);
            
            // Send message
            $params = [
                'chat_id' => $chatId,
                'text' => $formattedMessage,
                'parse_mode' => 'HTML',
                'disable_notification' => $this->isSilentNotification($type)
            ];
            
            if ($keyboard) {
                $params['reply_markup'] = json_encode($keyboard);
            }
            
            $result = $this->telegram->sendMessage($params);
            
            return !empty($result['ok']);
            
        } catch (Exception $e) {
            error_log("Telegram notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format message for Telegram
     */
    private function formatTelegramMessage($type, $title, $message, $data = []) {
        $formatted = "🔔 <b>" . htmlspecialchars($title) . "</b>\n\n";
        $formatted .= htmlspecialchars($message) . "\n";
        
        // Add type-specific information
        switch ($type) {
            case 'transaction':
                if (isset($data['amount'])) {
                    $formatted .= "\n💰 مبلغ: <code>" . number_format($data['amount']) . "</code> ریال\n";
                }
                if (isset($data['transaction_id'])) {
                    $formatted .= "🆔 شماره تراکنش: <code>" . $data['transaction_id'] . "</code>\n";
                }
                break;
                
            case 'service':
                if (isset($data['service_name'])) {
                    $formatted .= "\n🛍️ سرویس: " . htmlspecialchars($data['service_name']) . "\n";
                }
                if (isset($data['expiry_date'])) {
                    $formatted .= "📅 انقضا: " . $data['expiry_date'] . "\n";
                }
                break;
                
            case 'security':
                $formatted .= "\n⚠️ <b>توجه:</b> اگر این عملیات توسط شما انجام نشده، فوراً با پشتیبانی تماس بگیرید.\n";
                break;
        }
        
        $formatted .= "\n📅 تاریخ: " . jdate('Y/m/d H:i:s') . "\n";
        
        return $formatted;
    }
    
    /**
     * Get notification keyboard
     */
    private function getNotificationKeyboard($type, $data = []) {
        $keyboard = ['inline_keyboard' => []];
        
        switch ($type) {
            case 'transaction':
                if (isset($data['transaction_id'])) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => '📊 مشاهده جزئیات', 'callback_data' => 'view_transaction:' . $data['transaction_id']]
                    ];
                }
                break;
                
            case 'service':
                if (isset($data['service_id'])) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => '🛍️ مشاهده سرویس', 'callback_data' => 'view_service:' . $data['service_id']]
                    ];
                }
                break;
                
            case 'security':
                $keyboard['inline_keyboard'][] = [
                    ['text' => '🔐 بررسی امنیت', 'callback_data' => 'security_check']
                ];
                break;
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '✅ علامت‌گذاری به‌عنوان خوانده شده', 'callback_data' => 'mark_read:' . ($data['notification_id'] ?? 0)]
        ];
        
        return $keyboard;
    }
    
    /**
     * Check if notification should be silent
     */
    private function isSilentNotification($type) {
        $silentTypes = ['system', 'marketing', 'reminder'];
        return in_array($type, $silentTypes);
    }
    
    /**
     * Check if email notification is enabled for user
     */
    private function isEmailNotificationEnabled($userId) {
        $stmt = $this->pdo->prepare("SELECT notification_email FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() == 1;
    }
    
    /**
     * Check if SMS notification is enabled for user
     */
    private function isSMSNotificationEnabled($userId) {
        $stmt = $this->pdo->prepare("SELECT notification_sms FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() == 1;
    }
    
    /**
     * Get user chat ID
     */
    private function getUserChatId($userId) {
        // This would typically be stored in the users table or a separate table
        // For now, we'll assume user_id is the chat_id
        return $userId;
    }
    
    /**
     * Update notification status
     */
    private function updateNotificationStatus($notificationId, $status) {
        if (!$this->tableExists('notifications')) {
            return true;
        }
        $sql = "UPDATE notifications SET ";
        $params = [];
        $updates = [];
        
        foreach ($status as $key => $value) {
            $updates[] = "$key = ?";
            $params[] = $value;
        }
        
        $sql .= implode(', ', $updates) . " WHERE id = ?";
        $params[] = $notificationId;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        if (!$this->tableExists('notifications')) {
            return true;
        }
        $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$notificationId, $userId]);
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($userId) {
        if (!$this->tableExists('notifications')) {
            return true;
        }
        $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId]);
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($userId) {
        if (!$this->tableExists('notifications')) {
            return 0;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND is_deleted = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $limit = 20, $offset = 0) {
        if (!$this->tableExists('notifications')) {
            return [];
        }
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND is_deleted = 0 ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete notification
     */
    public function deleteNotification($notificationId, $userId) {
        if (!$this->tableExists('notifications')) {
            return true;
        }
        $sql = "UPDATE notifications SET is_deleted = 1 WHERE id = ? AND user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$notificationId, $userId]);
    }
    
    /**
     * Clear all notifications for user
     */
    public function clearAllNotifications($userId) {
        if (!$this->tableExists('notifications')) {
            return true;
        }
        $sql = "UPDATE notifications SET is_deleted = 1 WHERE user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId]);
    }
    
    /**
     * Scheduled notification methods
     */
    public function scheduleNotification($userId, $type, $title, $message, $scheduledTime, $data = []) {
        if (!$this->tableExists('notifications')) {
            return null;
        }
        $sql = "INSERT INTO notifications (user_id, title, message, type, related_type, related_id, scheduled_for, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        
        $relatedType = $data['related_type'] ?? null;
        $relatedId = $data['related_id'] ?? null;
        
        $stmt->execute([$userId, $title, $message, $type, $relatedType, $relatedId, $scheduledTime]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Process scheduled notifications
     */
    public function processScheduledNotifications() {
        try {
            if (!$this->tableExists('notifications')) {
                return 0;
            }
            $now = date('Y-m-d H:i:s');
            
            $sql = "SELECT * FROM notifications WHERE scheduled_for <= ? AND sent_at IS NULL AND is_deleted = 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$now]);
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($notifications as $notification) {
                $this->sendNotification(
                    $notification['user_id'],
                    $notification['type'],
                    $notification['title'],
                    $notification['message'],
                    [
                        'notification_id' => $notification['id'],
                        'related_type' => $notification['related_type'],
                        'related_id' => $notification['related_id']
                    ]
                );
            }
            
            return count($notifications);
            
        } catch (Exception $e) {
            error_log("Scheduled notification processing error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Bulk notification methods
     */
    public function sendBulkNotification($userIds, $type, $title, $message, $data = []) {
        $results = [];
        
        foreach ($userIds as $userId) {
            $results[$userId] = $this->sendNotification($userId, $type, $title, $message, $data);
        }
        
        return $results;
    }

    private function tableExists($name) {
        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = ?");
        $stmt->execute([$name]);
        return (bool)$stmt->fetchColumn();
    }
    
    /**
     * Service expiry notifications
     */
    public function sendServiceExpiryNotification($userId, $serviceName, $expiryDate, $daysLeft) {
        $title = "⏰ انقضای سرویس نزدیک است";
        
        if ($daysLeft <= 0) {
            $message = "سرویس {$serviceName} منقضی شده است. برای تمدید اقدام کنید.";
        } elseif ($daysLeft == 1) {
            $message = "سرویس {$serviceName} فردا منقضی می‌شود.";
        } elseif ($daysLeft <= 3) {
            $message = "سرویس {$serviceName} در {$daysLeft} روز آینده منقضی می‌شود.";
        } else {
            $message = "سرویس {$serviceName} در تاریخ {$expiryDate} منقضی می‌شود.";
        }
        
        return $this->sendNotification($userId, 'service', $title, $message, [
            'service_name' => $serviceName,
            'expiry_date' => $expiryDate,
            'days_left' => $daysLeft
        ]);
    }
    
    /**
     * Transaction notifications
     */
    public function sendTransactionNotification($userId, $transactionType, $amount, $status, $transactionId = null) {
        $statusTexts = [
            'pending' => 'در انتظار تأیید',
            'completed' => 'تکیل شده',
            'failed' => 'ناموفق',
            'cancelled' => 'لغو شده'
        ];
        
        $typeTexts = [
            'deposit' => 'واریز',
            'withdrawal' => 'برداشت',
            'purchase' => 'خرید',
            'refund' => 'بازگشت وجه',
            'transfer' => 'انتقال'
        ];
        
        $title = "💳 تراکنش " . $typeTexts[$transactionType] ?? $transactionType;
        $message = "تراکنش " . $typeTexts[$transactionType] ?? $transactionType . " شما با مبلغ " . number_format($amount) . " ریال " . $statusTexts[$status] ?? $status . " شد.";
        
        return $this->sendNotification($userId, 'transaction', $title, $message, [
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'status' => $status,
            'transaction_id' => $transactionId
        ]);
    }
    
    /**
     * Security notifications
     */
    public function sendSecurityNotification($userId, $event, $details = []) {
        $events = [
            'login' => ['title' => '🔐 ورود به حساب', 'message' => 'ورود جدید به حساب کاربری شما شناسایی شد.'],
            'password_change' => ['title' => '🔑 تغییر رمز عبور', 'message' => 'رمز عبور حساب شما تغییر یافت.'],
            'two_factor_enabled' => ['title' => '🔐 فعال‌سازی احراز هویت دوعاملی', 'message' => 'احراز هویت دوعاملی برای حساب شما فعال شد.'],
            'suspicious_activity' => ['title' => '⚠️ فعالیت مشکوک', 'message' => 'فعالیت مشکوک در حساب شما شناسایی شد.'],
            'account_locked' => ['title' => '🔒 حساب مسدود شد', 'message' => 'حساب شما به دلایل امنیتی موقتاً مسدود شد.']
        ];
        
        if (isset($events[$event])) {
            $title = $events[$event]['title'];
            $message = $events[$event]['message'];
            
            if (!empty($details)) {
                $message .= "\n\nجزئیات: " . implode(', ', $details);
            }
            
            return $this->sendNotification($userId, 'security', $title, $message);
        }
        
        return false;
    }
    
    /**
     * System notifications
     */
    public function sendSystemNotification($userId, $event, $details = []) {
        $events = [
            'maintenance' => ['title' => '⚙️ تعمیرات سیستم', 'message' => 'سیستم در حال تعمیرات است. ممکن است برخی خدمات موقتاً در دسترس نباشند.'],
            'update' => ['title' => '🔄 بروزرسانی سیستم', 'message' => 'سیستم به‌روزرسانی شد. ویژگی‌های جدید در دسترس هستند.'],
            'announcement' => ['title' => '📢 اطلاعیه', 'message' => 'اطلاعیه جدید از سوی مدیریت.'],
            'promotion' => ['title' => '🎁 پیشنهاد ویژه', 'message' => 'پیشنهاد ویژه‌ای برای شما فعال شده است.']
        ];
        
        if (isset($events[$event])) {
            $title = $events[$event]['title'];
            $message = $events[$event]['message'];
            
            if (!empty($details)) {
                $message .= "\n\nجزئیات: " . implode(', ', $details);
            }
            
            return $this->sendNotification($userId, 'system', $title, $message);
        }
        
        return false;
    }
    
    /**
     * Marketing and promotional notifications
     */
    public function sendPromotionalNotification($userId, $promotionType, $details = []) {
        $promotions = [
            'discount' => ['title' => '🏷️ تخفیف ویژه', 'message' => 'کد تخفیف ویژه‌ای برای شما فعال شده است.'],
            'new_service' => ['title' => '🆕 سرویس جدید', 'message' => 'سرویس جدیدی به فروشگاه افزوده شده است.'],
            'referral_bonus' => ['title' => '👥 پاداش معرفی', 'message' => 'پاداش معرفی دوستان به حساب شما افزوده شد.'],
            'loyalty_reward' => ['title' => '⭐ پاداش وفاداری', 'message' => 'به دلیل وفاداری شما، پاداشی دریافت کردید.']
        ];
        
        if (isset($promotions[$promotionType])) {
            $title = $promotions[$promotionType]['title'];
            $message = $promotions[$promotionType]['message'];
            
            if (!empty($details)) {
                $message .= "\n\nجزئیات: " . implode(', ', $details);
            }
            
            return $this->sendNotification($userId, 'marketing', $title, $message);
        }
        
        return false;
    }
    
    /**
     * Process service expiry notifications (run via cron)
     */
    public function processServiceExpiryNotifications() {
        try {
            // Get services expiring in 1, 3, and 7 days
            $expiryDays = [1, 3, 7];
            $notifiedCount = 0;
            
            foreach ($expiryDays as $days) {
                $expiryDate = date('Y-m-d', strtotime("+{$days} days"));
                
                $sql = "SELECT us.*, u.user_id, s.name as service_name 
                        FROM user_services us 
                        JOIN users u ON us.user_id = u.user_id 
                        JOIN services s ON us.service_id = s.id 
                        WHERE DATE(us.expires_at) = ? AND us.status = 'active' 
                        AND NOT EXISTS (
                            SELECT 1 FROM notifications 
                            WHERE user_id = u.user_id 
                            AND type = 'service' 
                            AND related_type = 'service' 
                            AND related_id = us.id 
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        )";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$expiryDate]);
                $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($services as $service) {
                    $this->sendServiceExpiryNotification(
                        $service['user_id'],
                        $service['service_name'],
                        jdate('Y/m/d', strtotime($service['expires_at'])),
                        $days
                    );
                    $notifiedCount++;
                }
            }
            
            return $notifiedCount;
            
        } catch (Exception $e) {
            error_log("Service expiry notification processing error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Process daily summary notifications (run via cron)
     */
    public function processDailySummaryNotifications() {
        try {
            // Get users who want daily summaries
            $sql = "SELECT user_id FROM users WHERE daily_summary_enabled = 1";
            $stmt = $this->pdo->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sentCount = 0;
            
            foreach ($users as $user) {
                $this->sendDailySummary($user['user_id']);
                $sentCount++;
            }
            
            return $sentCount;
            
        } catch (Exception $e) {
            error_log("Daily summary notification processing error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Send daily summary to user
     */
    private function sendDailySummary($userId) {
        try {
            // Get yesterday's statistics for user
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            // Get user's transactions
            $stmt = $this->pdo->prepare("SELECT 
                COUNT(*) as transaction_count,
                SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as deposits,
                SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as withdrawals,
                SUM(CASE WHEN type = 'purchase' AND status = 'completed' THEN amount ELSE 0 END) as purchases
                FROM transactions 
                WHERE user_id = ? AND DATE(created_at) = ? AND status = 'completed'");
            
            $stmt->execute([$userId, $yesterday]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get service expiry info
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as expiring_soon FROM user_services WHERE user_id = ? AND status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)");
            $stmt->execute([$userId]);
            $expiringCount = $stmt->fetchColumn();
            
            // Format message
            $title = "📊 خلاصه روزانه";
            $message = "سلام! خلاصه فعالیت‌های دیروز شما:\n\n";
            
            if ($stats['transaction_count'] > 0) {
                $message .= "💳 تراکنش‌ها: " . $stats['transaction_count'] . " عدد\n";
                
                if ($stats['deposits'] > 0) {
                    $message .= "💰 واریز: " . number_format($stats['deposits']) . " ریال\n";
                }
                
                if ($stats['withdrawals'] > 0) {
                    $message .= "💸 برداشت: " . number_format($stats['withdrawals']) . " ریال\n";
                }
                
                if ($stats['purchases'] > 0) {
                    $message .= "🛍️ خرید: " . number_format($stats['purchases']) . " ریال\n";
                }
                
                $message .= "\n";
            }
            
            if ($expiringCount > 0) {
                $message .= "⚠️ " . $expiringCount . " سرویس شما در هفته آینده منقضی می‌شود.\n\n";
            }
            
            $message .= "برای مشاهده جزئیات بیشتر به پنل کاربری خود مراجعه کنید.";
            
            return $this->sendNotification($userId, 'system', $title, $message);
            
        } catch (Exception $e) {
            error_log("Daily summary sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Email notification methods (placeholders)
     */
    private function sendEmailNotification($userId, $type, $title, $message, $data = []) {
        // This would integrate with an email service
        // For now, we'll just log it
        error_log("Email notification to user {$userId}: {$title} - {$message}");
        return true;
    }
    
    /**
     * SMS notification methods (placeholders)
     */
    private function sendSMSNotification($userId, $type, $title, $message, $data = []) {
        // This would integrate with an SMS service
        // For now, we'll just log it
        error_log("SMS notification to user {$userId}: {$title} - {$message}");
        return true;
    }
    
    /**
     * Notification settings management
     */
    public function updateNotificationSettings($userId, $settings) {
        $allowedSettings = ['notification_telegram', 'notification_email', 'notification_sms', 'daily_summary_enabled'];
        
        $updates = [];
        $params = [];
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedSettings)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Get notification settings
     */
    public function getNotificationSettings($userId) {
        $stmt = $this->pdo->prepare("SELECT notification_telegram, notification_email, notification_sms, daily_summary_enabled FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/**
 * Telegram API Wrapper Class
 */
class TelegramAPI {
    
    public function sendMessage($params) {
        return telegram('sendMessage', $params);
    }
    
    public function answerCallbackQuery($params) {
        return telegram('answerCallbackQuery', $params);
    }
}

/**
 * Cron job for processing scheduled notifications
 */
function processScheduledNotificationsCron() {
    global $pdo;
    
    $notificationSystem = new NotificationSystem($pdo);
    
    // Process scheduled notifications
    $scheduledCount = $notificationSystem->processScheduledNotifications();
    
    // Process service expiry notifications
    $expiryCount = $notificationSystem->processServiceExpiryNotifications();
    
    // Process daily summaries
    $summaryCount = $notificationSystem->processDailySummaryNotifications();
    
    return [
        'scheduled' => $scheduledCount,
        'expiry' => $expiryCount,
        'summaries' => $summaryCount
    ];
}

?>
