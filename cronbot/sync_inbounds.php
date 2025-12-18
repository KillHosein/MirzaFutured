<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../x-ui_single.php';
require_once __DIR__ . '/../alireza.php';
require_once __DIR__ . '/../alireza_single.php';
require_once __DIR__ . '/../marzneshin.php';
require_once __DIR__ . '/../hiddify.php';
require_once __DIR__ . '/../s_ui.php';

// Check if run from browser or CLI
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    // Basic auth or session check could be added here
    session_start();
    if (!isset($_SESSION["user"])) {
        die("Access Denied");
    }
}

echo "Starting inbound synchronization...\n";
if (!$is_cli) echo "<br>";

try {
    // 0. Ensure Inbound table exists
    $result = $pdo->query("SHOW TABLES LIKE 'Inbound'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE Inbound (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location VARCHAR(200) NULL,
            protocol VARCHAR(50) NULL,
            nameinbound VARCHAR(200) NULL,
            setting TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Created Inbound table.\n";
        if (!$is_cli) echo "<br>";
    }

    // 0.1 Ensure x_ui table exists (as per error logs)
    $result = $pdo->query("SHOW TABLES LIKE 'x_ui'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE x_ui (
            codepanel VARCHAR(200) PRIMARY KEY,
            setting TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Created x_ui table.\n";
        if (!$is_cli) echo "<br>";
    }

    // 1. Clear existing inbounds (optional, or we can upsert)
    // For now, let's truncate to ensure fresh data, or we can check existence.
    // Truncating is safer for synchronization if we want to remove deleted inbounds.
    $pdo->exec("TRUNCATE TABLE Inbound");
    echo "Cleared Inbound table.\n";
    if (!$is_cli) echo "<br>";

    // 2. Fetch all panels
    $panels = select("marzban_panel", "*", null, null, "fetchAll");
    
    foreach ($panels as $panel) {
        $panelName = $panel['name_panel'];
        $panelType = $panel['type'];
        
        echo "Processing panel: $panelName ($panelType)...\n";
        if (!$is_cli) echo "<br>";

        $inbounds = [];
        if ($panelType == 'marzban') {
            $inbounds = getinbounds($panelName);
        } elseif ($panelType == 'x-ui_single') {
            $inbounds = getinbounds_xui($panelName);
        } elseif ($panelType == 'alireza') {
            $inbounds = getinbounds_alireza($panelName);
        } elseif ($panelType == 'alireza_single') {
            $inbounds = getinbounds_alireza_single($panelName);
        } elseif ($panelType == 'marzneshin') {
            $inbounds = getinbounds_marzneshin($panelName);
        } elseif ($panelType == 'hiddify') {
            $inbounds = getinbounds_hiddify($panelName);
        } elseif ($panelType == 's_ui') {
            $inbounds = getinbounds_sui($panelName);
        }

        if (is_array($inbounds) && count($inbounds) > 0) {
            foreach ($inbounds as $key => $inbound) {
                // Normalize data
                $name = $inbound['tag'] ?? $inbound['remark'] ?? $inbound['name'] ?? "Inbound $key";
                $protocol = $inbound['protocol'] ?? 'unknown';
                $settings = json_encode($inbound);

                $stmt = $pdo->prepare("INSERT INTO Inbound (location, protocol, nameinbound, setting) VALUES (:loc, :prot, :name, :set)");
                $stmt->execute([
                    ':loc' => $panelName,
                    ':prot' => $protocol,
                    ':name' => $name,
                    ':set' => $settings
                ]);
            }
            echo "  -> Synced " . count($inbounds) . " inbounds.\n";
        } else {
            echo "  -> Failed to fetch inbounds or empty.\n";
        }
        
        if (!$is_cli) echo "<br>";
    }
    
    echo "Synchronization complete.\n";
    if (!$is_cli) echo "<br><a href='../panel/inbound.php'>Return to Inbounds</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
