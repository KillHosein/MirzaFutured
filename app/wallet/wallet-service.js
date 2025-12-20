/**
 * Wallet Service for Telegram Web App
 * Handles wallet operations and card-to-card transactions
 */

class WalletService {
    constructor() {
        this.apiBaseUrl = '/app/wallet/api.php';
        this.token = window.Telegram?.WebApp?.initData || '';
        this.user = window.Telegram?.WebApp?.initDataUnsafe?.user;
        
        // Initialize Telegram Web App
        if (window.Telegram?.WebApp) {
            window.Telegram.WebApp.ready();
            window.Telegram.WebApp.expand();
        }
    }
    
    /**
     * Make API request
     */
    async makeRequest(action, data = {}, method = 'POST') {
        try {
            const response = await fetch(this.apiBaseUrl, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Token': this.token
                },
                body: JSON.stringify({
                    action: action,
                    user_id: this.user?.id,
                    ...data
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (!result.status) {
                throw new Error(result.msg || 'Unknown error');
            }
            
            return result.obj;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }
    
    /**
     * Get wallet balance
     */
    async getBalance() {
        try {
            const result = await this.makeRequest('get_user_wallet_balance', {}, 'GET');
            return result;
        } catch (error) {
            throw new Error('Failed to get wallet balance: ' + error.message);
        }
    }
    
    /**
     * Get wallet transactions
     */
    async getTransactions(limit = 50, offset = 0) {
        try {
            const result = await this.makeRequest('get_user_wallet_transactions', {
                limit: limit,
                offset: offset
            }, 'GET');
            return result.transactions;
        } catch (error) {
            throw new Error('Failed to get wallet transactions: ' + error.message);
        }
    }
    
    /**
     * Get card-to-card transactions
     */
    async getCardToCardTransactions(limit = 50, offset = 0) {
        try {
            const result = await this.makeRequest('get_user_card_to_card_transactions', {
                limit: limit,
                offset: offset
            }, 'GET');
            return result.transactions;
        } catch (error) {
            throw new Error('Failed to get card-to-card transactions: ' + error.message);
        }
    }
    
    /**
     * Create card-to-card transaction
     */
    async createCardToCardTransaction(transactionData) {
        try {
            const result = await this.makeRequest('create_card_to_card_transaction', {
                source_card_number: transactionData.sourceCardNumber,
                destination_card_number: transactionData.destinationCardNumber,
                amount: transactionData.amount,
                bank_name: transactionData.bankName,
                transaction_date: transactionData.transactionDate || new Date().toISOString()
            });
            return result;
        } catch (error) {
            throw new Error('Failed to create card-to-card transaction: ' + error.message);
        }
    }
    
    /**
     * Validate card number using Luhn algorithm
     */
    validateCardNumber(cardNumber) {
        const digits = cardNumber.replace(/[^0-9]/g, '');
        
        if (digits.length !== 16) {
            return { valid: false, message: 'شماره کارت باید ۱۶ رقم باشد' };
        }
        
        let sum = 0;
        let alternate = false;
        
        for (let i = digits.length - 1; i >= 0; i--) {
            let n = parseInt(digits[i]);
            
            if (alternate) {
                n *= 2;
                if (n > 9) {
                    n = (n % 10) + 1;
                }
            }
            
            sum += n;
            alternate = !alternate;
        }
        
        const valid = (sum % 10 === 0);
        return {
            valid: valid,
            message: valid ? 'شماره کارت معتبر است' : 'شماره کارت نامعتبر است'
        };
    }
    
    /**
     * Format card number with spaces
     */
    formatCardNumber(cardNumber) {
        const digits = cardNumber.replace(/[^0-9]/g, '');
        return digits.replace(/(.{4})/g, '$1 ').trim();
    }
    
    /**
     * Parse amount (remove commas and convert to number)
     */
    parseAmount(amount) {
        return parseInt(amount.toString().replace(/[,،\s]/g, ''));
    }
    
    /**
     * Format amount with commas
     */
    formatAmount(amount) {
        return amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    /**
     * Show loading indicator
     */
    showLoading(message = 'در حال پردازش...') {
        const loadingElement = document.createElement('div');
        loadingElement.id = 'wallet-loading';
        loadingElement.className = 'wallet-loading';
        loadingElement.innerHTML = `
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <div class="loading-text">${message}</div>
            </div>
        `;
        document.body.appendChild(loadingElement);
    }
    
    /**
     * Hide loading indicator
     */
    hideLoading() {
        const loadingElement = document.getElementById('wallet-loading');
        if (loadingElement) {
            loadingElement.remove();
        }
    }
    
    /**
     * Show error message
     */
    showError(message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'wallet-error';
        errorElement.innerHTML = `
            <div class="error-content">
                <div class="error-icon">⚠️</div>
                <div class="error-message">${message}</div>
                <button class="error-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        document.body.appendChild(errorElement);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (errorElement.parentElement) {
                errorElement.remove();
            }
        }, 5000);
    }
    
    /**
     * Show success message
     */
    showSuccess(message) {
        const successElement = document.createElement('div');
        successElement.className = 'wallet-success';
        successElement.innerHTML = `
            <div class="success-content">
                <div class="success-icon">✅</div>
                <div class="success-message">${message}</div>
                <button class="success-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        document.body.appendChild(successElement);
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            if (successElement.parentElement) {
                successElement.remove();
            }
        }, 3000);
    }
    
    /**
     * Initialize wallet UI
     */
    async initializeWalletUI() {
        try {
            this.showLoading('در حال بارگذاری اطلاعات کیف پول...');
            
            const balance = await this.getBalance();
            const transactions = await this.getTransactions(5, 0);
            
            this.renderWalletDashboard(balance, transactions);
            
        } catch (error) {
            this.showError('خطا در بارگذاری اطلاعات کیف پول: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    /**
     * Render wallet dashboard
     */
    renderWalletDashboard(balance, transactions) {
        const container = document.getElementById('wallet-container');
        if (!container) return;
        
        container.innerHTML = `
            <div class="wallet-dashboard">
                <div class="balance-card">
                    <div class="balance-header">
                        <h3>موجودی کیف پول</h3>
                        <span class="balance-icon">💰</span>
                    </div>
                    <div class="balance-amount">
                        <span class="amount">${this.formatAmount(balance.balance)}</span>
                        <span class="currency">تومان</span>
                    </div>
                </div>
                
                <div class="wallet-actions">
                    <button class="wallet-btn wallet-btn-primary" onclick="walletService.showDepositForm()">
                        💳 افزایش موجودی
                    </button>
                    <button class="wallet-btn wallet-btn-secondary" onclick="walletService.showTransactions()">
                        📋 تراکنشها
                    </button>
                </div>
                
                <div class="recent-transactions">
                    <h3>تراکنشهای اخیر</h3>
                    <div class="transactions-list">
                        ${transactions.length > 0 ? transactions.map(transaction => `
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-type">${this.getTransactionTypeIcon(transaction.transaction_type)} ${this.getTransactionTypeLabel(transaction.transaction_type)}</div>
                                    <div class="transaction-description">${transaction.description || 'بدون توضیح'}</div>
                                    <div class="transaction-date">${transaction.created_at}</div>
                                </div>
                                <div class="transaction-amount ${transaction.amount > 0 ? 'positive' : 'negative'}">
                                    ${transaction.amount > 0 ? '+' : ''}${this.formatAmount(transaction.amount)}
                                </div>
                            </div>
                        `).join('') : '<div class="no-transactions">هیچ تراکنشی یافت نشد</div>'}
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Show deposit form
     */
    showDepositForm() {
        const container = document.getElementById('wallet-container');
        if (!container) return;
        
        container.innerHTML = `
            <div class="deposit-form">
                <div class="form-header">
                    <h3>افزایش موجودی کیف پول</h3>
                    <button class="back-btn" onclick="walletService.initializeWalletUI()">🔙</button>
                </div>
                
                <div class="deposit-methods">
                    <div class="method-card" onclick="walletService.showCardToCardForm()">
                        <div class="method-icon">💳</div>
                        <div class="method-title">کارت به کارت</div>
                        <div class="method-description">انتقال از کارت بانکی شما</div>
                    </div>
                </div>
                
                <div class="deposit-info">
                    <h4>اطلاعات مهم</h4>
                    <ul>
                        <li>حداقل مبلغ: ۱۰٬۰۰۰ تومان</li>
                        <li>زمان پردازش: تا ۳۰ دقیقه</li>
                        <li>پشتیبانی: ۲۴ ساعته</li>
                    </ul>
                </div>
            </div>
        `;
    }
    
    /**
     * Show card-to-card form
     */
    showCardToCardForm() {
        const container = document.getElementById('wallet-container');
        if (!container) return;
        
        container.innerHTML = `
            <div class="card-to-card-form">
                <div class="form-header">
                    <h3>افزایش موجودی با کارت به کارت</h3>
                    <button class="back-btn" onclick="walletService.showDepositForm()">🔙</button>
                </div>
                
                <div class="destination-card-info">
                    <h4>شماره کارت مقصد</h4>
                    <div class="card-number" id="destination-card">6037 9912 3456 7890</div>
                    <div class="bank-name">بانک ملی ایران</div>
                    <button class="copy-btn" onclick="walletService.copyCardNumber()">📋 کپی</button>
                </div>
                
                <form id="card-to-card-form" onsubmit="walletService.submitCardToCardForm(event)">
                    <div class="form-group">
                        <label for="amount">مبلغ (تومان)</label>
                        <input type="text" id="amount" name="amount" required 
                               placeholder="مثال: 50000" 
                               oninput="walletService.formatAmountInput(this)">
                        <div class="error-message" id="amount-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="sourceCardNumber">شماره کارت مبدا</label>
                        <input type="text" id="sourceCardNumber" name="sourceCardNumber" required 
                               placeholder="مثال: 6037 9912 3456 7890" 
                               oninput="walletService.formatCardNumberInput(this)">
                        <div class="error-message" id="card-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bankName">نام بانک</label>
                        <input type="text" id="bankName" name="bankName" required 
                               placeholder="مثال: بانک ملی ایران">
                    </div>
                    
                    <div class="form-group">
                        <label for="trackingCode">شماره پیگیری (اختیاری)</label>
                        <input type="text" id="trackingCode" name="trackingCode" 
                               placeholder="در صورت داشتن شماره پیگیری وارد کنید">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="wallet-btn wallet-btn-primary">
                            ✅ ارسال درخواست
                        </button>
                        <button type="button" class="wallet-btn wallet-btn-secondary" 
                                onclick="walletService.showDepositForm()">
                            ❌ انصراف
                        </button>
                    </div>
                </form>
                
                <div class="form-info">
                    <h4>راهنما</h4>
                    <ul>
                        <li>ابتدا از کارت خود به کارت مقصد انتقال وجه انجام دهید</li>
                        <li>سپس اطلاعات تراکنش را در فرم بالا وارد کنید</li>
                        <li>پس از تایید ادمین، مبلغ به کیف پول شما افزوده میشود</li>
                    </ul>
                </div>
            </div>
        `;
    }
    
    /**
     * Show transactions list
     */
    async showTransactions() {
        try {
            this.showLoading('در حال بارگذاری تراکنشها...');
            
            const walletTransactions = await this.getTransactions(20, 0);
            const cardToCardTransactions = await this.getCardToCardTransactions(20, 0);
            
            const container = document.getElementById('wallet-container');
            if (!container) return;
            
            container.innerHTML = `
                <div class="transactions-list">
                    <div class="form-header">
                        <h3>تراکنشهای کیف پول</h3>
                        <button class="back-btn" onclick="walletService.initializeWalletUI()">🔙</button>
                    </div>
                    
                    <div class="transaction-tabs">
                        <button class="tab-btn active" onclick="walletService.switchTab('wallet')">
                            تراکنشهای کیف پول
                        </button>
                        <button class="tab-btn" onclick="walletService.switchTab('card-to-card')">
                            کارت به کارت
                        </button>
                    </div>
                    
                    <div id="wallet-transactions" class="tab-content active">
                        ${walletTransactions.length > 0 ? walletTransactions.map(transaction => `
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-type">${this.getTransactionTypeIcon(transaction.transaction_type)} ${this.getTransactionTypeLabel(transaction.transaction_type)}</div>
                                    <div class="transaction-description">${transaction.description || 'بدون توضیح'}</div>
                                    <div class="transaction-date">${transaction.created_at}</div>
                                </div>
                                <div class="transaction-amount ${transaction.amount > 0 ? 'positive' : 'negative'}">
                                    ${transaction.amount > 0 ? '+' : ''}${this.formatAmount(transaction.amount)}
                                </div>
                            </div>
                        `).join('') : '<div class="no-transactions">هیچ تراکنشی یافت نشد</div>'}
                    </div>
                    
                    <div id="card-to-card-transactions" class="tab-content">
                        ${cardToCardTransactions.length > 0 ? cardToCardTransactions.map(transaction => `
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-status ${transaction.transaction_status}">${this.getStatusLabel(transaction.transaction_status)}</div>
                                    <div class="transaction-card">💳 ${transaction.source_card_number}</div>
                                    <div class="transaction-date">${transaction.created_at}</div>
                                </div>
                                <div class="transaction-amount">
                                    ${this.formatAmount(transaction.amount)} تومان
                                </div>
                            </div>
                        `).join('') : '<div class="no-transactions">هیچ تراکنشی یافت نشد</div>'}
                    </div>
                </div>
            `;
            
        } catch (error) {
            this.showError('خطا در بارگذاری تراکنشها: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    /**
     * Switch transaction tabs
     */
    switchTab(tab) {
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(t => t.classList.remove('active'));
        contents.forEach(c => c.classList.remove('active'));
        
        if (tab === 'wallet') {
            tabs[0].classList.add('active');
            document.getElementById('wallet-transactions').classList.add('active');
        } else {
            tabs[1].classList.add('active');
            document.getElementById('card-to-card-transactions').classList.add('active');
        }
    }
    
    /**
     * Submit card-to-card form
     */
    async submitCardToCardForm(event) {
        event.preventDefault();
        console.log('Submitting card-to-card form...');
        
        const formData = new FormData(event.target);
        const sourceCard = this.toEnglishDigits(formData.get('sourceCardNumber'));
        const amountStr = this.toEnglishDigits(formData.get('amount'));
        
        // Ensure user_id is available
        const userId = this.user?.id || 12345; // Fallback for testing/debugging
        
        const transactionData = {
            sourceCardNumber: sourceCard.replace(/[^0-9]/g, ''),
            destinationCardNumber: '6037991234567890', // Should be fetched from server
            amount: this.parseAmount(amountStr),
            bankName: formData.get('bankName'),
            trackingCode: formData.get('trackingCode'),
            user_id: userId
        };
        
        console.log('Transaction Data:', transactionData);
        
        // Validate card number
        const cardValidation = this.validateCardNumber(transactionData.sourceCardNumber);
        if (!cardValidation.valid) {
            document.getElementById('card-error').textContent = cardValidation.message;
            document.getElementById('card-error').style.color = 'red';
            return;
        }
        
        // Validate amount
        if (transactionData.amount < 10000) {
            document.getElementById('amount-error').textContent = 'حداقل مبلغ ۱۰٬۰۰۰ تومان است';
            return;
        }
        
        try {
            this.showLoading('در حال ارسال درخواست...');
            
            const result = await this.createCardToCardTransaction(transactionData);
            
            this.showSuccess('تراکنش با موفقیت ثبت شد. پس از تایید ادمین، مبلغ به کیف پول شما افزوده خواهد شد.');
            
            // Reset form
            event.target.reset();
            
            // Go back to wallet dashboard
            setTimeout(() => {
                this.initializeWalletUI();
            }, 2000);
            
        } catch (error) {
            this.showError('خطا در ثبت تراکنش: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    /**
     * Format amount input
     */
    formatAmountInput(input) {
        const value = input.value.replace(/[^0-9]/g, '');
        input.value = this.formatAmount(value);
        
        // Clear error
        document.getElementById('amount-error').textContent = '';
    }
    
    /**
     * Format card number input
     */
    formatCardNumberInput(input) {
        const value = input.value.replace(/[^0-9]/g, '');
        input.value = this.formatCardNumber(value);
        
        // Clear error
        document.getElementById('card-error').textContent = '';
    }
    
    /**
     * Copy destination card number
     */
    copyCardNumber() {
        const cardNumber = document.getElementById('destination-card').textContent.replace(/[^0-9]/g, '');
        navigator.clipboard.writeText(cardNumber).then(() => {
            this.showSuccess('شماره کارت کپی شد');
        }).catch(() => {
            this.showError('خطا در کپی شماره کارت');
        });
    }
    
    /**
     * Get transaction type icon
     */
    getTransactionTypeIcon(type) {
        const icons = {
            'deposit': '💰',
            'withdrawal': '💸',
            'refund': '🔄',
            'purchase': '🛒',
            'commission': '💎'
        };
        return icons[type] || '📊';
    }
    
    /**
     * Get transaction type label
     */
    getTransactionTypeLabel(type) {
        const labels = {
            'deposit': 'واریز',
            'withdrawal': 'برداشت',
            'refund': 'بازگشت وجه',
            'purchase': 'خرید',
            'commission': 'کمیسیون'
        };
        return labels[type] || 'تراکنش';
    }
    
    /**
     * Get status label
     */
    getStatusLabel(status) {
        const labels = {
            'pending': '⏳ در انتظار',
            'confirmed': '✅ تایید شده',
            'rejected': '❌ رد شده',
            'cancelled': '🚫 لغو شده'
        };
        return labels[status] || status;
    }
}

// Initialize wallet service
const walletService = new WalletService();
// Expose to window for global access (fixes inline event handlers)
window.walletService = walletService;

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WalletService;
}