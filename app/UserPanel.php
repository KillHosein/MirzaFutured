<?php
/**
 * Enhanced User Panel - Account Information, Transaction History, and Active Services
 * Professional user dashboard for Telegram web application
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

class UserPanel {
    
    private $pdo;
    private $telegram;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->telegram = new TelegramAPI();
    }
    
    /**
     * Handle user panel menu
     */
    public function handleUserPanel($userId, $chatId, $section = null, $action = null) {
        try {
            if (!$this->isUserRegistered($userId)) {
                return $this->sendRegistrationRequired($chatId);
            }
            
            switch ($section) {
                case 'profile':
                    return $this->showUserProfile($userId, $chatId, $action);
                    
                case 'transactions':
                    return $this->showTransactionHistory($userId, $chatId, $action);
                    
                case 'services':
                    return $this->showUserServices($userId, $chatId, $action);
                    
                case 'settings':
                    return $this->showUserSettings($userId, $chatId, $action);
                    
                case 'notifications':
                    return $this->showUserNotifications($userId, $chatId, $action);
                    
                case 'support':
                    return $this->showSupportSection($userId, $chatId, $action);
                    
                default:
                    return $this->showUserDashboard($userId, $chatId);
            }
            
        } catch (Exception $e) {
            error_log("User panel error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Show user dashboard
     */
    private function showUserDashboard($userId, $chatId) {
        $user = $this->getUserById($userId);
        $stats = $this->getUserStats($userId);
        
        $balance = number_format($user['balance']);
        $totalTransactions = number_format($stats['total_transactions']);
        $activeServices = $stats['active_services'];
        $totalSpent = number_format($stats['total_spent']);
        
        $message = "👋 <b>سلام {$user['first_name']}!</b>\n\n";
        $message .= "به پنل کاربری خود خوش آمدید.\n\n";
        $message .= "💰 موجودی: <code>{$balance}</code> ریال\n";
        $message .= "📊 تعداد تراکنش‌ها: {$totalTransactions}\n";
        $message .= "🛍️ سرویس‌های فعال: {$activeServices}\n";
        $message .= "💸 مجموع خرید: {$totalSpent} ریال\n\n";
        $message .= "لطفاً یکی از بخش‌های زیر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👤 پروفایل کاربری', 'callback_data' => 'user_profile'],
                    ['text' => '💰 مدیریت مالی', 'callback_data' => 'user_finance']
                ],
                [
                    ['text' => '📊 تراکنش‌ها', 'callback_data' => 'user_transactions'],
                    ['text' => '🛍️ سرویس‌های من', 'callback_data' => 'user_services']
                ],
                [
                    ['text' => '⚙️ تنظیمات', 'callback_data' => 'user_settings'],
                    ['text'� '🔔 اعلان‌ها', 'callback_data' => 'user_notifications']
                ],
                [
                    ['text' => '🆘 پشتیبانی', 'callback_data' => 'user_support'],
                    ['text' => '📱 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show user profile
     */
    private function showUserProfile($userId, $chatId, $action = null) {
        $user = $this->getUserById($userId);
        
        $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
        $username = $user['username'] ? "@{$user['username']}" : 'ثبت نشده';
        $phone = $user['phone_number'] ?: 'ثبت نشده';
        $email = $user['email'] ?: 'ثبت نشده';
        $nationalId = $user['national_id'] ?: 'ثبت نشده';
        $birthDate = $user['birth_date'] ?: 'ثبت نشده';
        $balance = number_format($user['balance']);
        $status = $this->getUserStatusText($user['status']);
        $verificationLevel = $this->getVerificationLevelText($user['verification_level']);
        $createdAt = jdate('Y/m/d', strtotime($user['created_at']));
        $lastSeen = $user['last_seen'] ? jdate('Y/m/d H:i', strtotime($user['last_seen'])) : 'ثبت نشده';
        
        $message = "👤 <b>پروفایل کاربری</b>\n\n";
        $message .= "🆔 شناسه کاربر: <code>{$user['user_id']}</code>\n";
        $message .= "👤 نام کامل: {$fullName}\n";
        $message .= "🔗 نام کاربری: {$username}\n";
        $message .= "📱 تلفن همراه: {$phone}\n";
        $message .= "📧 ایمیل: {$email}\n";
        $message .= "🆔 کد ملی: {$nationalId}\n";
        $message .= "🎂 تاریخ تولد: {$birthDate}\n";
        $message .= "💰 موجودی: <code>{$balance}</code> ریال\n";
        $message .= "📊 وضعیت: {$status}\n";
        $message .= "⭐ سطح تأیید: {$verificationLevel}\n";
        $message .= "📅 تاریخ عضویت: {$createdAt}\n";
        $message .= "🕐 آخرین بازدید: {$lastSeen}\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✏️ ویرایش اطلاعات', 'callback_data' => 'edit_profile']
                ],
                [
                    ['text' => '📍 آدرس‌ها', 'callback_data' => 'user_addresses'],
                    ['text' => '🔐 امنیت', 'callback_data' => 'user_security']
                ],
                [
                    ['text' => '🔄 بروزرسانی', 'callback_data' => 'refresh_profile'],
                    ['text' => '🔙 بازگشت', 'callback_data' => 'user_dashboard']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show transaction history
     */
    private function showTransactionHistory($userId, $chatId, $filter = null) {
        $transactions = $this->getUserTransactions($userId, $filter);
        
        if (empty($transactions)) {
            $message = "📊 <b>تراکنش‌ها</b>\n\n";
            $message .= "هیچ تراکنشی یافت نشد.";
        } else {
            $message = "📊 <b>تراکنش‌های اخیر</b>\n\n";
            
            foreach ($transactions as $transaction) {
                $amount = number_format(abs($transaction['amount']));
                $date = jdate('Y/m/d H:i', strtotime($transaction['created_at']));
                $status = $this->getTransactionStatusText($transaction['status']);
                $type = $this->getTransactionTypeText($transaction['type']);
                $icon = $transaction['amount'] > 0 ? '➕' : '➖';
                
                $message .= "{$icon} {$type}: <code>{$amount}</code> ریال\n";
                $message .= "📅 {$date} - {$status}\n";
                $message .= "🆔 {$transaction['transaction_id']}\n";
                
                if ($transaction['payment_method']) {
                    $paymentMethod = $this->getPaymentMethodText($transaction['payment_method']);
                    $message .= "💳 {$paymentMethod}\n";
                }
                
                $message .= "━━━━━━━━━━━━━━━\n\n";
            }
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📈 گزارش کامل', 'callback_data' => 'transaction_report'],
                    ['text' => '🔍 جستجو', 'callback_data' => 'search_transactions']
                ],
                [
                    ['text' => '💰 واریزها', 'callback_data' => 'filter_deposits'],
                    ['text' => '💸 برداشت‌ها', 'callback_data' => 'filter_withdrawals']
                ],
                [
                    ['text' => '🔄 بروزرسانی', 'callback_data' => 'refresh_transactions'],
                    ['text' => '🔙 بازگشت', 'callback_data' => 'user_dashboard']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show user services
     */
    private function showUserServices($userId, $chatId, $filter = null) {
        $services = $this->getUserServices($userId, $filter);
        
        if (empty($services)) {
            $message = "🛍️ <b>سرویس‌های من</b>\n\n";
            $message .= "شما هیچ سرویس فعالی ندارید.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🛍️ خرید سرویس', 'callback_data' => 'service_browse']
                    ],
                    [
                        ['text' => '🔙 بازگشت', 'callback_data' => 'user_dashboard']
                    ]
                ]
            ];
        } else {
            $message = "🛍️ <b>سرویس‌های من</b>\n\n";
            
            foreach ($services as $service) {
                $expiryDate = jdate('Y/m/d', strtotime($service['expires_at']));
                $status = $this->getServiceStatusText($service['status']);
                $daysLeft = $this->calculateDaysLeft($service['expires_at']);
                
                $message .= "📌 {$service['service_name']}\n";
                $message .= "📅 انقضا: {$expiryDate}\n";
                $message .= "📊 وضعیت: {$status}\n";
                $message .= "⏰ {$daysLeft} روز تا انقضا\n";
                
                if ($service['bandwidth_limit']) {
                    $bandwidth = $this->formatBandwidth($service['bandwidth_limit']);
                    $usedBandwidth = $this->formatBandwidth($service['bandwidth_used']);
                    $message .= "📊 حجم: {$usedBandwidth} / {$bandwidth}\n";
                }
                
                if ($service['device_limit']) {
                    $message .= "📱 دستگاه‌ها: {$service['device_count']}/{$service['device_limit']}\n";
                }
                
                if ($service['server_location']) {
                    $message .= "🌍 سرور: {$service['server_location']}\n";
                }
                
                $message .= "━━━━━━━━━━━━━━━\n\n";
            }
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔄 بروزرسانی', 'callback_data' => 'refresh_services'],
                        ['text' => '📊 گزارش مصرف', 'callback_data' => 'service_usage_report']
                    ],
                    [
                        ['text' => '🔔 تنظیمات اعلان', 'callback_data' => 'service_notification_settings'],
                        ['text' => '⚙️ تنظیمات سرویس', 'callback_data' => 'service_settings']
                    ],
                    [
                        ['text' => '🔙 بازگشت', 'callback_data' => 'user_dashboard']
                    ]
                ]
            ];
        }
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show user settings
     */
    private function showUserSettings($userId, $chatId, $action = null) {
        $user = $this->getUserById($userId);
        
        $message = "⚙️ <b>تنظیمات حساب کاربری</b>\n\n";
        $message .= "زبان: فارسی\n";
        $message .= "منطقه زمانی: {$user['timezone']}\n";
        $message .= "نوتیفیکیشن تلگرام: " . ($user['notification_telegram'] ? '✅ فعال' : '❌ غیرفعال') . "\n";
        $message .= "نوتیفیکیشن ایمیل: " . ($user['notification_email'] ? '✅ فعال' : '❌ غیرفعال') . "\n";
        $message .= "نوتیفیکیشن SMS: " . ($user['notification_sms'] ? '✅ فعال' : '❌ غیرفعال') . "\n";
        $message .= "ورود دوعاملی: " . ($user['two_factor_enabled'] ? '✅ فعال' : '❌ غیرفعال') . "\n\n";
        $message .= "لطفاً تنظیمات مورد نظر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 تغییر زبان', 'callback_data' => 'change_language'],
                    ['text' => '🕐 منطقه زمانی', 'callback_data' => 'change_timezone']
                ],
                [
                    ['text' => '🔔 نوتیفیکیشن‌ها', 'callback_data' => 'notification_settings'],
                    ['text' => '🔐 امنیت', 'callback_data' => 'security_settings']
                ],
                [
                    ['text' => '🎨 تم و ظاهر', 'callback_data' => 'appearance_settings'],
                    ['text' => '🔒 حریم خصوصی', 'callback_data' => 'privacy_settings']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'user_dashboard']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show user notifications
     */
    private function showUserNotifications($userId, $chatId, $filter = null) {
        $notifications = $this->getUserNotifications($userId, $filter);
        
        if (empty($notifications)) {
            $message = "🔔 <b>اعلان‌ها</b>\n\n";
            $message .= "هیچ اعلان جدیدی ندارید.";
        } else {
            $unreadCount = $this->getUnreadNotificationCount($userId);
            $message = "🔔 <b>اعلان‌ها</b>\n";
            $message .= "($unreadCount خوانده نشده)\n\n";
            
            foreach ($notifications as $notification) {
                $date = jdate('Y/m/d H:i', strtotime($notification['created_at']));
                $type = $this->getNotificationTypeIcon($notification['type']);
                $readStatus = $notification['is_read'] ? '✅' : '🔴';
                
                $message .= "{$readStatus} {$type} {$notification['title']}\n";
                $message .= "{$notification['message']}\n";
                $message .= "📅 {$date}\n";
                $message .= "━━━━━━━━━━━━━━━\n\n";
            }
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📖 علامت‌گذاری همه به‌عنوان خوانده شده', 'callback_data' => 'mark_all_read']
                ],
                [
                    ['text' => '🗑️ حذف همه', 'callback_data' => 'clear_all_notifications'],
                    ['text' => '🔔 تنظیمات', 'callback_data' => 'notification_settings']
                ],
                [
                    ['text' => '🔄 بروزرسانی', 'callback_data' => 'refresh_notifications'],
                    ['text' => '🔙 بازگشت', 'callback_data' => 'user_dashboard']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show support section
     */
    private function showSupportSection($userId, $chatId, $action = null) {
        $message = "🆘 <b>پشتیبانی</b>\n\n";
        $message .= "در صورت نیاز به کمک، از یکی از روش‌های زیر استفاده کنید:\n\n";
        $message .= "📞 <b>تماس تلفنی:</b> ۰۲۱-۱۲۳۴۵۶۷۸\n";
        $message .= "📧 <b>ایمیل:</b> support@telegram-web.com\n";
        $message .= "💬 <b>پشتیبانی آنلاین:</b> @SupportBot\n\n";
        $message .= "ساعات کاری: شنبه تا چهارشنبه ۹-۱۷\n\n";
        $message .= "لطفاً قبل از تماس، بخش سوالات متداول را بررسی کنید.";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '❓ سوالات متداول', 'callback_data' => 'faq'],
                    ['text' =& '💬 ارسال تیکت', 'callback_data' => 'create_ticket']
                ],
                [
                    ['text' =& '📋 تیکت‌های من', 'callback_data' => 'my_tickets'],
                    ['text' =& '📞 تماس با ما', 'callback_data' => 'contact_us']
                ],
                [
                    ['text' =& '📖 راهنما', 'callback_data' =& 'user_guide'],
                    ['text' =& '📊 وضعیت سیستم', 'callback_data' =& 'system_status']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'user_dashboard']
                ]
            ]
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' =& $chatId,
            'text' =& $message,
            'reply_markup' =& json_encode($keyboard),
            'parse_mode' =& 'HTML'
        ]);
    }
    
    /**
     * Helper methods
     */
    private function isUserRegistered($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getUserStats($userId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN type = 'purchase' AND amount < 0 THEN ABS(amount) ELSE 0 END) as total_spent,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_services
            FROM transactions t
            LEFT JOIN user_services us ON t.user_id = us.user_id
            WHERE t.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getUserTransactions($userId, $filter = null, $limit = 10) {
        $sql = "SELECT * FROM transactions WHERE user_id = ?";
        $params = [$userId];
        
        if ($filter === 'deposits') {
            $sql .= " AND type = 'deposit'";
        } elseif ($filter === 'withdrawals') {
            $sql .= " AND type = 'withdrawal'";
        } elseif ($filter === 'purchases') {
            $sql .= " AND type = 'purchase'";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getUserServices($userId, $filter = null) {
        $sql = "SELECT * FROM user_services WHERE user_id = ?";
        $params = [$userId];
        
        if ($filter === 'active') {
            $sql .= " AND status = 'active'";
        } elseif ($filter === 'expired') {
            $sql .= " AND status = 'expired'";
        }
        
        $sql .= " ORDER BY expires_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getUserActiveServices($userId) {
        return $this->getUserServices($userId, 'active');
    }
    
    private function getUserNotifications($userId, $filter = null, $limit = 20) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND is_deleted = 0";
        $params = [$userId];
        
        if ($filter === 'unread') {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getUnreadNotificationCount($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND is_deleted = 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    private function getUserStatusText($status) {
        $statuses = [
            'active' => '✅ فعال',
            'inactive' => '❌ غیرفعال',
            'banned' => '🚫 مسدود',
            'pending' => '⏳ در انتظار تأیید'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    private function getVerificationLevelText($level) {
        $levels = [
            0 => '❌ تأیید نشده',
            1 => '✅ پایه',
            2 => '⭐ نقره‌ای',
            3 => '💎 طلایی'
        ];
        
        return $levels[$level] ?? $levels[0];
    }
    
    private function getTransactionStatusText($status) {
        $statuses = [
            'pending' => '⏳ در انتظار',
            'completed' => '✅ تکمیل شده',
            'failed' => '❌ ناموفق',
            'cancelled' => '❌ لغو شده',
            'refunded' => '🔄 بازگشت وجه',
            'disputed' => '⚠️ مورد اختلاف'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    private function getTransactionTypeText($type) {
        $types = [
            'deposit' => 'شارژ حساب',
            'withdrawal' => 'برداشت وجه',
            'purchase' => 'خرید',
            'refund' => 'بازگشت وجه',
            'transfer' => 'انتقال وجه',
            'commission' => 'کارمزد',
            'bonus' => 'پاداش'
        ];
        
        return $types[$type] ?? $type;
    }
    
    private function getPaymentMethodText($method) {
        $methods = [
            'card_to_card' => 'کارت به کارت',
            'bank_transfer' => 'انتقال بانکی',
            'online_payment' => 'پرداخت آنلاین',
            'digital_wallet' => 'کیف پول دیجیتال',
            'cryptocurrency' => 'ارز دیجیتال',
            'cash' => 'نقدی',
            'internal_transfer' => 'انتقال داخلی',
            'wallet' => 'کیف پول'
        ];
        
        return $methods[$method] ?? $method;
    }
    
    private function getServiceStatusText($status) {
        $statuses = [
            'active' => '✅ فعال',
            'suspended' => '⏸️ تعلیق',
            'expired' => '⏰ منقضی',
            'cancelled' => '❌ لغو شده'
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    private function getNotificationTypeIcon($type) {
        $icons = [
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'error' => '❌',
            'success' => '✅',
            'transaction' => '💳',
            'service' => '🛍️',
            'system' => '⚙️'
        ];
        
        return $icons[$type] ?? '🔔';
    }
    
    private function calculateDaysLeft($expiryDate) {
        $now = new DateTime();
        $expiry = new DateTime($expiryDate);
        $diff = $now->diff($expiry);
        
        if ($diff->days === 0) {
            return 'امروز';
        } elseif ($diff->days === 1) {
            return 'فردا';
        } elseif ($diff->days < 0) {
            return 'منقضی شده';
        } else {
            return $diff->days . ' روز';
        }
    }
    
    private function formatBandwidth($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
    
    private function sendRegistrationRequired($chatId) {
        return $this->sendErrorMessage($chatId, "ابتدا باید ثبت‌نام کنید. لطفاً از دستور /start استفاده کنید.");
    }
    
    private function sendErrorMessage($chatId, $message) {
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "❌ خطا: " . $message,
            'parse_mode' => 'HTML'
        ]);
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
    
    public function editMessageText($params) {
        return telegram('editMessageText', $params);
    }
}

?>