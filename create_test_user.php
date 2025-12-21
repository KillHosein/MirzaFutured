<?php
// Create test user for debugging
require_once 'app/includes/config.php';
require_once 'app/includes/Database.php';
require_once 'app/includes/UserManager.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = Database::getInstance();
    $userManager = new UserManager($db);
    
    // Check if test user already exists
    $testUser = $userManager->getUserByTelegramId('123456789');
    
    if (!$testUser) {
        // Create test user
        $testUserData = [
            'id' => '123456789',
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'language_code' => 'fa',
            'is_premium' => false,
            'allows_write_to_pm' => true,
            'photo_url' => null
        ];
        
        $newUser = $userManager->createUser($testUserData);
        
        if ($newUser) {
            echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
            echo "<h2>✅ Test User Created Successfully!</h2>";
            echo "<p><strong>Telegram ID:</strong> 123456789</p>";
            echo "<p><strong>Name:</strong> Test User</p>";
            echo "<p><strong>Username:</strong> @testuser</p>";
            echo "<hr>";
            echo "<h3>Test URLs:</h3>";
            echo "<ul>";
            echo "<li><a href='debug_auth.php?telegram_id=123456789'>Test with Debug Script</a></li>";
            echo "<li><a href='app/index.php?telegram_id=123456789'>Test with App</a></li>";
            echo "<li><a href='test_telegram_id_auth.html'>Test with HTML Form</a></li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div style='font-family: Arial, sans-serif; padding: 20px; color: red;'>";
            echo "<h2>❌ Failed to create test user</h2>";
            echo "</div>";
        }
    } else {
        echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
        echo "<h2>ℹ️ Test User Already Exists</h2>";
        echo "<p><strong>Telegram ID:</strong> {$testUser['telegram_id']}</p>";
        echo "<p><strong>Name:</strong> {$testUser['first_name']} {$testUser['last_name']}</p>";
        echo "<p><strong>Username:</strong> @{$testUser['username']}</p>";
        echo "<hr>";
        echo "<h3>Test URLs:</h3>";
        echo "<ul>";
        echo "<li><a href='debug_auth.php?telegram_id=123456789'>Test with Debug Script</a></li>";
        echo "<li><a href='app/index.php?telegram_id=123456789'>Test with App</a></li>";
        echo "<li><a href='test_telegram_id_auth.html'>Test with HTML Form</a></li>";
        echo "</ul>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='font-family: Arial, sans-serif; padding: 20px; color: red;'>";
    echo "<h2>❌ Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>