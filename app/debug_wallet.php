<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$file = __DIR__ . '/wallet.php';
if (!file_exists($file)) {
    die("File not found: $file");
}

// Check syntax using built-in linter if available (via exec)
// But since CLI php is missing, we can only try to include it and see if it crashes.
// However, including it will execute it.
// We can try `php_check_syntax` but it is deprecated/removed in newer PHP.

echo "Checking includes...\n";
$includes = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../jdf.php',
    __DIR__ . '/wallet/database.php',
    __DIR__ . '/wallet/bot_interface.php'
];

foreach ($includes as $inc) {
    if (file_exists($inc)) {
        echo "✅ Found: $inc\n";
    } else {
        echo "❌ MISSING: $inc\n";
    }
}

echo "\nAttempting to parse file...\n";
$content = file_get_contents($file);

// Check for common errors
if (strpos($content, '$this->') !== false) {
    echo "⚠️ Warning: Found usage of '$this->' which might be invalid outside a class.\n";
    // Show context
    preg_match_all('/\$this->.*/', $content, $matches);
    print_r($matches[0]);
} else {
    echo "✅ No usage of '$this->' found.\n";
}

echo "\nDone basic checks.\n";
