<?php
require_once __DIR__ . '/../../config.php';

class WalletDatabase {
    private $pdo;
    private $connect;
    
    public function __construct() {
        global $pdo, $connect;
        $this->pdo = $pdo;
        $this->connect = $connect;
    }
    
    /**
     * Initialize wallet-related tables
     */
    public function initializeTables() {
        try {
            // Create card_to_card_transactions table
            $this->createCardToCardTable();
            // Create wallet_transactions table
            $this->createWalletTransactionsTable();
            // Create bank_cards table for storing verified bank cards
            $this->createBankCardsTable();
            
            return true;
        } catch (Exception $e) {
            error_log("Wallet database initialization error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create card_to_card_transactions table
     */
    private function createCardToCardTable() {
        $tableName = 'card_to_card_transactions';
        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
        $stmt->bindParam(':tableName', $tableName);
        $stmt->execute();
        $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tableExists) {
            $sql = "CREATE TABLE $tableName (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(500) NOT NULL,
                transaction_id VARCHAR(100) UNIQUE NOT NULL,
                source_card_number VARCHAR(20) NOT NULL,
                destination_card_number VARCHAR(20) NOT NULL,
                amount BIGINT NOT NULL,
                amount_toman BIGINT NOT NULL,
                tracking_code VARCHAR(50) DEFAULT NULL,
                reference_number VARCHAR(50) DEFAULT NULL,
                bank_name VARCHAR(100) DEFAULT NULL,
                transaction_date DATETIME DEFAULT NULL,
                transaction_status ENUM('pending', 'confirmed', 'rejected', 'cancelled') DEFAULT 'pending',
                verification_status ENUM('unverified', 'verified', 'failed') DEFAULT 'unverified',
                verification_attempts INT(3) DEFAULT 0,
                verification_response TEXT DEFAULT NULL,
                admin_notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                confirmed_at DATETIME DEFAULT NULL,
                rejected_at DATETIME DEFAULT NULL,
                rejected_reason TEXT DEFAULT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_transaction_id (transaction_id),
                INDEX idx_transaction_status (transaction_status),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
        }
    }
    
    /**
     * Create wallet_transactions table
     */
    private function createWalletTransactionsTable() {
        $tableName = 'wallet_transactions';
        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
        $stmt->bindParam(':tableName', $tableName);
        $stmt->execute();
        $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tableExists) {
            $sql = "CREATE TABLE $tableName (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(500) NOT NULL,
                transaction_type ENUM('deposit', 'withdrawal', 'refund', 'purchase', 'commission') NOT NULL,
                amount BIGINT NOT NULL,
                balance_before BIGINT NOT NULL,
                balance_after BIGINT NOT NULL,
                related_transaction_id VARCHAR(100) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                reference_type VARCHAR(50) DEFAULT NULL,
                reference_id VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_transaction_type (transaction_type),
                INDEX idx_created_at (created_at),
                INDEX idx_related_transaction (related_transaction_id),
                FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
        }
    }
    
    /**
     * Create bank_cards table for storing verified bank cards
     */
    private function createBankCardsTable() {
        $tableName = 'bank_cards';
        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
        $stmt->bindParam(':tableName', $tableName);
        $stmt->execute();
        $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tableExists) {
            $sql = "CREATE TABLE $tableName (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(500) NOT NULL,
                card_number VARCHAR(20) NOT NULL,
                bank_name VARCHAR(100) DEFAULT NULL,
                card_owner_name VARCHAR(200) DEFAULT NULL,
                is_verified BOOLEAN DEFAULT FALSE,
                verification_date DATETIME DEFAULT NULL,
                usage_count INT(11) DEFAULT 0,
                total_amount BIGINT DEFAULT 0,
                last_used_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_card_number (card_number),
                INDEX idx_is_verified (is_verified),
                UNIQUE KEY unique_user_card (user_id, card_number),
                FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
        }
    }
    
    /**
     * Insert a new card-to-card transaction
     */
    public function insertCardToCardTransaction($data) {
        try {
            $sql = "INSERT INTO card_to_card_transactions (
                user_id, transaction_id, source_card_number, destination_card_number, 
                amount, amount_toman, bank_name, transaction_date, transaction_status
            ) VALUES (
                :user_id, :transaction_id, :source_card_number, :destination_card_number,
                :amount, :amount_toman, :bank_name, :transaction_date, :transaction_status
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':transaction_id' => $data['transaction_id'],
                ':source_card_number' => $data['source_card_number'],
                ':destination_card_number' => $data['destination_card_number'],
                ':amount' => $data['amount'],
                ':amount_toman' => $data['amount_toman'],
                ':bank_name' => $data['bank_name'] ?? null,
                ':transaction_date' => $data['transaction_date'] ?? date('Y-m-d H:i:s'),
                ':transaction_status' => $data['transaction_status'] ?? 'pending'
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Insert card-to-card transaction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert a new wallet transaction
     */
    public function insertWalletTransaction($data) {
        try {
            $sql = "INSERT INTO wallet_transactions (
                user_id, transaction_type, amount, balance_before, balance_after,
                related_transaction_id, description, reference_type, reference_id
            ) VALUES (
                :user_id, :transaction_type, :amount, :balance_before, :balance_after,
                :related_transaction_id, :description, :reference_type, :reference_id
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':transaction_type' => $data['transaction_type'],
                ':amount' => $data['amount'],
                ':balance_before' => $data['balance_before'],
                ':balance_after' => $data['balance_after'],
                ':related_transaction_id' => $data['related_transaction_id'] ?? null,
                ':description' => $data['description'] ?? null,
                ':reference_type' => $data['reference_type'] ?? null,
                ':reference_id' => $data['reference_id'] ?? null
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Insert wallet transaction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get card-to-card transaction by transaction ID
     */
    public function getCardToCardTransaction($transaction_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM card_to_card_transactions WHERE transaction_id = ?");
            $stmt->execute([$transaction_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get card-to-card transaction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update card-to-card transaction status
     */
    public function updateCardToCardTransactionStatus($transaction_id, $status, $data = []) {
        try {
            $allowedStatuses = ['pending', 'confirmed', 'rejected', 'cancelled'];
            if (!in_array($status, $allowedStatuses)) {
                throw new Exception("Invalid transaction status");
            }
            
            $sql = "UPDATE card_to_card_transactions SET transaction_status = :status";
            $params = [':status' => $status];
            
            if ($status === 'confirmed') {
                $sql .= ", confirmed_at = :confirmed_at";
                $params[':confirmed_at'] = date('Y-m-d H:i:s');
            } elseif ($status === 'rejected') {
                $sql .= ", rejected_at = :rejected_at, rejected_reason = :rejected_reason";
                $params[':rejected_at'] = date('Y-m-d H:i:s');
                $params[':rejected_reason'] = $data['rejected_reason'] ?? null;
            }
            
            if (isset($data['tracking_code'])) {
                $sql .= ", tracking_code = :tracking_code";
                $params[':tracking_code'] = $data['tracking_code'];
            }
            
            if (isset($data['reference_number'])) {
                $sql .= ", reference_number = :reference_number";
                $params[':reference_number'] = $data['reference_number'];
            }
            
            if (isset($data['admin_notes'])) {
                $sql .= ", admin_notes = :admin_notes";
                $params[':admin_notes'] = $data['admin_notes'];
            }
            
            $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE transaction_id = :transaction_id";
            $params[':transaction_id'] = $transaction_id;
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update card-to-card transaction status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's wallet balance
     */
    public function getUserBalance($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT Balance FROM user WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['Balance'] : 0;
        } catch (PDOException $e) {
            error_log("Get user balance error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user's wallet balance
     */
    public function updateUserBalance($user_id, $new_balance) {
        try {
            $stmt = $this->pdo->prepare("UPDATE user SET Balance = ? WHERE id = ?");
            return $stmt->execute([$new_balance, $user_id]);
        } catch (PDOException $e) {
            error_log("Update user balance error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's card-to-card transactions
     */
    public function getUserCardToCardTransactions($user_id, $limit = 50, $offset = 0) {
        try {
            $sql = "SELECT * FROM card_to_card_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user card-to-card transactions error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's wallet transactions
     */
    public function getUserWalletTransactions($user_id, $limit = 50, $offset = 0) {
        try {
            $sql = "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user wallet transactions error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add or update bank card information
     */
    public function addBankCard($data) {
        try {
            $sql = "INSERT INTO bank_cards (user_id, card_number, bank_name, card_owner_name) 
                    VALUES (:user_id, :card_number, :bank_name, :card_owner_name) 
                    ON DUPLICATE KEY UPDATE 
                    bank_name = VALUES(bank_name), 
                    card_owner_name = VALUES(card_owner_name),
                    updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':user_id' => $data['user_id'],
                ':card_number' => $data['card_number'],
                ':bank_name' => $data['bank_name'] ?? null,
                ':card_owner_name' => $data['card_owner_name'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Add bank card error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's bank cards
     */
    public function getUserBankCards($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM bank_cards WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user bank cards error: " . $e->getMessage());
            return false;
        }
    }
}