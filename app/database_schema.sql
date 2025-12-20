-- Professional Telegram Web Application Database Schema
-- Enhanced version with all requested features

-- Users table - Enhanced with additional user information
CREATE TABLE IF NOT EXISTS users (
    id BIGINT PRIMARY KEY,
    user_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(255),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    phone_number VARCHAR(20),
    email VARCHAR(255),
    national_id VARCHAR(20),
    birth_date DATE,
    gender ENUM('male', 'female', 'other'),
    profile_photo VARCHAR(500),
    
    -- Financial fields
    balance DECIMAL(15,2) DEFAULT 0.00,
    total_deposits DECIMAL(15,2) DEFAULT 0.00,
    total_withdrawals DECIMAL(15,2) DEFAULT 0.00,
    
    -- Status and verification
    status ENUM('active', 'inactive', 'banned', 'pending') DEFAULT 'pending',
    verification_level TINYINT DEFAULT 0,
    phone_verified BOOLEAN DEFAULT FALSE,
    email_verified BOOLEAN DEFAULT FALSE,
    identity_verified BOOLEAN DEFAULT FALSE,
    
    -- Security and preferences
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    language VARCHAR(10) DEFAULT 'fa',
    timezone VARCHAR(50) DEFAULT 'Asia/Tehran',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_seen TIMESTAMP,
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_username (username),
    INDEX idx_phone (phone_number),
    INDEX idx_created_at (created_at)
);

-- User addresses table
CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    address_type ENUM('home', 'work', 'billing', 'shipping') DEFAULT 'home',
    country VARCHAR(100) DEFAULT 'Iran',
    province VARCHAR(100),
    city VARCHAR(100),
    district VARCHAR(100),
    postal_code VARCHAR(20),
    full_address TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (address_type)
);

-- Services table - Enhanced with categories and pricing
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    subcategory_id INT,
    
    -- Pricing
    base_price DECIMAL(15,2) NOT NULL,
    discounted_price DECIMAL(15,2),
    currency VARCHAR(3) DEFAULT 'IRR',
    
    -- Service configuration
    service_type ENUM('vpn', 'proxy', 'subscription', 'digital', 'physical') DEFAULT 'vpn',
    duration_days INT DEFAULT 30,
    bandwidth_limit BIGINT,
    device_limit INT DEFAULT 1,
    
    -- Service details
    configuration TEXT,
    server_locations TEXT,
    features TEXT,
    
    -- Status and availability
    status ENUM('active', 'inactive', 'out_of_stock', 'coming_soon') DEFAULT 'active',
    stock_quantity INT DEFAULT -1,
    
    -- Media
    image_url VARCHAR(500),
    icon_class VARCHAR(100),
    
    -- SEO and metadata
    slug VARCHAR(255) UNIQUE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    tags TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_type (service_type),
    INDEX idx_price (base_price),
    INDEX idx_slug (slug)
);

-- Service categories table
CREATE TABLE IF NOT EXISTS service_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    icon_class VARCHAR(100),
    image_url VARCHAR(500),
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES service_categories(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_status (status),
    INDEX idx_sort (sort_order)
);

-- Transactions table - Enhanced financial tracking
CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    
    -- Transaction details
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    type ENUM('deposit', 'withdrawal', 'purchase', 'refund', 'transfer', 'commission', 'bonus') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'IRR',
    
    -- Payment method details
    payment_method ENUM('card_to_card', 'bank_transfer', 'online_payment', 'digital_wallet', 'cryptocurrency', 'cash') DEFAULT 'card_to_card',
    payment_gateway VARCHAR(100),
    payment_reference VARCHAR(255),
    
    -- Card-to-card specific fields
    source_card_number VARCHAR(20),
    destination_card_number VARCHAR(20),
    card_holder_name VARCHAR(255),
    bank_name VARCHAR(100),
    
    -- Transaction status
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'refunded', 'disputed') DEFAULT 'pending',
    
    -- Related entities
    service_id INT,
    order_id BIGINT,
    
    -- Financial tracking
    balance_before DECIMAL(15,2),
    balance_after DECIMAL(15,2),
    fee_amount DECIMAL(15,2) DEFAULT 0.00,
    
    -- Verification and compliance
    verified_by_admin BOOLEAN DEFAULT FALSE,
    admin_notes TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    -- Indexes
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_created_at (created_at),
    INDEX idx_completed_at (completed_at)
);

-- Orders table - Shopping cart and purchase tracking
CREATE TABLE IF NOT EXISTS orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    
    -- Order details
    order_number VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('pending', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    
    -- Financial summary
    subtotal DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    discount_amount DECIMAL(15,2) DEFAULT 0.00,
    shipping_amount DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'IRR',
    
    -- Payment information
    payment_status ENUM('pending', 'paid', 'partially_paid', 'refunded', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(100),
    payment_reference VARCHAR(255),
    
    -- Shipping information
    shipping_address_id INT,
    shipping_method VARCHAR(100),
    tracking_number VARCHAR(100),
    
    -- Customer notes
    customer_notes TEXT,
    admin_notes TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    -- Indexes
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    service_id INT NOT NULL,
    
    -- Item details
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    
    -- Service snapshot
    service_name VARCHAR(255) NOT NULL,
    service_description TEXT,
    service_configuration TEXT,
    
    -- Status
    status ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending',
    expires_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL,
    
    -- Indexes
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_service_id (service_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- User services table - Active services for users
CREATE TABLE IF NOT EXISTS user_services (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    service_id INT NOT NULL,
    order_item_id BIGINT,
    
    -- Service details
    service_name VARCHAR(255) NOT NULL,
    service_type VARCHAR(100),
    configuration TEXT,
    
    -- Usage tracking
    bandwidth_used BIGINT DEFAULT 0,
    bandwidth_limit BIGINT,
    device_count INT DEFAULT 0,
    device_limit INT DEFAULT 1,
    
    -- Status and validity
    status ENUM('active', 'suspended', 'expired', 'cancelled') DEFAULT 'active',
    expires_at TIMESTAMP NOT NULL,
    auto_renew BOOLEAN DEFAULT FALSE,
    
    -- Server assignment
    server_id INT,
    server_location VARCHAR(100),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    
    -- Indexes
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_service_id (service_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_user_service (user_id, service_id)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    
    -- Notification details
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'error', 'success', 'transaction', 'service', 'system') DEFAULT 'info',
    
    -- Delivery channels
    telegram_sent BOOLEAN DEFAULT FALSE,
    email_sent BOOLEAN DEFAULT FALSE,
    sms_sent BOOLEAN DEFAULT FALSE,
    push_sent BOOLEAN DEFAULT FALSE,
    
    -- Status
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    -- Related entities
    related_type VARCHAR(100),
    related_id BIGINT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_for TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    
    -- Indexes
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_scheduled (scheduled_for)
);

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    
    -- Admin details
    full_name VARCHAR(255),
    phone_number VARCHAR(20),
    avatar_url VARCHAR(500),
    
    -- Permissions and roles
    role ENUM('super_admin', 'admin', 'moderator', 'support', 'finance') DEFAULT 'admin',
    permissions TEXT,
    
    -- Status
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    
    -- Security
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
);

-- Admin logs table
CREATE TABLE IF NOT EXISTS admin_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    
    -- Log details
    action VARCHAR(255) NOT NULL,
    resource_type VARCHAR(100),
    resource_id BIGINT,
    old_values TEXT,
    new_values TEXT,
    
    -- Context
    ip_address VARCHAR(45),
    user_agent TEXT,
    additional_data TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'array') DEFAULT 'string',
    description TEXT,
    category VARCHAR(100),
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_category (category)
);

-- Payment gateways table
CREATE TABLE IF NOT EXISTS payment_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    gateway_type VARCHAR(100) NOT NULL,
    
    -- Configuration
    config_data TEXT,
    api_key VARCHAR(500),
    merchant_id VARCHAR(255),
    
    -- Status and fees
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    transaction_fee_percentage DECIMAL(5,2) DEFAULT 0.00,
    transaction_fee_fixed DECIMAL(10,2) DEFAULT 0.00,
    
    -- Limits
    min_amount DECIMAL(15,2) DEFAULT 1000.00,
    max_amount DECIMAL(15,2) DEFAULT 100000000.00,
    daily_limit DECIMAL(15,2) DEFAULT 1000000000.00,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_type (gateway_type),
    INDEX idx_status (status)
);

-- Discount codes table
CREATE TABLE IF NOT EXISTS discount_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    
    -- Discount details
    discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    discount_value DECIMAL(10,2) NOT NULL,
    max_discount_amount DECIMAL(15,2),
    
    -- Usage limits
    usage_limit INT DEFAULT 1,
    usage_count INT DEFAULT 0,
    user_usage_limit INT DEFAULT 1,
    
    -- Validity
    valid_from TIMESTAMP,
    valid_until TIMESTAMP,
    min_order_amount DECIMAL(15,2) DEFAULT 0.00,
    
    -- Applicability
    applicable_to ENUM('all', 'specific_services', 'categories', 'users') DEFAULT 'all',
    applicable_services TEXT,
    applicable_categories TEXT,
    applicable_users TEXT,
    
    -- Status
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_validity (valid_from, valid_until)
);

-- User discount usage table
CREATE TABLE IF NOT EXISTS user_discount_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    discount_code_id INT NOT NULL,
    order_id BIGINT,
    
    -- Usage details
    discount_amount DECIMAL(15,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (discount_code_id) REFERENCES discount_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_user_discount (user_id, discount_code_id),
    INDEX idx_used_at (used_at)
);

-- System logs table
CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    log_level ENUM('debug', 'info', 'warning', 'error', 'critical') DEFAULT 'info',
    category VARCHAR(100),
    message TEXT NOT NULL,
    context TEXT,
    
    -- Request context
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_url VARCHAR(500),
    request_method VARCHAR(10),
    
    -- User context
    user_id BIGINT,
    admin_id INT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_level (log_level),
    INDEX idx_category (category),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public) VALUES
('site_name', 'تلگرام وب حرفه‌ای', 'string', 'نام سایت', 'general', true),
('site_description', 'وب اپلیکیشن تلگرام با قابلیت‌های مالی و خدماتی', 'string', 'توضیحات سایت', 'general', true),
('default_language', 'fa', 'string', 'زبان پیش‌فرض', 'general', true),
('default_currency', 'IRR', 'string', 'واحد پول پیش‌فرض', 'general', true),
('min_deposit_amount', '10000', 'integer', 'حداقل مبلغ شارژ', 'financial', false),
('max_deposit_amount', '100000000', 'integer', 'حداکثر مبلغ شارژ', 'financial', false),
('transaction_fee_percentage', '0', 'integer', 'کارمزد تراکنش (درصد)', 'financial', false),
('transaction_fee_fixed', '0', 'integer', 'کارمزد ثابت تراکنش', 'financial', false),
('card_to_card_enabled', 'true', 'boolean', 'فعال‌سازی کارت به کارت', 'payment', false),
('online_payment_enabled', 'true', 'boolean', 'فعال‌سازی پرداخت آنلاین', 'payment', false),
('notification_telegram_enabled', 'true', 'boolean', 'فعال‌سازی نوتیفیکیشن تلگرام', 'notifications', false),
('notification_email_enabled', 'false', 'boolean', 'فعال‌سازی نوتیفیکیشن ایمیل', 'notifications', false),
('auto_service_activation', 'true', 'boolean', 'فعال‌سازی خودکار سرویس‌ها', 'services', false),
('service_expiry_reminder_days', '3', 'integer', 'روزهای اطلاع‌رسانی انقضا', 'services', false);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role, status) VALUES
('admin', 'admin@telegram-web.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'super_admin', 'active');

-- Insert default payment gateways
INSERT INTO payment_gateways (name, gateway_type, config_data, status) VALUES
('کارت به کارت', 'card_to_card', '{"instructions": "لطفاً پس از انتقال وجه، تصویر رسید را ارسال کنید"}', 'active'),
('درگاه زرین‌پال', 'zarinpal', '{"merchant_id": "your_merchant_id"}', 'inactive'),
('درگاه آقای پرداخت', 'aqayepardakht', '{"api_key": "your_api_key"}', 'inactive');