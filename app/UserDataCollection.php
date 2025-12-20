<?php
/**
 * Enhanced Telegram Web Application - User Data Collection System
 * Professional user registration and data collection through Telegram bot
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

class UserDataCollection {
    
    private $pdo;
    private $telegram;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->telegram = new TelegramAPI();
    }
    
    /**
     * Start user registration process
     */
    public function startRegistration($userId, $chatId) {
        try {
            // Check if user exists
            $user = $this->getUserByTelegramId($userId);
            
            if ($user) {
                return $this->sendUserProfile($userId, $chatId);
            }
            
            // Create new user registration session
            $this->createRegistrationSession($userId, $chatId);
            
            // Send welcome message and start registration
            $welcomeMessage = $this->getWelcomeMessage();
            $keyboard = $this->getRegistrationStartKeyboard();
            
            return $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $welcomeMessage,
                'reply_markup' => $keyboard,
                'parse_mode' => 'HTML'
            ]);
            
        } catch (Exception $e) {
            error_log("Registration start error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle user data collection step by step
     */
    public function handleDataCollection($userId, $chatId, $message, $step = null) {
        try {
            $session = $this->getRegistrationSession($userId);
            
            if (!$session) {
                return $this->startRegistration($userId, $chatId);
            }
            
            $currentStep = $session['current_step'] ?? 'start';
            
            switch ($currentStep) {
                case 'start':
                    return $this->collectFullName($userId, $chatId);
                    
                case 'full_name':
                    return $this->processFullName($userId, $chatId, $message);
                    
                case 'phone':
                    return $this->processPhone($userId, $chatId, $message);
                    
                case 'email':
                    return $this->processEmail($userId, $chatId, $message);
                    
                case 'national_id':
                    return $this->processNationalId($userId, $chatId, $message);
                    
                case 'birth_date':
                    return $this->processBirthDate($userId, $chatId, $message);
                    
                case 'address':
                    return $this->processAddress($userId, $chatId, $message);
                    
                case 'complete':
                    return $this->completeRegistration($userId, $chatId);
                    
                default:
                    return $this->handleUnknownStep($userId, $chatId);
            }
            
        } catch (Exception $e) {
            error_log("Data collection error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Collect user's full name
     */
    private function collectFullName($userId, $chatId) {
        $message = "👤 <b>ثبت نام کاربر جدید</b>\n\n";
        $message .= "لطفاً نام و نام خانوادگی خود را وارد کنید:\n";
        $message .= "<i>مثال: علی احمدی</i>";
        
        $this->updateRegistrationStep($userId, 'full_name');
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process full name
     */
    private function processFullName($userId, $chatId, $message) {
        if (empty(trim($message))) {
            return $this->sendErrorMessage($chatId, "لطفاً نام و نام خانوادگی معتبر وارد کنید.");
        }
        
        // Validate name format
        if (!$this->validatePersianName($message)) {
            return $this->sendErrorMessage($chatId, "لطفاً نام و نام خانوادگی را به صورت صحیح وارد کنید.");
        }
        
        // Store full name
        $this->updateRegistrationData($userId, 'full_name', $message);
        
        // Ask for phone number
        $this->requestPhoneNumber($userId, $chatId);
        $this->updateRegistrationStep($userId, 'phone');
        
        return true;
    }
    
    /**
     * Request phone number with contact sharing button
     */
    private function requestPhoneNumber($userId, $chatId) {
        $message = "📱 <b>شماره تلفن</b>\n\n";
        $message .= "لطفاً شماره تلفن همراه خود را وارد کنید یا از دکمه زیر استفاده کنید:\n";
        
        $keyboard = [
            'keyboard' => [
                [
                    [
                        'text' => '📱 اشتراک‌گذاری شماره تلفن',
                        'request_contact' => true
                    ]
                ],
                [
                    ['text' => '❌ انصراف']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process phone number
     */
    private function processPhone($userId, $chatId, $message, $contact = null) {
        $phone = $contact ? $contact['phone_number'] : $message;
        
        if (!$this->validatePhoneNumber($phone)) {
            return $this->sendErrorMessage($chatId, "لطفاً شماره تلفن معتبر وارد کنید.");
        }
        
        // Check if phone is already registered
        if ($this->isPhoneRegistered($phone, $userId)) {
            return $this->sendErrorMessage($chatId, "این شماره تلفن قبلاً ثبت شده است.");
        }
        
        $this->updateRegistrationData($userId, 'phone', $phone);
        $this->updateRegistrationData($userId, 'phone_verified', $contact ? true : false);
        
        // Ask for email
        $this->requestEmail($userId, $chatId);
        $this->updateRegistrationStep($userId, 'email');
        
        return true;
    }
    
    /**
     * Request email address
     */
    private function requestEmail($userId, $chatId) {
        $message = "📧 <b>آدرس ایمیل</b>\n\n";
        $message .= "لطفاً آدرس ایمیل خود را وارد کنید:\n";
        $message .= "<i>در صورت تمایل می‌توانید این مرحله را رد کنید</i>";
        
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '⏭️ رد کردن']
                ],
                [
                    ['text' => '❌ انصراف']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process email
     */
    private function processEmail($userId, $chatId, $message) {
        if ($message === '⏭️ رد کردن') {
            $this->updateRegistrationData($userId, 'email', null);
            $this->requestNationalId($userId, $chatId);
            $this->updateRegistrationStep($userId, 'national_id');
            return true;
        }
        
        if (!$this->validateEmail($message)) {
            return $this->sendErrorMessage($chatId, "لطفاً آدرس ایمیل معتبر وارد کنید.");
        }
        
        if ($this->isEmailRegistered($message, $userId)) {
            return $this->sendErrorMessage($chatId, "این آدرس ایمیل قبلاً ثبت شده است.");
        }
        
        $this->updateRegistrationData($userId, 'email', $message);
        
        // Ask for national ID
        $this->requestNationalId($userId, $chatId);
        $this->updateRegistrationStep($userId, 'national_id');
        
        return true;
    }
    
    /**
     * Request national ID
     */
    private function requestNationalId($userId, $chatId) {
        $message = "🆔 <b>کد ملی</b>\n\n";
        $message .= "لطفاً کد ملی ۱۰ رقمی خود را وارد کنید:\n";
        $message .= "<i>در صورت تمایل می‌توانید این مرحله را رد کنید</i>";
        
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '⏭️ رد کردن']
                ],
                [
                    ['text' => '❌ انصراف']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process national ID
     */
    private function processNationalId($userId, $chatId, $message) {
        if ($message === '⏭️ رد کردن') {
            $this->updateRegistrationData($userId, 'national_id', null);
            $this->requestBirthDate($userId, $chatId);
            $this->updateRegistrationStep($userId, 'birth_date');
            return true;
        }
        
        if (!$this->validateNationalId($message)) {
            return $this->sendErrorMessage($chatId, "لطفاً کد ملی معتبر وارد کنید.");
        }
        
        if ($this->isNationalIdRegistered($message, $userId)) {
            return $this->sendErrorMessage($chatId, "این کد ملی قبلاً ثبت شده است.");
        }
        
        $this->updateRegistrationData($userId, 'national_id', $message);
        
        // Ask for birth date
        $this->requestBirthDate($userId, $chatId);
        $this->updateRegistrationStep($userId, 'birth_date');
        
        return true;
    }
    
    /**
     * Request birth date
     */
    private function requestBirthDate($userId, $chatId) {
        $message = "🎂 <b>تاریخ تولد</b>\n\n";
        $message .= "لطفاً تاریخ تولد خود را به صورت شمسی وارد کنید:\n";
        $message .= "<i>مثال: 1370/05/15</i>\n";
        $message .= "<i>در صورت تمایل می‌توانید این مرحله را رد کنید</i>";
        
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '⏭️ رد کردن']
                ],
                [
                    ['text' => '❌ انصراف']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process birth date
     */
    private function processBirthDate($userId, $chatId, $message) {
        if ($message === '⏭️ رد کردن') {
            $this->updateRegistrationData($userId, 'birth_date', null);
            $this->requestAddress($userId, $chatId);
            $this->updateRegistrationStep($userId, 'address');
            return true;
        }
        
        if (!$this->validatePersianDate($message)) {
            return $this->sendErrorMessage($chatId, "لطفاً تاریخ تولد معتبر وارد کنید (مثال: 1370/05/15).");
        }
        
        $this->updateRegistrationData($userId, 'birth_date', $message);
        
        // Ask for address
        $this->requestAddress($userId, $chatId);
        $this->updateRegistrationStep($userId, 'address');
        
        return true;
    }
    
    /**
     * Request address
     */
    private function requestAddress($userId, $chatId) {
        $message = "🏠 <b>آدرس</b>\n\n";
        $message .= "لطفاً آدرس کامل خود را وارد کنید:\n";
        $message .= "<i>استان، شهر، منطقه، خیابان، کوچه، پلاک</i>\n";
        $message .= "<i>در صورت تمایل می‌توانید این مرحله را رد کنید</i>";
        
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '⏭️ رد کردن']
                ],
                [
                    ['text' => '❌ انصراف']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Process address
     */
    private function processAddress($userId, $chatId, $message) {
        if ($message === '⏭️ رد کردن') {
            $this->updateRegistrationData($userId, 'address', null);
        } else {
            if (strlen(trim($message)) < 10) {
                return $this->sendErrorMessage($chatId, "لطفاً آدرس کامل‌تری وارد کنید.");
            }
            $this->updateRegistrationData($userId, 'address', $message);
        }
        
        // Show summary and complete registration
        $this->showRegistrationSummary($userId, $chatId);
        $this->updateRegistrationStep($userId, 'complete');
        
        return true;
    }
    
    /**
     * Show registration summary
     */
    private function showRegistrationSummary($userId, $chatId) {
        $data = $this->getRegistrationData($userId);
        
        $message = "✅ <b>مرور اطلاعات ثبت‌نام</b>\n\n";
        $message .= "👤 <b>نام کامل:</b> " . ($data['full_name'] ?? 'ثبت نشده') . "\n";
        $message .= "📱 <b>شماره تلفن:</b> " . ($data['phone'] ?? 'ثبت نشده') . "\n";
        $message .= "📧 <b>ایمیل:</b> " . ($data['email'] ?? 'ثبت نشده') . "\n";
        $message .= "🆔 <b>کد ملی:</b> " . ($data['national_id'] ?? 'ثبت نشده') . "\n";
        $message .= "🎂 <b>تاریخ تولد:</b> " . ($data['birth_date'] ?? 'ثبت نشده') . "\n";
        $message .= "🏠 <b>آدرس:</b> " . ($data['address'] ?? 'ثبت نشده') . "\n\n";
        $message .= "آیا اطلاعات بالا صحیح است؟";
        
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '✅ تأیید و ثبت‌نام نهایی']
                ],
                [
                    ['text' => '✏️ ویرایش اطلاعات']
                ],
                [
                    ['text' => '❌ انصراف']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Complete registration
     */
    private function completeRegistration($userId, $chatId) {
        try {
            $data = $this->getRegistrationData($userId);
            
            // Create user in database
            $userData = [
                'user_id' => $userId,
                'username' => $data['username'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone_number' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'status' => 'active',
                'verification_level' => 1,
                'phone_verified' => $data['phone_verified'] ?? false,
                'email_verified' => false,
                'identity_verified' => !empty($data['national_id']),
                'balance' => 0.00,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->createUser($userData);
            
            // Store address if provided
            if (!empty($data['address'])) {
                $this->storeUserAddress($userId, $data['address']);
            }
            
            // Send welcome message
            $this->sendRegistrationSuccessMessage($userId, $chatId);
            
            // Clean up registration session
            $this->cleanupRegistrationSession($userId);
            
            // Send notification to admin
            $this->notifyAdminNewUser($userData);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Registration completion error: " . $e->getMessage());
            return $this->sendErrorMessage($chatId, "متأسفانه در ثبت‌نام مشکلی پیش آمده. لطفاً دوباره تلاش کنید.");
        }
    }
    
    /**
     * Send registration success message
     */
    private function sendRegistrationSuccessMessage($userId, $chatId) {
        $message = "🎉 <b>تبریک! ثبت‌نام شما با موفقیت انجام شد.</b>\n\n";
        $message .= "به خانواده ما خوش آمدید! 🌟\n\n";
        $message .= "شما اکنون می‌توانید از تمام امکانات ربات استفاده کنید:\n";
        $message .= "💰 مدیریت مالی و شارژ حساب\n";
        $message .= "🛍️ خرید سرویس‌ها\n";
        $message .= "📊 مشاهده تراکنش‌ها\n";
        $message .= "⚙️ مدیریت حساب کاربری\n\n";
        $message .= "برای شروع، از منوی اصلی استفاده کنید.";
        
        $keyboard = $this->getMainMenuKeyboard();
        
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Get user by Telegram ID
     */
    private function getUserByTelegramId($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create user in database
     */
    private function createUser($data) {
        $sql = "INSERT INTO users (user_id, username, first_name, last_name, phone_number, email, national_id, birth_date, status, verification_level, phone_verified, email_verified, identity_verified, balance, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['user_id'],
            $data['username'],
            $data['first_name'],
            $data['last_name'],
            $data['phone_number'],
            $data['email'],
            $data['national_id'],
            $data['birth_date'],
            $data['status'],
            $data['verification_level'],
            $data['phone_verified'],
            $data['email_verified'],
            $data['identity_verified'],
            $data['balance'],
            $data['created_at']
        ]);
    }
    
    /**
     * Validation methods
     */
    private function validatePersianName($name) {
        return preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', trim($name));
    }
    
    private function validatePhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && preg_match('/^09[0-9]{9}$/', $phone);
    }
    
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function validateNationalId($nationalId) {
        $nationalId = preg_replace('/[^0-9]/', '', $nationalId);
        return strlen($nationalId) === 10 && $this->isValidIranianNationalCode($nationalId);
    }
    
    private function validatePersianDate($date) {
        return preg_match('/^[1-4]\d{3}\/(0?[1-9]|1[0-2])\/(0?[1-9]|[12]\d|3[01])$/', $date);
    }
    
    /**
     * Validate Iranian national code
     */
    private function isValidIranianNationalCode($code) {
        if (!preg_match('/^\d{10}$/', $code))
            return false;
        
        for ($i = 0; $i < 10; $i++)
            if (preg_match('/^' . $i . '{10}$/', $code))
                return false;
        
        for ($i = 0, $sum = 0; $i < 9; $i++)
            $sum += ((10 - $i) * intval(substr($code, $i, 1)));
        
        $ret = $sum % 11;
        $parity = intval(substr($code, 9, 1));
        
        return ($ret < 2 && $ret == $parity) || ($ret >= 2 && $ret == 11 - $parity);
    }
    
    /**
     * Helper methods
     */
    private function isPhoneRegistered($phone, $excludeUserId = null) {
        $sql = "SELECT COUNT(*) FROM users WHERE phone_number = ?";
        $params = [$phone];
        
        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    private function isEmailRegistered($email, $excludeUserId = null) {
        if (empty($email)) return false;
        
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    private function isNationalIdRegistered($nationalId, $excludeUserId = null) {
        if (empty($nationalId)) return false;
        
        $sql = "SELECT COUNT(*) FROM users WHERE national_id = ?";
        $params = [$nationalId];
        
        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Registration session management
     */
    private function createRegistrationSession($userId, $chatId) {
        $stmt = $this->pdo->prepare("INSERT INTO registration_sessions (user_id, chat_id, current_step, data, created_at) VALUES (?, ?, 'start', ?, NOW())");
        return $stmt->execute([$userId, $chatId, json_encode([])]);
    }
    
    private function getRegistrationSession($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM registration_sessions WHERE user_id = ? AND completed = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateRegistrationStep($userId, $step) {
        $stmt = $this->pdo->prepare("UPDATE registration_sessions SET current_step = ? WHERE user_id = ? AND completed = 0");
        return $stmt->execute([$step, $userId]);
    }
    
    private function updateRegistrationData($userId, $key, $value) {
        $session = $this->getRegistrationSession($userId);
        if (!$session) return false;
        
        $data = json_decode($session['data'], true);
        $data[$key] = $value;
        
        $stmt = $this->pdo->prepare("UPDATE registration_sessions SET data = ? WHERE user_id = ? AND completed = 0");
        return $stmt->execute([json_encode($data), $userId]);
    }
    
    private function getRegistrationData($userId) {
        $session = $this->getRegistrationSession($userId);
        return $session ? json_decode($session['data'], true) : [];
    }
    
    private function cleanupRegistrationSession($userId) {
        $stmt = $this->pdo->prepare("UPDATE registration_sessions SET completed = 1 WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Store user address
     */
    private function storeUserAddress($userId, $address) {
        // Simple address parsing - can be enhanced with better NLP
        $addressData = [
            'user_id' => $userId,
            'address_type' => 'home',
            'full_address' => $address,
            'is_default' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $sql = "INSERT INTO user_addresses (user_id, address_type, full_address, is_default, created_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $addressData['user_id'],
            $addressData['address_type'],
            $addressData['full_address'],
            $addressData['is_default'],
            $addressData['created_at']
        ]);
    }
    
    /**
     * Notify admin about new user registration
     */
    private function notifyAdminNewUser($userData) {
        global $adminnumber;
        
        $message = "🆕 <b>کاربر جدید ثبت‌نام کرد</b>\n\n";
        $message .= "👤 نام: " . ($userData['first_name'] ?? '') . " " . ($userData['last_name'] ?? '') . "\n";
        $message .= "📱 تلفن: " . ($userData['phone_number'] ?? 'ثبت نشده') . "\n";
        $message .= "📧 ایمیل: " . ($userData['email'] ?? 'ثبت نشده') . "\n";
        $message .= "🆔 کد ملی: " . ($userData['national_id'] ?? 'ثبت نشده') . "\n";
        $message .= "📅 تاریخ ثبت‌نام: " . jdate('Y/m/d H:i:s');
        
        return $this->telegram->sendMessage([
            'chat_id' => $adminnumber,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Send user profile
     */
    private function sendUserProfile($userId, $chatId) {
        $user = $this->getUserByTelegramId($userId);
        
        $message = "👤 <b>پروفایل کاربری شما</b>\n\n";
        $message .= "🆔 شناسه کاربر: <code>" . $user['user_id'] . "</code>\n";
        $message .= "👤 نام: " . ($user['first_name'] ?? '') . " " . ($user['last_name'] ?? '') . "\n";
        $message .= "📱 تلفن: " . ($user['phone_number'] ?? 'ثبت نشده') . "\n";
        $message .= "📧 ایمیل: " . ($user['email'] ?? 'ثبت نشده') . "\n";
        $message .= "💰 موجودی: " . number_format($user['balance']) . " ریال\n";
        $message .= "📊 سطح تأیید: " . $this->getVerificationLevelText($user['verification_level']) . "\n";
        $message .= "📅 تاریخ عضویت: " . jdate('Y/m/d', strtotime($user['created_at']));
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✏️ ویرایش پروفایل', 'callback_data' => 'edit_profile'],
                    ['text' => '💳 شارژ حساب', 'callback_data' => 'charge_account']
                ],
                [
                    ['text' => '🛍️ خرید سرویس', 'callback_data' => 'buy_service'],
                    ['text' => '📊 تراکنش‌ها', 'callback_data' => 'transactions']
                ],
                [
                    ['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']
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
     * Helper methods
     */
    private function getWelcomeMessage() {
        return "🌟 <b>به تلگرام وب حرفه‌ای خوش آمدید!</b>\n\n" .
               "برای استفاده از امکانات ربات، لطفاً اطلاعات خود را تکمیل کنید.\n" .
               "این اطلاعات برای ارائه خدمات بهتر و امنیت حساب شما استفاده خواهد شد.";
    }
    
    private function getRegistrationStartKeyboard() {
        return json_encode([
            'keyboard' => [
                [
                    ['text' => '🚀 شروع ثبت‌نام']
                ],
                [
                    ['text' => '❌ انصراف']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);
    }
    
    private function getMainMenuKeyboard() {
        return json_encode([
            'keyboard' => [
                [
                    ['text' => '👤 پروفایل کاربری'],
                    ['text' => '💰 مدیریت مالی']
                ],
                [
                    ['text' => '🛍️ خرید سرویس'],
                    ['text' => '📊 تراکنش‌ها']
                ],
                [
                    ['text' => '⚙️ تنظیمات'],
                    ['text' => '🆘 پشتیبانی']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
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
    
    private function sendErrorMessage($chatId, $message) {
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "❌ خطا: " . $message,
            'parse_mode' => 'HTML'
        ]);
    }
    
    private function handleUnknownStep($userId, $chatId) {
        return $this->sendErrorMessage($chatId, "مرحله نامشخص. لطفاً دوباره تلاش کنید.");
    }
}

/**
 * Telegram API Wrapper Class
 */
class TelegramAPI {
    
    public function sendMessage($params) {
        return telegram('sendMessage', $params);
    }
    
    public function sendPhoto($params) {
        return telegram('sendPhoto', $params);
    }
    
    public function answerCallbackQuery($params) {
        return telegram('answerCallbackQuery', $params);
    }
    
    public function editMessageText($params) {
        return telegram('editMessageText', $params);
    }
    
    public function deleteMessage($params) {
        return telegram('deleteMessage', $params);
    }
}

/**
 * Registration session management functions
 * These should be added to the database schema
 */
function createRegistrationSessionsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS registration_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        chat_id BIGINT NOT NULL,
        current_step VARCHAR(50),
        data TEXT,
        completed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_completed (completed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    return $pdo->exec($sql);
}

// Initialize the registration sessions table
try {
    createRegistrationSessionsTable($pdo);
} catch (Exception $e) {
    error_log("Failed to create registration sessions table: " . $e->getMessage());
}

?>