<?php
// Test Luhn Algorithm

function isValidCardNumber($cardNumber) {
    // Remove non-numeric characters
    $cardNumber = preg_replace('/[^0-9]/', '', $cardNumber);
    
    if (strlen($cardNumber) !== 16) {
        return "Length check failed: " . strlen($cardNumber);
    }

    $sum = 0;
    $alternate = false;
    
    for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
        $n = intval($cardNumber[$i]);
        
        if ($alternate) {
            $n *= 2;
            if ($n > 9) {
                $n = ($n % 10) + 1;
            }
        }
        
        $sum += $n;
        $alternate = !$alternate;
    }
    
    return ($sum % 10 == 0) ? "Valid" : "Invalid (Sum: $sum)";
}

// Known valid Iranian card numbers (Sample structure, not real active cards for security, but mathematically valid)
// Melli: 603799...
// Mellat: 610433...
// Saman: 621986...

// Let's generate a valid one for testing or use a known test card
// A valid Luhn number: 49927398716
// Iranian format (16 digits):
// 6037991122334455 -> Let's check checksum
// 5 5 4 4 3 3 2 2 1 1 9 9 7 3 0 6
// sum = 5 + (1) + 4 + (8) + 3 + (6) + 2 + (4) + 1 + (2) + 9 + (9->18->9) + 7 + (6) + 0 + (3)
// This is hard to calc manually.

// Let's use the function to check a few numbers.
$testCards = [
    '6037997599632587', // Randomly typed, likely invalid
    '6037991899670000', // Example
    '6219861000000000', // Saman base
];

foreach ($testCards as $card) {
    echo "Card $card: " . isValidCardNumber($card) . "\n";
}

// Generate a valid card for verification
function generateValidCard($prefix) {
    $card = $prefix;
    while (strlen($card) < 15) {
        $card .= rand(0, 9);
    }
    
    // Calculate check digit
    $sum = 0;
    $alternate = true; // We start from right (which will be the check digit), so for the rest, logic is inverted relative to check loop
    // Actually simpler: just iterate what we have and find what X makes sum%10==0
    
    // Let's use the validation logic to find the check digit
    // The check digit is at index 15.
    // In validation loop (right to left):
    // Index 15 (Check Digit): alternate=false -> n
    // Index 14: alternate=true -> n*2
    // ...
    
    // So for the first 15 digits (indices 0-14):
    // Index 14 corresponds to i=1 in loop? No.
    // Loop: i=15 down to 0.
    // i=15: Check Digit. Alternate=False. Sum += n.
    // i=14: Alternate=True. Sum += Doubled(n).
    
    $tempSum = 0;
    $alternate = true; // Next one (from right, excluding check digit) is i=14 which is alternate=true
    
    for ($i = 14; $i >= 0; $i--) {
        $n = intval($card[$i]);
        if ($alternate) {
            $n *= 2;
            if ($n > 9) $n = ($n % 10) + 1;
        }
        $tempSum += $n;
        $alternate = !$alternate;
    }
    
    $checkDigit = (10 - ($tempSum % 10)) % 10;
    return $card . $checkDigit;
}

$generated = generateValidCard('603799');
echo "Generated Valid Card: $generated\n";
echo "Validation Result: " . isValidCardNumber($generated) . "\n";

// JS Logic Check (Ported to PHP to verify logic is identical)
// JS: loop i = length-1 down to 0.
// i=15: alternate=false. sum+=n.
// i=14: alternate=true. sum+=doubled.
// PHP: same loop.
// So logic is identical.
?>