<?php
/**
 * Utility functions for Mirza Web App
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

class Utils {
    
    /**
     * Format Persian date
     */
    public static function formatPersianDate($date, $format = 'Y/m/d H:i') {
        if (!$date) return '';
        
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        
        // Convert to Persian calendar (simplified)
        $gregorianYear = date('Y', $timestamp);
        $gregorianMonth = date('n', $timestamp);
        $gregorianDay = date('j', $timestamp);
        
        // Simple conversion (for accurate conversion use a proper library)
        $persianYear = $gregorianYear - 621;
        $persianMonth = $gregorianMonth;
        $persianDay = $gregorianDay;
        
        if ($gregorianMonth < 3 || ($gregorianMonth == 3 && $gregorianDay < 21)) {
            $persianYear--;
            $persianMonth += 10;
        } else {
            $persianMonth -= 2;
        }
        
        $persianMonths = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
        ];
        
        $result = $format;
        $result = str_replace('Y', $persianYear, $result);
        $result = str_replace('m', str_pad($persianMonth, 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('n', $persianMonth, $result);
        $result = str_replace('d', str_pad($persianDay, 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('j', $persianDay, $result);
        $result = str_replace('F', $persianMonths[$persianMonth], $result);
        
        // Time
        $result = str_replace('H', date('H', $timestamp), $result);
        $result = str_replace('i', date('i', $timestamp), $result);
        $result = str_replace('s', date('s', $timestamp), $result);
        
        // Convert English numbers to Persian
        $result = self::convertToPersianNumbers($result);
        
        return $result;
    }
    
    /**
     * Convert English numbers to Persian
     */
    public static function convertToPersianNumbers($string) {
        $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        return str_replace($englishNumbers, $persianNumbers, $string);
    }
    
    /**
     * Convert Persian numbers to English
     */
    public static function convertToEnglishNumbers($string) {
        $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        return str_replace($persianNumbers, $englishNumbers, $string);
    }
    
    /**
     * Format file size
     */
    public static function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Generate random string
     */
    public static function generateRandomString($length = 32, $includeSymbols = false) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($includeSymbols) {
            $characters .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }
        
        $randomString = '';
        $maxIndex = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $maxIndex)];
        }
        
        return $randomString;
    }
    
    /**
     * Truncate text
     */
    public static function truncateText($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get user agent info
     */
    public static function getUserAgentInfo($userAgent = null) {
        $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'Unknown';
        
        // Browser detection
        if (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Opera/i', $userAgent)) {
            $browser = 'Opera';
        }
        
        // OS detection
        if (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $os = 'Mac';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iOS/i', $userAgent)) {
            $os = 'iOS';
        }
        
        // Device detection
        if (preg_match('/Mobile/i', $userAgent)) {
            $device = 'Mobile';
        } elseif (preg_match('/Tablet/i', $userAgent)) {
            $device = 'Tablet';
        } else {
            $device = 'Desktop';
        }
        
        return [
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'user_agent' => $userAgent
        ];
    }
    
    /**
     * Validate Iranian national code (Melli code)
     */
    public static function validateIranianNationalCode($code) {
        if (!preg_match('/^\d{10}$/', $code)) {
            return false;
        }
        
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($code[$i]) * (10 - $i);
        }
        
        $remainder = $sum % 11;
        $controlDigit = intval($code[9]);
        
        if ($remainder < 2) {
            return $controlDigit === $remainder;
        } else {
            return $controlDigit === (11 - $remainder);
        }
    }
    
    /**
     * Validate Iranian mobile number
     */
    public static function validateIranianMobile($mobile) {
        // Remove spaces and convert to English numbers
        $mobile = trim(self::convertToEnglishNumbers($mobile));
        
        // Check if it starts with 09 and has 11 digits
        return preg_match('/^09\d{9}$/', $mobile);
    }
    
    /**
     * Generate QR code URL
     */
    public static function generateQRCodeUrl($text, $size = 200) {
        $encodedText = urlencode($text);
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedText}";
    }
    
    /**
     * Get current timestamp in milliseconds
     */
    public static function getTimestampMs() {
        return round(microtime(true) * 1000);
    }
    
    /**
     * Calculate time ago
     */
    public static function timeAgo($timestamp) {
        $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'همین حالا';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' دقیقه پیش';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' ساعت پیش';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' روز پیش';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' هفته پیش';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' ماه پیش';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' سال پیش';
        }
    }
    
    /**
     * Convert array to CSV string
     */
    public static function arrayToCSV($array, $delimiter = ',', $enclosure = '"') {
        $output = '';
        $handle = fopen('php://temp', 'r+');
        
        foreach ($array as $row) {
            fputcsv($handle, $row, $delimiter, $enclosure);
        }
        
        rewind($handle);
        $output = stream_get_contents($handle);
        fclose($handle);
        
        return $output;
    }
    
    /**
     * Sanitize filename
     */
    public static function sanitizeFilename($filename) {
        // Remove any path information
        $filename = basename($filename);
        
        // Replace spaces with underscores
        $filename = str_replace(' ', '_', $filename);
        
        // Remove any character that is not alphanumeric, underscore, hyphen, or dot
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
        
        // Remove multiple dots
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        
        // Ensure the filename is not empty
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        return $filename;
    }
    
    /**
     * Generate slug from text
     */
    public static function generateSlug($text) {
        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-zA-Z0-9\-]/', '-', $text);
        
        // Remove multiple hyphens
        $text = preg_replace('/-{2,}/', '-', $text);
        
        // Trim hyphens from start and end
        $text = trim($text, '-');
        
        // Convert to lowercase
        $text = strtolower($text);
        
        return $text;
    }
    
    /**
     * Check if string is JSON
     */
    public static function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Mask sensitive data
     */
    public static function maskData($data, $visibleChars = 4) {
        $length = strlen($data);
        if ($length <= $visibleChars) {
            return str_repeat('*', $length);
        }
        
        $masked = substr($data, 0, $visibleChars);
        $masked .= str_repeat('*', $length - $visibleChars);
        
        return $masked;
    }
    
    /**
     * Calculate percentage
     */
    public static function calculatePercentage($value, $total) {
        if ($total == 0) {
            return 0;
        }
        
        return round(($value / $total) * 100, 2);
    }
    
    /**
     * Format currency
     */
    public static function formatCurrency($amount, $currency = 'IRR') {
        if ($currency === 'IRR') {
            return number_format($amount) . ' ریال';
        } elseif ($currency === 'USD') {
            return '$' . number_format($amount, 2);
        } else {
            return number_format($amount) . ' ' . $currency;
        }
    }
}