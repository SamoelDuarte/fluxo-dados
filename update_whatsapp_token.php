<?php
/**
 * Quick script to update WhatsApp access token
 * Usage: php update_whatsapp_token.php "YOUR_NEW_TOKEN_HERE"
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line');
}

if (!isset($argv[1]) || empty(trim($argv[1]))) {
    echo "Usage: php update_whatsapp_token.php \"YOUR_TOKEN_HERE\"\n";
    echo "Example: php update_whatsapp_token.php \"EAATBVK83U6QBPzp...\"\n";
    exit(1);
}

require __DIR__ . '/bootstrap/app.php';

$newToken = trim($argv[1]);

// Validate token looks reasonable
if (strlen($newToken) < 100) {
    echo "ERROR: Token seems too short. Token should be 190+ characters.\n";
    echo "Length provided: " . strlen($newToken) . " characters\n";
    exit(1);
}

try {
    $updated = DB::table('whatsapp')->update([
        'access_token' => $newToken,
        'updated_at' => now(),
    ]);
    
    if ($updated) {
        echo "✓ Token updated successfully!\n";
        echo "Token length: " . strlen($newToken) . " characters\n";
        echo "First 20 chars: " . substr($newToken, 0, 20) . "...\n";
        echo "Last 20 chars: ..." . substr($newToken, -20) . "\n";
        exit(0);
    } else {
        echo "ERROR: No rows updated. Check if whatsapp table exists.\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
