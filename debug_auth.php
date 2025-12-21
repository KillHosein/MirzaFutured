<?php
// Debug script for testing telegram_id authentication
require_once 'app/includes/config.php';
require_once 'app/includes/Database.php';
require_once 'app/includes/UserManager.php';

session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Authentication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Debug Authentication System</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            // Initialize database
                            $db = Database::getInstance();
                            echo "<div class='alert alert-success'>✅ Database connection successful</div>";
                            
                            // Initialize UserManager
                            $userManager = new UserManager($db);
                            echo "<div class='alert alert-success'>✅ UserManager initialized</div>";
                            
                            // Check current session
                            echo "<h6>Current Session:</h6>";
                            echo "<pre class='bg-light p-2'>";
                            echo "SESSION: " . print_r($_SESSION, true);
                            echo "</pre>";
                            
                            // Test telegram_id from URL
                            $telegramId = $_GET['telegram_id'] ?? null;
                            if ($telegramId) {
                                echo "<div class='alert alert-info'>🔍 Testing telegram_id: <strong>$telegramId</strong></div>";
                                
                                // Test getUserByTelegramId
                                $user = $userManager->getUserByTelegramId($telegramId);
                                
                                if ($user) {
                                    echo "<div class='alert alert-success'>✅ User found in database!</div>";
                                    echo "<h6>User Data:</h6>";
                                    echo "<pre class='bg-light p-2'>";
                                    echo "ID: " . $user['id'] . "\n";
                                    echo "First Name: " . $user['first_name'] . "\n";
                                    echo "Last Name: " . ($user['last_name'] ?? 'N/A') . "\n";
                                    echo "Username: " . ($user['username'] ?? 'N/A') . "\n";
                                    echo "Telegram ID: " . $user['telegram_id'] . "\n";
                                    echo "Language: " . $user['language_code'] . "\n";
                                    echo "</pre>";
                                    
                                    // Test session creation
                                    $_SESSION['user_id'] = $user['id'];
                                    $_SESSION['telegram_id'] = $telegramId;
                                    echo "<div class='alert alert-success'>✅ Session created successfully</div>";
                                    
                                    // Test redirect to app
                                    echo "<div class='alert alert-warning'>🔄 Redirecting to app in 3 seconds...</div>";
                                    echo "<meta http-equiv='refresh' content='3;url=app/index.php'>";
                                    
                                } else {
                                    echo "<div class='alert alert-danger'>❌ User not found in database</div>";
                                    
                                    // Show all users for debugging
                                    echo "<h6>Available users in database:</h6>";
                                    $allUsers = $db->fetchAll('SELECT id, telegram_id, first_name, username FROM users LIMIT 10');
                                    if ($allUsers) {
                                        echo "<table class='table table-sm table-striped'>";
                                        echo "<thead><tr><th>ID</th><th>Telegram ID</th><th>Name</th><th>Username</th></tr></thead>";
                                        foreach ($allUsers as $u) {
                                            echo "<tr>";
                                            echo "<td>{$u['id']}</td>";
                                            echo "<td>{$u['telegram_id']}</td>";
                                            echo "<td>{$u['first_name']}</td>";
                                            echo "<td>{$u['username']}</td>";
                                            echo "</tr>";
                                        }
                                        echo "</table>";
                                    } else {
                                        echo "<div class='alert alert-warning'>⚠️ No users found in database</div>";
                                    }
                                }
                            } else {
                                echo "<div class='alert alert-warning'>ℹ️ Add ?telegram_id=YOUR_ID to test authentication</div>";
                                
                                // Show available test IDs
                                $allUsers = $db->fetchAll('SELECT telegram_id, first_name FROM users LIMIT 5');
                                if ($allUsers) {
                                    echo "<h6>Available test IDs:</h6>";
                                    foreach ($allUsers as $u) {
                                        echo "<a href='?telegram_id={$u['telegram_id']}' class='btn btn-outline-primary btn-sm m-1'>Test with {$u['first_name']} ({$u['telegram_id']})</a>";
                                    }
                                }
                            }
                            
                        } catch (Exception $e) {
                            echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
                        }
                        ?>
                        
                        <hr>
                        <div class="text-center">
                            <a href="app/index.php" class="btn btn-success">Go to App</a>
                            <a href="?clear_session=1" class="btn btn-warning">Clear Session</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>