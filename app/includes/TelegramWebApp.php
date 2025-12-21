<?php
/**
 * Telegram Web App Handler
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

class TelegramWebApp {
    private $botToken;
    private $initData;
    
    public function __construct($botToken) {
        $this->botToken = $botToken;
    }
    
    /**
     * Validate Telegram Web App initialization data
     */
    public function validateInitData($initData) {
        if (!$initData) {
            return false;
        }
        
        $data = [];
        parse_str($initData, $data);
        
        if (!isset($data['hash'])) {
            return false;
        }
        
        $hash = $data['hash'];
        unset($data['hash']);
        
        ksort($data);
        $dataCheckString = [];
        
        foreach ($data as $key => $value) {
            $dataCheckString[] = $key . '=' . $value;
        }
        
        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $computedHash = bin2hex(hash_hmac('sha256', implode("\n", $dataCheckString), $secretKey, true));
        
        return hash_equals($computedHash, $hash);
    }
    
    /**
     * Get user data from initialization data
     */
    public function getUserData($initData) {
        if (!$this->validateInitData($initData)) {
            return null;
        }
        
        $data = [];
        parse_str($initData, $data);
        
        if (isset($data['user'])) {
            return json_decode($data['user'], true);
        }
        
        return null;
    }
    
    /**
     * Send data to Telegram Web App
     */
    public function sendData($data) {
        echo '<script>';
        echo 'if (window.Telegram && window.Telegram.WebApp) {';
        echo 'window.Telegram.WebApp.sendData(' . json_encode($data) . ');';
        echo '}';
        echo '</script>';
    }
    
    /**
     * Close Web App
     */
    public function close() {
        echo '<script>';
        echo 'if (window.Telegram && window.Telegram.WebApp) {';
        echo 'window.Telegram.WebApp.close();';
        echo '}';
        echo '</script>';
    }
    
    /**
     * Show alert in Web App
     */
    public function showAlert($message, $callback = null) {
        echo '<script>';
        echo 'if (window.Telegram && window.Telegram.WebApp) {';
        echo 'window.Telegram.WebApp.showAlert(' . json_encode($message) . ');';
        if ($callback) {
            echo 'setTimeout(function() { ' . $callback . ' }, 1000);';
        }
        echo '}';
        echo '</script>';
    }
    
    /**
     * Show confirm dialog in Web App
     */
    public function showConfirm($message, $onConfirm, $onCancel = null) {
        echo '<script>';
        echo 'if (window.Telegram && window.Telegram.WebApp) {';
        echo 'window.Telegram.WebApp.showConfirm(' . json_encode($message) . ', function(result) {';
        echo 'if (result) { ' . $onConfirm . ' } else { ' . ($onCancel ?: '') . ' }';
        echo '});';
        echo '}';
        echo '</script>';
    }
}