<?php
/**
 * Enhanced Admin Dashboard - Reporting and Statistics
 * Professional admin panel for Telegram web application
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

class AdminDashboard {
    
    private $pdo;
    private $telegram;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->telegram = new TelegramAPI();
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Handle admin dashboard
     */
    public function handleAdminDashboard($adminId, $chatId, $section = null, $action = null) {
        try {
            if (!$this->isAdmin($adminId)) {
                return $this->sendAccessDenied($chatId);
            }
            
            switch ($section) {
                case 'overview':
                    return $this->showOverview($adminId, $chatId);
                    
                case 'users':
                    return $this->handleUsersSection($adminId, $chatId, $action);
                    
                case 'transactions':
                    return $this->handleTransactionsSection($adminId, $chatId, $action);
                    
                case 'services':
                    return $this->handleServicesSection($adminId, $chatId, $action);
                    
                case 'financial':
                    return $this->handleFinancialSection($adminId, $chatId, $action);
                    
                case 'reports':
                    return $this->handleReportsSection($adminId, $chatId, $action);
                    
                case 'settings':
                    return $this->handleSettingsSection($adminId, $chatId, $action);
                    
                case 'notifications':
                    return $this->handleNotificationsSection($adminId, $chatId, $action);
                    
                default:
                    return $this->showAdminMainMenu($adminId, $chatId);
            }
            
        } catch (Exception $e) {
            error_log("Admin dashboard error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Show admin main menu
     */
    private function showAdminMainMenu($adminId, $chatId) {
        $stats = $this->getSystemStats();
        
        $message = "🛠️ <b>پنل مدیریت</b>\n\n";
        $message .= "📊 <b>آمار کلی سیستم:</b>\n\n";
        $message .= "👥 کاربران: <code>" . number_format($stats['total_users']) . "</code>\n";
        $message .= "✅ کاربران فعال: <code>" . number_format($stats['active_users']) . "</code>\n";
        $message .= "💰 موجودی کل: <code>" . number_format($stats['total_balance']) . "</code> ریال\n";
        $message .= "📈 تراکنش‌های امروز: <code>" . number_format($stats['today_transactions']) . "</code>\n";
        $message .= "🛍️ سرویس‌های فعال: <code>" . number_format($stats['active_services']) . "</code>\n";
        $message .= "📊 فروش امروز: <code>" . number_format($stats['today_sales']) . "</code> ریال\n\n";
        $message .= "لطفاً بخش مورد نظر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👥 مدیریت کاربران', 'callback_data' => 'admin_users'],
                    ['text' => '💳 تراکنش‌ها', 'callback_data' => 'admin_transactions']
                ],
                [
                    ['text' => '🛍️ سرویس‌ها', 'callback_data' => 'admin_services'],
                    ['text' => '💰 گزارش مالی', 'callback_data' => 'admin_financial']
                ],
                [
                    ['text' => '📊 گزارش‌ها و آمار', 'callback_data' => 'admin_reports'],
                    ['text' => '⚙️ تنظیمات سیستم', 'callback_data' => 'admin_settings']
                ],
                [
                    ['text' => '🔔 اعلان‌ها', 'callback_data' => 'admin_notifications'],
                    ['text' => '🚪 خروج', 'callback_data' => 'admin_logout']
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
     * Handle users section
     */
    private function handleUsersSection($adminId, $chatId, $action = null) {
        switch ($action) {
            case 'list':
                return $this->showUsersList($adminId, $chatId);
                
            case 'search':
                return $this->showUserSearch($adminId, $chatId);
                
            case 'pending':
                return $this->showPendingUsers($adminId, $chatId);
                
            case 'banned':
                return $this->showBannedUsers($adminId, $chatId);
                
            case 'vip':
                return $this->showVIPUsers($adminId, $chatId);
                
            default:
                return $this->showUsersMenu($adminId, $chatId);
        }
    }
    
    /**
     * Show users menu
     */
    private function showUsersMenu($adminId, $chatId) {
        $stats = $this->getUsersStats();
        
        $message = "👥 <b>مدیریت کاربران</b>\n\n";
        $message .= "📊 <b>آمار کاربران:</b>\n\n";
        $message .= "کل کاربران: <code>" . number_format($stats['total']) . "</code>\n";
        $message .= "کاربران فعال: <code>" . number_format($stats['active']) . "</code>\n";
        $message .= "کاربران در انتظار: <code>" . number_format($stats['pending']) . "</code>\n";
        $message .= "کاربران مسدود: <code>" . number_format($stats['banned']) . "</code>\n";
        $message .= "کاربران جدید امروز: <code>" . number_format($stats['today_new']) . "</code>\n\n";
        $message .= "لطفاً عملیات مورد نظر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 لیست کاربران', 'callback_data' => 'admin_users_list'],
                    ['text' => '🔍 جستجوی کاربر', 'callback_data' => 'admin_users_search']
                ],
                [
                    ['text' => '⏳ کاربران در انتظار', 'callback_data' => 'admin_users_pending'],
                    ['text' => '🚫 کاربران مسدود', 'callback_data' => 'admin_users_banned']
                ],
                [
                    ['text' =& '⭐ کاربران ویژه', 'callback_data' =& 'admin_users_vip'],
                    ['text' =& '📈 گزارش فعالیت', 'callback_data' =& 'admin_users_report']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_main']
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
     * Handle transactions section
     */
    private function handleTransactionsSection($adminId, $chatId, $action = null) {
        switch ($action) {
            case 'pending':
                return $this->showPendingTransactions($adminId, $chatId);
                
            case 'deposits':
                return $this->showDeposits($adminId, $chatId);
                
            case 'withdrawals':
                return $this->showWithdrawals($adminId, $chatId);
                
            case 'purchases':
                return $this->showPurchases($adminId, $chatId);
                
            case 'search':
                return $this->showTransactionSearch($adminId, $chatId);
                
            case 'statistics':
                return $this->showTransactionStatistics($adminId, $chatId);
                
            default:
                return $this->showTransactionsMenu($adminId, $chatId);
        }
    }
    
    /**
     * Show transactions menu
     */
    private function showTransactionsMenu($adminId, $chatId) {
        $stats = $this->getTransactionsStats();
        
        $message = "💳 <b>مدیریت تراکنش‌ها</b>\n\n";
        $message .= "📊 <b>آمار تراکنش‌ها:</b>\n\n";
        $message .= "کل تراکنش‌ها: <code>" . number_format($stats['total']) . "</code>\n";
        $message .= "تراکنش‌های موفق: <code>" . number_format($stats['successful']) . "</code>\n";
        $message .= "تراکنش‌های در انتظار: <code>" . number_format($stats['pending']) . "</code>\n";
        $message .= "تراکنش‌های ناموفق: <code>" . number_format($stats['failed']) . "</code>\n";
        $message .= "مبلغ کل: <code>" . number_format($stats['total_amount']) . "</code> ریال\n\n";
        $message .= "لطفاً بخش مورد نظر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⏳ تراکنش‌های در انتظار', 'callback_data' => 'admin_transactions_pending'],
                    ['text' =& '💰 واریزها', 'callback_data' =& 'admin_transactions_deposits']
                ],
                [
                    ['text' =& '💸 برداشت‌ها', 'callback_data' =& 'admin_transactions_withdrawals'],
                    ['text' =& '🛍️ خریدها', 'callback_data' =& 'admin_transactions_purchases']
                ],
                [
                    ['text' =& '🔍 جستجوی تراکنش', 'callback_data' =& 'admin_transactions_search'],
                    ['text' =& '📈 آمار و گزارش', 'callback_data' =& 'admin_transactions_statistics']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_main']
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
     * Handle financial section
     */
    private function handleFinancialSection($adminId, $chatId, $action = null) {
        switch ($action) {
            case 'overview':
                return $this->showFinancialOverview($adminId, $chatId);
                
            case 'daily':
                return $this->showDailyFinancialReport($adminId, $chatId);
                
            case 'monthly':
                return $this->showMonthlyFinancialReport($adminId, $chatId);
                
            case 'gateways':
                return $this->showPaymentGateways($adminId, $chatId);
                
            case 'commissions':
                return $this->showCommissions($adminId, $chatId);
                
            default:
                return $this->showFinancialMenu($adminId, $chatId);
        }
    }
    
    /**
     * Show financial menu
     */
    private function showFinancialMenu($adminId, $chatId) {
        $stats = $this->getFinancialStats();
        
        $message = "💰 <b>گزارش مالی</b>\n\n";
        $message .= "📊 <b>آمار مالی:</b>\n\n";
        $message .= "کل واریزها: <code>" . number_format($stats['total_deposits']) . "</code> ریال\n";
        $message .= "کل برداشت‌ها: <code>" . number_format($stats['total_withdrawals']) . "</code> ریال\n";
        $message .= "کل فروش: <code>" . number_format($stats['total_sales']) . "</code> ریال\n";
        $message .= "سود خالص: <code>" . number_format($stats['net_profit']) . "</code> ریال\n";
        $message .= "کارمزدها: <code>" . number_format($stats['total_commissions']) . "</code> ریال\n\n";
        $message .= "لطفاً گزارش مورد نظر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' =& '📋 گزارش کلی', 'callback_data' =& 'admin_financial_overview'],
                    ['text' =& '📅 گزارش روزانه', 'callback_data' =& 'admin_financial_daily']
                ],
                [
                    ['text' =& '📆 گزارش ماهانه', 'callback_data' =& 'admin_financial_monthly'],
                    ['text' =& '🌐 درگاه‌های پرداخت', 'callback_data' =& 'admin_financial_gateways']
                ],
                [
                    ['text' =& '💸 کارمزدها', 'callback_data' =& 'admin_financial_commissions'],
                    ['text' =& '📈 نمودارها', 'callback_data' =& 'admin_financial_charts']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_main']
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
     * Handle reports section
     */
    private function handleReportsSection($adminId, $chatId, $action = null) {
        switch ($action) {
            case 'users':
                return $this->showUsersReport($adminId, $chatId);
                
            case 'financial':
                return $this->showFinancialReport($adminId, $chatId);
                
            case 'services':
                return $this->showServicesReport($adminId, $chatId);
                
            case 'system':
                return $this->showSystemReport($adminId, $chatId);
                
            case 'export':
                return $this->showExportOptions($adminId, $chatId);
                
            default:
                return $this->showReportsMenu($adminId, $chatId);
        }
    }
    
    /**
     * Show reports menu
     */
    private function showReportsMenu($adminId, $chatId) {
        $message = "📊 <b>گزارش‌ها و آمار</b>\n\n";
        $message .= "لطفاً نوع گزارش مورد نظر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' =& '👥 گزارش کاربران', 'callback_data' =& 'admin_reports_users'],
                    ['text' =& '💰 گزارش مالی', 'callback_data' =& 'admin_reports_financial']
                ],
                [
                    ['text' =& '🛍️ گزارش سرویس‌ها', 'callback_data' =& 'admin_reports_services'],
                    ['text' =& '⚙️ گزارش سیستم', 'callback_data' =& 'admin_reports_system']
                ],
                [
                    ['text' =& '📤 خروجی اکسل/CSV', 'callback_data' =& 'admin_reports_export'],
                    ['text' =& '📈 نمودارها', 'callback_data' =& 'admin_reports_charts']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_main']
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
     * Handle settings section
     */
    private function handleSettingsSection($adminId, $chatId, $action = null) {
        switch ($action) {
            case 'general':
                return $this->showGeneralSettings($adminId, $chatId);
                
            case 'payment':
                return $this->showPaymentSettings($adminId, $chatId);
                
            case 'services':
                return $this->showServicesSettings($adminId, $chatId);
                
            case 'notifications':
                return $this->showNotificationSettings($adminId, $chatId);
                
            case 'security':
                return $this->showSecuritySettings($adminId, $chatId);
                
            default:
                return $this->showSettingsMenu($adminId, $chatId);
        }
    }
    
    /**
     * Show settings menu
     */
    private function showSettingsMenu($adminId, $chatId) {
        $message = "⚙️ <b>تنظیمات سیستم</b>\n\n";
        $message .= "لطفاً بخش مورد نظر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' =& '🔧 تنظیمات عمومی', 'callback_data' =& 'admin_settings_general'],
                    ['text' =& '💳 تنظیمات پرداخت', 'callback_data' =& 'admin_settings_payment']
                ],
                [
                    ['text' =& '🛍️ تنظیمات سرویس‌ها', 'callback_data' =& 'admin_settings_services'],
                    ['text' =& '🔔 تنظیمات اعلان‌ها', 'callback_data' =& 'admin_settings_notifications']
                ],
                [
                    ['text' =& '🔐 تنظیمات امنیت', 'callback_data' =& 'admin_settings_security'],
                    ['text' =& '📝 لاگ‌های سیستم', 'callback_data' =& 'admin_settings_logs']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_main']
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
     * Show overview statistics
     */
    private function showOverview($adminId, $chatId) {
        $stats = $this->getDetailedSystemStats();
        
        $message = "📊 <b>گزارش کلی سیستم</b>\n\n";
        $message .= "📅 تاریخ: " . jdate('Y/m/d') . "\n\n";
        
        $message .= "<b>👥 کاربران:</b>\n";
        $message .= "• کل کاربران: <code>" . number_format($stats['total_users']) . "</code>\n";
        $message .= "• کاربران فعال امروز: <code>" . number_format($stats['active_today']) . "</code>\n";
        $message .= "• کاربران جدید امروز: <code>" . number_format($stats['new_today']) . "</code>\n\n";
        
        $message .= "<b>💰 مالی:</b>\n";
        $message .= "• واریز امروز: <code>" . number_format($stats['deposits_today']) . "</code> ریال\n";
        $message .= "• برداشت امروز: <code>" . number_format($stats['withdrawals_today']) . "</code> ریال\n";
        $message .= "• فروش امروز: <code>" . number_format($stats['sales_today']) . "</code> ریال\n\n";
        
        $message .= "<b>🛍️ سرویس‌ها:</b>\n";
        $message .= "• سرویس‌های فعال: <code>" . number_format($stats['active_services']) . "</code>\n";
        $message .= "• سرویس‌های منقضی امروز: <code>" . number_format($stats['expired_today']) . "</code>\n\n";
        
        $message .= "<b>📈 عملکرد:</b>\n";
        $message .= "• نرخ رشد کاربران: <code>" . $stats['user_growth_rate'] . "%</code>\n";
        $message .= "• نرخ تبدیل: <code>" . $stats['conversion_rate'] . "%</code>\n";
        $message .= "• میانگین خرید: <code>" . number_format($stats['avg_purchase']) . "</code> ریال\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' =& '🔄 بروزرسانی', 'callback_data' =& 'admin_overview_refresh'],
                    ['text' =& '📊 جزئیات بیشتر', 'callback_data' =& 'admin_overview_details']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_main']
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
     * Show pending transactions for approval
     */
    private function showPendingTransactions($adminId, $chatId) {
        $pendingTransactions = $this->getPendingTransactions();
        
        if (empty($pendingTransactions)) {
            $message = "✅ <b>تراکنش در انتظاری وجود ندارد</b>\n\n";
            $message .= "همه تراکنش‌ها بررسی شده‌اند.";
        } else {
            $message = "⏳ <b>تراکنش‌های در انتظار تأیید</b>\n\n";
            $message .= "تعداد: <code>" . count($pendingTransactions) . "</code>\n\n";
            
            foreach ($pendingTransactions as $transaction) {
                $user = $this->getUserById($transaction['user_id']);
                $amount = number_format($transaction['amount']);
                $date = jdate('Y/m/d H:i', strtotime($transaction['created_at']));
                $type = $this->getTransactionTypeText($transaction['type']);
                $method = $this->getPaymentMethodText($transaction['payment_method']);
                
                $message .= "🆔 <b>{$transaction['transaction_id']}</b>\n";
                $message .= "👤 کاربر: {$user['first_name']} {$user['last_name']}\n";
                $message .= "💰 مبلغ: <code>{$amount}</code> ریال\n";
                $message .= "🔄 نوع: {$type}\n";
                $message .= "💳 روش: {$method}\n";
                $message .= "📅 تاریخ: {$date}\n";
                
                if ($transaction['admin_notes']) {
                    $message .= "📝 یادداشت: {$transaction['admin_notes']}\n";
                }
                
                $message .= "\n";
            }
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' =& '🔄 بروزرسانی', 'callback_data' =& 'admin_transactions_pending_refresh'],
                    ['text' =& '📊 آمار تراکنش‌ها', 'callback_data' =& 'admin_transactions_statistics']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_transactions']
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
     * Show daily financial report
     */
    private function showDailyFinancialReport($adminId, $chatId) {
        $report = $this->getDailyFinancialReport();
        
        $message = "📅 <b>گزارش مالی روزانه</b>\n\n";
        $message .= "📅 تاریخ: " . jdate('Y/m/d') . "\n\n";
        
        $message .= "<b>💰 واریزها:</b>\n";
        $message .= "• تعداد: <code>" . number_format($report['deposit_count']) . "</code>\n";
        $message .= "• مبلغ: <code>" . number_format($report['deposit_amount']) . "</code> ریال\n\n";
        
        $message .= "<b>💸 برداشت‌ها:</b>\n";
        $message .= "• تعداد: <code>" . number_format($report['withdrawal_count']) . "</code>\n";
        $message .= "• مبلغ: <code>" . number_format($report['withdrawal_amount']) . "</code> ریال\n\n";
        
        $message .= "<b>🛍️ خریدها:</b>\n";
        $message .= "• تعداد: <code>" . number_format($report['purchase_count']) . "</code>\n";
        $message .= "• مبلغ: <code>" . number_format($report['purchase_amount']) . "</code> ریال\n\n";
        
        $message .= "<b>📊 خلاصه:</b>\n";
        $message .= "• گردش مالی: <code>" . number_format($report['total_turnover']) . "</code> ریال\n";
        $message .= "• سود خالص: <code>" . number_format($report['net_profit']) . "</code> ریال\n";
        $message .= "• کارمزدها: <code>" . number_format($report['total_commissions']) . "</code> ریال\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' =& '📤 خروجی PDF', 'callback_data' =& 'export_daily_pdf'],
                    ['text' =& '📤 خروجی اکسل', 'callback_data' =& 'export_daily_excel']
                ],
                [
                    ['text' =& '📊 مقایسه با دیروز', 'callback_data' =& 'compare_yesterday'],
                    ['text' =& '📈 نمودار روزانه', 'callback_data' =& 'daily_chart']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_financial']
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
     * Show users report
     */
    private function showUsersReport($adminId, $chatId) {
        $report = $this->getUsersReport();
        
        $message = "👥 <b>گزارش کاربران</b>\n\n";
        $message .= "📅 بازه: ۳۰ روز گذشته\n\n";
        
        $message .= "<b>📈 رشد کاربران:</b>\n";
        $message .= "• ثبت‌نام جدید: <code>" . number_format($report['new_registrations']) . "</code>\n";
        $message .= "• رشد نسبت به ماه قبل: <code>" . $report['growth_rate'] . "%</code>\n\n";
        
        $message .= "<b>👤 فعالیت کاربران:</b>\n";
        $message .= "• کاربران فعال ماهانه: <code>" . number_format($report['monthly_active']) . "</code>\n";
        $message .= "• کاربران فعال روزانه: <code>" . number_format($report['daily_active']) . "</code>\n";
        $message .= "• میانگین زمان حضور: <code>" . $report['avg_session_time'] . "</code> دقیقه\n\n";
        
        $message .= "<b>💰 فعالیت مالی:</b>\n";
        $message .= "• کاربران با خرید: <code>" . number_format($report['paying_users']) . "</code>\n";
        $message .= "• میانگین خرید: <code>" . number_format($report['avg_purchase']) . "</code> ریال\n";
        $message .= "• نرخ تبدیل: <code>" . $report['conversion_rate'] . "%</code>\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' =& '📤 خروجی اکسل', 'callback_data' =& 'export_users_excel'],
                    ['text' =& '📊 نمودار رشد', 'callback_data' =& 'users_growth_chart']
                ],
                [
                    ['text' =& '🔙 بازگشت', 'callback_data' =& 'admin_reports']
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
     * Helper methods for statistics
     */
    private function getSystemStats() {
        $stats = [];
        
        // Total users
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
        $stats['total_users'] = $stmt->fetchColumn();
        
        // Active users (last 30 days)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'active' AND last_seen >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $stats['active_users'] = $stmt->fetchColumn();
        
        // Total balance
        $stmt = $this->pdo->query("SELECT SUM(balance) FROM users");
        $stats['total_balance'] = $stmt->fetchColumn() ?: 0;
        
        // Today's transactions
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['today_transactions'] = $stmt->fetchColumn();
        
        // Active services
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM user_services WHERE status = 'active'");
        $stats['active_services'] = $stmt->fetchColumn();
        
        // Today's sales
        $stmt = $this->pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'purchase' AND status = 'completed' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['today_sales'] = $stmt->fetchColumn() ?: 0;
        
        return $stats;
    }
    
    private function getDetailedSystemStats() {
        $stats = $this->getSystemStats();
        
        // Additional detailed stats
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['new_today'] = $stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(last_seen) = CURDATE()");
        $stmt->execute();
        $stats['active_today'] = $stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'deposit' AND status = 'completed' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['deposits_today'] = $stmt->fetchColumn() ?: 0;
        
        $stmt = $this->pdo->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'withdrawal' AND status = 'completed' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['withdrawals_today'] = $stmt->fetchColumn() ?: 0;
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_services WHERE DATE(expires_at) = CURDATE()");
        $stmt->execute();
        $stats['expired_today'] = $stmt->fetchColumn();
        
        // Calculate rates
        $stats['user_growth_rate'] = $this->calculateGrowthRate('users');
        $stats['conversion_rate'] = $this->calculateConversionRate();
        $stats['avg_purchase'] = $this->calculateAveragePurchase();
        
        return $stats;
    }
    
    private function getUsersStats() {
        $stats = [];
        
        $stmt = $this->pdo->query("SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'banned' THEN 1 END) as banned,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_new
        FROM users");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getTransactionsStats() {
        $stmt = $this->pdo->query("SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount
        FROM transactions");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getFinancialStats() {
        $stats = [];
        
        $stmt = $this->pdo->query("SELECT 
            SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as total_withdrawals,
            SUM(CASE WHEN type = 'purchase' AND status = 'completed' THEN amount ELSE 0 END) as total_sales,
            SUM(CASE WHEN type = 'commission' AND status = 'completed' THEN amount ELSE 0 END) as total_commissions
        FROM transactions");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_deposits'] = $result['total_deposits'] ?: 0;
        $stats['total_withdrawals'] = $result['total_withdrawals'] ?: 0;
        $stats['total_sales'] = $result['total_sales'] ?: 0;
        $stats['total_commissions'] = $result['total_commissions'] ?: 0;
        $stats['net_profit'] = $stats['total_commissions'];
        
        return $stats;
    }
    
    private function getPendingTransactions() {
        $stmt = $this->pdo->prepare("SELECT t.*, u.first_name, u.last_name, u.username 
                                    FROM transactions t 
                                    JOIN users u ON t.user_id = u.user_id 
                                    WHERE t.status = 'pending' 
                                    ORDER BY t.created_at ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getDailyFinancialReport() {
        $report = [];
        
        // Deposits
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as amount FROM transactions WHERE type = 'deposit' AND status = 'completed' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $report['deposit_count'] = $result['count'] ?: 0;
        $report['deposit_amount'] = $result['amount'] ?: 0;
        
        // Withdrawals
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as amount FROM transactions WHERE type = 'withdrawal' AND status = 'completed' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $report['withdrawal_count'] = $result['count'] ?: 0;
        $report['withdrawal_amount'] = $result['amount'] ?: 0;
        
        // Purchases
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as amount FROM transactions WHERE type = 'purchase' AND status = 'completed' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $report['purchase_count'] = $result['count'] ?: 0;
        $report['purchase_amount'] = $result['amount'] ?: 0;
        
        // Summary
        $report['total_turnover'] = $report['deposit_amount'] + $report['purchase_amount'];
        $report['net_profit'] = $report['purchase_amount'] * 0.1; // Assuming 10% commission
        $report['total_commissions'] = $report['net_profit'];
        
        return $report;
    }
    
    private function getUsersReport() {
        $report = [];
        
        // New registrations in last 30 days
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $report['new_registrations'] = $stmt->fetchColumn();
        
        // Growth rate compared to previous month
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $previous_month = $stmt->fetchColumn();
        
        $report['growth_rate'] = $previous_month > 0 ? round((($report['new_registrations'] - $previous_month) / $previous_month) * 100, 2) : 0;
        
        // Monthly active users
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $report['monthly_active'] = $stmt->fetchColumn();
        
        // Daily active users
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM transactions WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $report['daily_active'] = $stmt->fetchColumn();
        
        // Average session time (placeholder)
        $report['avg_session_time'] = 15; // This would need proper tracking
        
        // Paying users
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM transactions WHERE type = 'purchase' AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $report['paying_users'] = $stmt->fetchColumn();
        
        // Average purchase
        $stmt = $this->pdo->prepare("SELECT AVG(amount) FROM transactions WHERE type = 'purchase' AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $report['avg_purchase'] = $stmt->fetchColumn() ?: 0;
        
        // Conversion rate
        $report['conversion_rate'] = $report['monthly_active'] > 0 ? round(($report['paying_users'] / $report['monthly_active']) * 100, 2) : 0;
        
        return $report;
    }
    
    private function calculateGrowthRate($type) {
        // This would calculate growth rate based on historical data
        return 12.5; // Placeholder
    }
    
    private function calculateConversionRate() {
        // This would calculate conversion rate based on user activity
        return 8.3; // Placeholder
    }
    
    private function calculateAveragePurchase() {
        // This would calculate average purchase amount
        return 150000; // Placeholder
    }
    
    private function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getTransactionTypeText($type) {
        $types = [
            'deposit' => 'واریز',
            'withdrawal' => 'برداشت',
            'purchase' => 'خرید',
            'refund' => 'بازگشت وجه',
            'transfer' => 'انتقال',
            'commission' => 'کارمزد'
        ];
        
        return $types[$type] ?? $type;
    }
    
    private function getPaymentMethodText($method) {
        $methods = [
            'card_to_card' => 'کارت به کارت',
            'bank_transfer' => 'انتقال بانکی',
            'online_payment' => 'پرداخت آنلاین',
            'digital_wallet' => 'کیف پول دیجیتال',
            'cryptocurrency' => 'ارز دیجیتال'
        ];
        
        return $methods[$method] ?? $method;
    }
    
    private function sendAccessDenied($chatId) {
        return $this->telegram->sendMessage([
            'chat_id' =& $chatId,
            'text' =& "❌ <b>دسترسی غیرمجاز</b>\n\nشما مجوز لازم برای دسترسی به این بخش را ندارید.",
            'parse_mode' =& 'HTML'
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
}

?>