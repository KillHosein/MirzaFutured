<?php
/**
 * Enhanced Service Purchase System - Shopping Cart and Payment Processing
 * Professional service purchase system for Telegram web application
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/FinancialSystem.php';

class ServicePurchaseSystem {
    
    private $pdo;
    private $telegram;
    private $financialSystem;
    private $notificationSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->telegram = new TelegramAPI();
        $this->financialSystem = new FinancialSystem($pdo);
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Handle service purchase menu
     */
    public function handleServiceMenu($userId, $chatId, $action = null, $data = null) {
        try {
            if (!$this->isUserRegistered($userId)) {
                return $this->sendRegistrationRequired($chatId);
            }
            
            switch ($action) {
                case 'browse':
                    return $this->showServiceCategories($userId, $chatId);
                    
                case 'category':
                    return $this->showServicesByCategory($userId, $chatId, $data);
                    
                case 'service':
                    return $this->showServiceDetails($userId, $chatId, $data);
                    
                case 'add_to_cart':
                    return $this->addToCart($userId, $chatId, $data);
                    
                case 'cart':
                    return $this->showCart($userId, $chatId);
                    
                case 'checkout':
                    return $this->processCheckout($userId, $chatId);
                    
                case 'payment':
                    return $this->processPayment($userId, $chatId, $data);
                    
                case 'my_services':
                    return $this->showUserServices($userId, $chatId);
                    
                default:
                    return $this->showServiceMenu($userId, $chatId);
            }
            
        } catch (Exception $e) {
            error_log("Service menu error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Show service main menu
     */
    private function showServiceMenu($userId, $chatId) {
        $message = "🛍️ <b>فروشگاه سرویس‌ها</b>\n\n";
        $message .= "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📂 مشاهده دسته‌بندی‌ها', 'callback_data' => 'service_browse'],
                    ['text' => '🛒 سبد خرید', 'callback_data' => 'service_cart']
                ],
                [
                    ['text' => '📋 سرویس‌های من', 'callback_data' => 'service_my_services'],
                    ['text' => '🔍 جستجو', 'callback_data' => 'service_search']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'main_menu']
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
     * Show service categories
     */
    private function showServiceCategories($userId, $chatId) {
        $categories = $this->getActiveCategories();
        
        if (empty($categories)) {
            return $this->sendErrorMessage($chatId, "در حال حاضر دسته‌بندی‌ای موجود نیست.");
        }
        
        $message = "📂 <b>دسته‌بندی سرویس‌ها</b>\n\n";
        $message .= "دسته‌بندی مورد نظر را انتخاب کنید:";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($categories as $category) {
            $serviceCount = $this->getCategoryServiceCount($category['id']);
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => "{$category['name']} ({$serviceCount})",
                    'callback_data' => "service_category:{$category['id']}"
                ]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 بازگشت', 'callback_data' => 'service_menu']
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show services by category
     */
    private function showServicesByCategory($userId, $chatId, $categoryId) {
        $services = $this->getActiveServicesByCategory($categoryId);
        
        if (empty($services)) {
            return $this->sendErrorMessage($chatId, "در این دسته‌بندی سرویسی موجود نیست.");
        }
        
        $category = $this->getCategoryById($categoryId);
        $message = "📂 <b>{$category['name']}</b>\n\n";
        $message .= "سرویس مورد نظر را انتخاب کنید:";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($services as $service) {
            $price = number_format($service['discounted_price'] ?: $service['base_price']);
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => "{$service['name']} - {$price} ریال",
                    'callback_data' => "service_detail:{$service['id']}"
                ]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 بازگشت', 'callback_data' => 'service_browse']
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Show service details
     */
    private function showServiceDetails($userId, $chatId, $serviceId) {
        $service = $this->getServiceById($serviceId);
        
        if (!$service) {
            return $this->sendErrorMessage($chatId, "سرویس مورد نظر یافت نشد.");
        }
        
        $price = $service['discounted_price'] ?: $service['base_price'];
        $formattedPrice = number_format($price);
        
        $message = "📋 <b>{$service['name']}</b>\n\n";
        $message .= "{$service['description']}\n\n";
        
        if ($service['discounted_price'] && $service['discounted_price'] < $service['base_price']) {
            $originalPrice = number_format($service['base_price']);
            $discount = round((($service['base_price'] - $service['discounted_price']) / $service['base_price']) * 100);
            $message .= "💰 قیمت اصلی: <s>{$originalPrice}</s> ریال\n";
            $message .= "🏷️ قیمت با تخفیف: <code>{$formattedPrice}</code> ریال\n";
            $message .= "🔥 تخفیف: {$discount}%\n\n";
        } else {
            $message .= "💰 قیمت: <code>{$formattedPrice}</code> ریال\n\n";
        }
        
        if ($service['duration_days']) {
            $message .= "⏰ مدت: {$service['duration_days']} روز\n";
        }
        
        if ($service['bandwidth_limit']) {
            $bandwidth = $this->formatBandwidth($service['bandwidth_limit']);
            $message .= "📊 حجم: {$bandwidth}\n";
        }
        
        if ($service['device_limit']) {
            $message .= "📱 تعداد دستگاه: {$service['device_limit']}\n";
        }
        
        if ($service['features']) {
            $message .= "\n✨ <b>ویژگی‌ها:</b>\n";
            $features = json_decode($service['features'], true) ?: [];
            foreach ($features as $feature) {
                $message .= "• {$feature}\n";
            }
        }
        
        $message .= "\n📅 وضعیت: " . $this->getServiceStatusText($service['status']);
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🛒 افزودن به سبد خرید', 'callback_data' => "add_to_cart:{$service['id']}"],
                    ['text' => '💳 خرید مستقیم', 'callback_data' => "buy_now:{$service['id']}"]
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => "service_category:{$service['category_id']}"]
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
     * Add service to cart
     */
    private function addToCart($userId, $chatId, $serviceId) {
        try {
            $service = $this->getServiceById($serviceId);
            
            if (!$service || $service['status'] !== 'active') {
                return $this->sendErrorMessage($chatId, "سرویس مورد نظر در دسترس نیست.");
            }
            
            // Check if already in cart
            $existingItem = $this->getCartItem($userId, $serviceId);
            
            if ($existingItem) {
                // Increase quantity
                $this->updateCartItemQuantity($userId, $serviceId, $existingItem['quantity'] + 1);
            } else {
                // Add new item
                $this->addCartItem($userId, $serviceId);
            }
            
            $message = "✅ سرویس به سبد خرید افزوده شد.\n\n";
            $message .= "برای مشاهده سبد خرید و تکمیل خرید، از دکمه زیر استفاده کنید:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🛒 مشاهده سبد خرید', 'callback_data' => 'service_cart'],
                        ['text'� '💳 پرداخت', 'callback_data' => 'service_checkout']
                    ],
                    [
                        ['text' => '🔙 ادامه خرید', 'callback_data' => "service_category:{$service['category_id']}"]
                    ]
                ]
            ];
            
            return $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode($keyboard),
                'parse_mode' => 'HTML'
            ]);
            
        } catch (Exception $e) {
            error_log("Add to cart error: " . $e->getMessage());
            return $this->sendErrorMessage($chatId, "خطا در افزودن به سبد خرید.");
        }
    }
    
    /**
     * Show shopping cart
     */
    private function showCart($userId, $chatId) {
        $cartItems = $this->getUserCartItems($userId);
        
        if (empty($cartItems)) {
            $message = "🛒 <b>سبد خرید شما خالی است</b>\n\n";
            $message .= "برای افزودن سرویس به سبد خرید، از فروشگاه دیدن کنید.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📂 مشاهده سرویس‌ها', 'callback_data' => 'service_browse']
                    ],
                    [
                        ['text' => '🔙 بازگشت', 'callback_data' => 'service_menu']
                    ]
                ]
            ];
        } else {
            $message = "🛒 <b>سبد خرید شما</b>\n\n";
            $total = 0;
            
            foreach ($cartItems as $item) {
                $service = $this->getServiceById($item['service_id']);
                $price = $service['discounted_price'] ?: $service['base_price'];
                $itemTotal = $price * $item['quantity'];
                $total += $itemTotal;
                
                $formattedPrice = number_format($price);
                $formattedTotal = number_format($itemTotal);
                
                $message .= "📋 {$service['name']}\n";
                $message .= "💰 قیمت: {$formattedPrice} ریال\n";
                $message .= "🔢 تعداد: {$item['quantity']}\n";
                $message .= "💵 جمع: {$formattedTotal} ریال\n";
                $message .= "━━━━━━━━━━━━━━━\n\n";
            }
            
            $formattedTotal = number_format($total);
            $message .= "💰 <b>جمع کل: {$formattedTotal} ریال</b>";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '💳 پرداخت نهایی', 'callback_data' => 'service_checkout'],
                        ['text' => '🔄 بروزرسانی', 'callback_data' => 'service_cart']
                    ],
                    [
                        ['text' => '✏️ ویرایش', 'callback_data' => 'edit_cart'],
                        ['text' => '🗑️ حذف همه', 'callback_data' => 'clear_cart']
                    ],
                    [
                        ['text' => '🔙 بازگشت', 'callback_data' => 'service_menu']
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
     * Process checkout
     */
    private function processCheckout($userId, $chatId) {
        try {
            $cartItems = $this->getUserCartItems($userId);
            
            if (empty($cartItems)) {
                return $this->sendErrorMessage($chatId, "سبد خرید شما خالی است.");
            }
            
            // Calculate total
            $total = $this->calculateCartTotal($cartItems);
            $userBalance = $this->getUserBalance($userId);
            
            // Check if user has sufficient balance
            if ($userBalance < $total) {
                $neededAmount = $total - $userBalance;
                $formattedNeeded = number_format($neededAmount);
                $formattedBalance = number_format($userBalance);
                
                $message = "💰 <b>موجودی ناکافی</b>\n\n";
                $message .= "موجودی فعلی شما: <code>{$formattedBalance}</code> ریال\n";
                $message .= "مبلغ مورد نیاز: <code>" . number_format($total) . "</code> ریال\n";
                $message .= "کسری موجودی: <code>{$formattedNeeded}</code> ریال\n\n";
                $message .= "لطفاً ابتدا حساب خود را شارژ کنید.";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '💳 شارژ حساب', 'callback_data' => 'finance_deposit']
                        ],
                        [
                            ['text' => '🔙 بازگشت', 'callback_data' => 'service_cart']
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
            
            // Create order
            $order = $this->createOrder($userId, $cartItems, $total);
            
            if (!$order) {
                return $this->sendErrorMessage($chatId, "خطا در ایجاد سفارش. لطفاً دوباره تلاش کنید.");
            }
            
            // Show payment options
            return $this->showPaymentOptions($userId, $chatId, $order['id']);
            
        } catch (Exception $e) {
            error_log("Checkout error: " . $e->getMessage());
            return $this->sendErrorMessage($chatId, "خطا در پردازش سفارش.");
        }
    }
    
    /**
     * Show payment options
     */
    private function showPaymentOptions($userId, $chatId, $orderId) {
        $order = $this->getOrderById($orderId);
        $total = number_format($order['total_amount']);
        
        $message = "💳 <b>پرداخت سفارش</b>\n\n";
        $message .= "شماره سفارش: <code>{$order['order_number']}</code>\n";
        $message .= "مبلغ قابل پرداخت: <code>{$total}</code> ریال\n\n";
        $message .= "روش پرداخت را انتخاب کنید:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '💰 پرداخت از کیف پول', 'callback_data' => "pay_wallet:{$orderId}"]
                ],
                [
                    ['text' => '💳 کارت به کارت', 'callback_data' => "pay_card:{$orderId}"],
                    ['text' => '🌐 درگاه آنلاین', 'callback_data' => "pay_online:{$orderId}"]
                ],
                [
                    ['text' => '💎 ارز دیجیتال', 'callback_data' => "pay_crypto:{$orderId}"]
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'service_cart']
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
     * Process payment
     */
    private function processPayment($userId, $chatId, $paymentData) {
        try {
            list($paymentMethod, $orderId) = explode(':', $paymentData);
            
            $order = $this->getOrderById($orderId);
            
            if (!$order || $order['user_id'] != $userId) {
                return $this->sendErrorMessage($chatId, "سفارش نامعتبر است.");
            }
            
            if ($order['payment_status'] !== 'pending') {
                return $this->sendErrorMessage($chatId, "این سفارش قبلاً پرداخت شده است.");
            }
            
            switch ($paymentMethod) {
                case 'wallet':
                    return $this->processWalletPayment($userId, $chatId, $order);
                    
                case 'card':
                    return $this->processCardPayment($userId, $chatId, $order);
                    
                case 'online':
                    return $this->processOnlinePayment($userId, $chatId, $order);
                    
                case 'crypto':
                    return $this->processCryptoPayment($userId, $chatId, $order);
                    
                default:
                    return $this->sendErrorMessage($chatId, "روش پرداخت نامعتبر است.");
            }
            
        } catch (Exception $e) {
            error_log("Payment processing error: " . $e->getMessage());
            return $this->sendErrorMessage($chatId, "خطا در پردازش پرداخت.");
        }
    }
    
    /**
     * Process wallet payment
     */
    private function processWalletPayment($userId, $chatId, $order) {
        $userBalance = $this->getUserBalance($userId);
        
        if ($userBalance < $order['total_amount']) {
            return $this->sendErrorMessage($chatId, "موجودی کیف پول کافی نیست.");
        }
        
        try {
            // Deduct from user balance
            $this->updateUserBalance($userId, -$order['total_amount']);
            
            // Update order status
            $this->updateOrderPaymentStatus($order['id'], 'paid');
            
            // Create transaction record
            $this->createTransaction([
                'user_id' => $userId,
                'transaction_id' => $this->generateTransactionId(),
                'type' => 'purchase',
                'amount' => -$order['total_amount'],
                'payment_method' => 'wallet',
                'status' => 'completed',
                'balance_before' => $userBalance,
                'balance_after' => $userBalance - $order['total_amount'],
                'order_id' => $order['id'],
                'created_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Activate services
            $this->activateOrderServices($order['id']);
            
            // Clear cart
            $this->clearUserCart($userId);
            
            // Send success message
            $this->sendPurchaseSuccessMessage($userId, $chatId, $order);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Wallet payment error: " . $e->getMessage());
            return $this->sendErrorMessage($chatId, "خطا در پرداخت از کیف پول.");
        }
    }
    
    /**
     * Process card payment
     */
    private function processCardPayment($userId, $chatId, $order) {
        // This would integrate with the FinancialSystem card-to-card functionality
        // For now, we'll create a pending transaction and ask for manual confirmation
        
        try {
            // Create pending transaction
            $transaction = $this->createTransaction([
                'user_id' => $userId,
                'transaction_id' => $this->generateTransactionId(),
                'type' => 'purchase',
                'amount' => $order['total_amount'],
                'payment_method' => 'card_to_card',
                'status' => 'pending',
                'balance_before' => $this->getUserBalance($userId),
                'balance_after' => $this->getUserBalance($userId),
                'order_id' => $order['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update order payment method
            $this->updateOrderPaymentMethod($order['id'], 'card_to_card');
            
            // Send instructions
            $this->sendCardPaymentInstructions($userId, $chatId, $order, $transaction);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Card payment error: " . $e->getMessage());
            return $this->sendErrorMessage($chatId, "خطا در پردازش پرداخت کارت به کارت.");
        }
    }
    
    /**
     * Show user services
     */
    private function showUserServices($userId, $chatId) {
        $services = $this->getUserActiveServices($userId);
        
        if (empty($services)) {
            $message = "📋 <b>شما هیچ سرویس فعالی ندارید</b>\n\n";
            $message .= "برای خرید سرویس از فروشگاه دیدن کنید.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🛍️ خرید سرویس', 'callback_data' => 'service_browse']
                    ],
                    [
                        ['text' => '🔙 بازگشت', 'callback_data' => 'service_menu']
                    ]
                ]
            ];
        } else {
            $message = "📋 <b>سرویس‌های فعال شما</b>\n\n";
            
            foreach ($services as $service) {
                $expiryDate = jdate('Y/m/d', strtotime($service['expires_at']));
                $status = $this->getServiceStatusText($service['status']);
                
                $message .= "📌 {$service['service_name']}\n";
                $message .= "📅 انقضا: {$expiryDate}\n";
                $message .= "📊 وضعیت: {$status}\n";
                
                if ($service['bandwidth_limit']) {
                    $bandwidth = $this->formatBandwidth($service['bandwidth_limit']);
                    $message .= "📊 حجم: {$bandwidth}\n";
                }
                
                if ($service['device_limit']) {
                    $message .= "📱 دستگاه‌ها: {$service['device_count']}/{$service['device_limit']}\n";
                }
                
                $message .= "━━━━━━━━━━━━━━━\n\n";
            }
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔄 بروزرسانی', 'callback_data' => 'service_my_services'],
                        ['text' => '📊 گزارش مصرف', 'callback_data' => 'service_usage_report']
                    ],
                    [
                        ['text' => '🔙 بازگشت', 'callback_data' => 'service_menu']
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
     * Activate order services
     */
    private function activateOrderServices($orderId) {
        $orderItems = $this->getOrderItems($orderId);
        
        foreach ($orderItems as $item) {
            $service = $this->getServiceById($item['service_id']);
            
            // Calculate expiry date
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$service['duration_days']} days"));
            
            // Create user service
            $this->createUserService([
                'user_id' => $item['user_id'],
                'service_id' => $item['service_id'],
                'order_item_id' => $item['id'],
                'service_name' => $service['name'],
                'service_type' => $service['service_type'],
                'configuration' => $service['configuration'],
                'bandwidth_limit' => $service['bandwidth_limit'],
                'device_limit' => $service['device_limit'],
                'status' => 'active',
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update order item status
            $this->updateOrderItemStatus($item['id'], 'active', date('Y-m-d H:i:s'));
        }
        
        return true;
    }
    
    /**
     * Send purchase success message
     */
    private function sendPurchaseSuccessMessage($userId, $chatId, $order) {
        $total = number_format($order['total_amount']);
        
        $message = "🎉 <b>خرید موفق!</b>\n\n";
        $message .= "سفارش شماره <code>{$order['order_number']}</code> با موفقیت پرداخت شد.\n";
        $message .= "مبلغ پرداختی: <code>{$total}</code> ریال\n\n";
        $message .= "سرویس‌های خریداری شده به‌صورت خودکار فعال شدند.\n";
        $message .= "می‌توانید از بخش 'سرویس‌های من' وضعیت آن‌ها را مشاهده کنید.";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 سرویس‌های من', 'callback_data' => 'service_my_services'],
                    ['text' => '📊 تراکنش‌ها', 'callback_data' => 'transactions']
                ],
                [
                    ['text' => '🔙 بازگشت', 'callback_data' => 'service_menu']
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
     * Send card payment instructions
     */
    private function sendCardPaymentInstructions($userId, $chatId, $order, $transaction) {
        $total = number_format($order['total_amount']);
        
        $message = "💳 <b>پرداخت کارت به کارت</b>\n\n";
        $message .= "شماره سفارش: <code>{$order['order_number']}</code>\n";
        $message .= "مبلغ: <code>{$total}</code> ریال\n";
        $message .= "شماره تراکنش: <code>{$transaction['transaction_id']}</code>\n\n";
        $message .= "لطفاً مبلغ را به یکی از کارت‌های زیر واریز کنید:\n\n";
        
        $bankCards = $this->getActiveBankCards();
        foreach ($bankCards as $card) {
            $message .= "🏦 {$card['bank_name']}\n";
            $message .= "💳 {$card['card_number']}\n";
            $message .= "👤 {$card['account_holder']}\n\n";
        }
        
        $message .= "⚠️ <b>نکات مهم:</b>\n";
        $message .= "• پس از واریز، تصویر رسید را ارسال کنید\n";
        $message .= "• در توضیحات انتقال، شماره سفارش را ذکر کنید\n";
        $message .= "• پردازش تا ۳۰ دقیقه زمان می‌برد";
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
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
    
    private function getUserBalance($userId) {
        $stmt = $this->pdo->prepare("SELECT balance FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function updateUserBalance($userId, $amount) {
        $stmt = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
        return $stmt->execute([$amount, $userId]);
    }
    
    private function getActiveCategories() {
        $stmt = $this->pdo->prepare("SELECT * FROM service_categories WHERE status = 'active' ORDER BY sort_order, name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getCategoryById($categoryId) {
        $stmt = $this->pdo->prepare("SELECT * FROM service_categories WHERE id = ? AND status = 'active'");
        $stmt->execute([$categoryId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getCategoryServiceCount($categoryId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM services WHERE category_id = ? AND status = 'active'");
        $stmt->execute([$categoryId]);
        return $stmt->fetchColumn();
    }
    
    private function getActiveServicesByCategory($categoryId) {
        $stmt = $this->pdo->prepare("SELECT * FROM services WHERE category_id = ? AND status = 'active' ORDER BY name");
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getServiceById($serviceId) {
        $stmt = $this->pdo->prepare("SELECT * FROM services WHERE id = ? AND status IN ('active', 'out_of_stock')");
        $stmt->execute([$serviceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getCartItem($userId, $serviceId) {
        $stmt = $this->pdo->prepare("SELECT * FROM cart_items WHERE user_id = ? AND service_id = ?");
        $stmt->execute([$userId, $serviceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function addCartItem($userId, $serviceId) {
        $stmt = $this->pdo->prepare("INSERT INTO cart_items (user_id, service_id, quantity, created_at) VALUES (?, ?, 1, NOW())");
        return $stmt->execute([$userId, $serviceId]);
    }
    
    private function updateCartItemQuantity($userId, $serviceId, $quantity) {
        $stmt = $this->pdo->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND service_id = ?");
        return $stmt->execute([$quantity, $userId, $serviceId]);
    }
    
    private function getUserCartItems($userId) {
        $stmt = $this->pdo->prepare("SELECT ci.*, s.* FROM cart_items ci JOIN services s ON ci.service_id = s.id WHERE ci.user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function clearUserCart($userId) {
        $stmt = $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
    
    private function calculateCartTotal($cartItems) {
        $total = 0;
        foreach ($cartItems as $item) {
            $price = $item['discounted_price'] ?: $item['base_price'];
            $total += $price * $item['quantity'];
        }
        return $total;
    }
    
    private function createOrder($userId, $cartItems, $total) {
        $orderNumber = $this->generateOrderNumber();
        
        // Create order
        $stmt = $this->pdo->prepare("INSERT INTO orders (user_id, order_number, subtotal, total_amount, payment_status, status, created_at) VALUES (?, ?, ?, ?, 'pending', 'pending', NOW())");
        $stmt->execute([$userId, $orderNumber, $total, $total]);
        $orderId = $this->pdo->lastInsertId();
        
        // Create order items
        foreach ($cartItems as $item) {
            $service = $this->getServiceById($item['service_id']);
            $price = $service['discounted_price'] ?: $service['base_price'];
            $itemTotal = $price * $item['quantity'];
            
            $stmt = $this->pdo->prepare("INSERT INTO order_items (order_id, service_id, quantity, unit_price, total_price, service_name, service_description, service_configuration, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $orderId,
                $item['service_id'],
                $item['quantity'],
                $price,
                $itemTotal,
                $service['name'],
                $service['description'],
                $service['configuration']
            ]);
        }
        
        return ['id' => $orderId, 'order_number' => $orderNumber];
    }
    
    private function getOrderById($orderId) {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getOrderItems($orderId) {
        $stmt = $this->pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function updateOrderPaymentStatus($orderId, $status) {
        $stmt = $this->pdo->prepare("UPDATE orders SET payment_status = ?, paid_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $orderId]);
    }
    
    private function updateOrderPaymentMethod($orderId, $method) {
        $stmt = $this->pdo->prepare("UPDATE orders SET payment_method = ? WHERE id = ?");
        return $stmt->execute([$method, $orderId]);
    }
    
    private function updateOrderItemStatus($itemId, $status, $activatedAt = null) {
        $sql = "UPDATE order_items SET status = ?";
        $params = [$status];
        
        if ($activatedAt) {
            $sql .= ", activated_at = ?";
            $params[] = $activatedAt;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $itemId;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    private function createUserService($data) {
        $stmt = $this->pdo->prepare("INSERT INTO user_services (user_id, service_id, order_item_id, service_name, service_type, configuration, bandwidth_limit, device_limit, status, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['user_id'],
            $data['service_id'],
            $data['order_item_id'],
            $data['service_name'],
            $data['service_type'],
            $data['configuration'],
            $data['bandwidth_limit'],
            $data['device_limit'],
            $data['status'],
            $data['expires_at'],
            $data['created_at']
        ]);
    }
    
    private function getUserActiveServices($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM user_services WHERE user_id = ? AND status = 'active' ORDER BY expires_at ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function createTransaction($data) {
        $sql = "INSERT INTO transactions (user_id, transaction_id, type, amount, payment_method, status, balance_before, balance_after, order_id, created_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['user_id'],
            $data['transaction_id'],
            $data['type'],
            $data['amount'],
            $data['payment_method'],
            $data['status'],
            $data['balance_before'],
            $data['balance_after'],
            $data['order_id'] ?? null,
            $data['created_at'],
            $data['completed_at'] ?? null
        ]);
        
        return ['id' => $this->pdo->lastInsertId(), 'transaction_id' => $data['transaction_id']];
    }
    
    private function generateOrderNumber() {
        return 'ORD' . date('YmdHis') . rand(1000, 9999);
    }
    
    private function generateTransactionId() {
        return 'TRX' . date('YmdHis') . rand(1000, 9999);
    }
    
    private function getActiveBankCards() {
        return [
            [
                'bank_name' => 'بانک ملی ایران',
                'card_number' => '6037991234567890',
                'account_holder' => 'شرکت تلگرام وب'
            ]
        ];
    }
    
    private function getServiceStatusText($status) {
        $statuses = [
            'active' => '✅ فعال',
            'inactive' => '❌ غیرفعال',
            'out_of_stock' => '🚫 ناموجود',
            'coming_soon' => '⏳ به‌زودی',
            'suspended' => '⏸️ تعلیق',
            'expired' => '⏰ منقضی',
            'cancelled' => '❌ لغو شده'
        ];
        
        return $statuses[$status] ?? $status;
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
    
    private function sendErrorMessage($chatId, $message) {
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "❌ خطا: " . $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    private function sendRegistrationRequired($chatId) {
        return $this->sendErrorMessage($chatId, "ابتدا باید ثبت‌نام کنید. لطفاً از دستور /start استفاده کنید.");
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
 * Notification System Class
 */
class NotificationSystem {
    
    private $pdo;
    private $telegram;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->telegram = new TelegramAPI();
    }
    
    public function sendPurchaseNotification($userId, $order) {
        $message = "🎉 سفارش شماره {$order['order_number']} با موفقیت پرداخت و فعال شد.";
        return $this->createNotification($userId, 'خرید موفق', $message, 'success');
    }
    
    private function createNotification($userId, $title, $message, $type = 'info') {
        $stmt = $this->pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $title, $message, $type, date('Y-m-d H:i:s')]);
    }
}

?>