<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../x-ui_single.php';

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

        if ($panelType == 'marzban') {
            // Get inbounds from Marzban
            // Marzban.php has getinbounds($location) function
            $inbounds = getinbounds($panelName);
            
            if (is_array($inbounds)) {
                foreach ($inbounds as $key => $inbound) {
                    // Marzban structure might vary, usually it's a list of inbounds
                    // We need to extract tag (name) and protocol
                    
                    $name = $inbound['tag'] ?? "Inbound $key";
                    $protocol = $inbound['protocol'] ?? 'unknown';
                    $settings = json_encode($inbound); // Store full setting just in case

                    // Insert into Inbound table
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
        } 
        elseif ($panelType == 'x-ui_single') {
            $inbounds = getinbounds_xui($panelName);
            
            if (is_array($inbounds) && count($inbounds) > 0) {
                foreach ($inbounds as $inbound) {
                    $name = $inbound['remark'] ?? "Inbound " . ($inbound['id'] ?? '?');
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
                echo "  -> Failed to fetch inbounds or empty (or API not supported).\n";
            }
        }
        
        if (!$is_cli) echo "<br>";
    }
    
    echo "Synchronization complete.\n";
    if (!$is_cli) echo "<br><a href='../panel/inbound.php'>Return to Inbounds</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
