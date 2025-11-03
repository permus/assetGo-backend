<?php

/**
 * Quick test script to verify admin token creation and validation
 * Run: php test_admin_token.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin;
use Laravel\Sanctum\PersonalAccessToken;

echo "Testing Admin Token Authentication\n";
echo str_repeat("=", 50) . "\n\n";

// Check if admin exists
$admin = Admin::first();
if (!$admin) {
    echo "ERROR: No admin found. Run php artisan db:seed --class=AdminSeeder\n";
    exit(1);
}

echo "Admin found: {$admin->email} (ID: {$admin->id})\n\n";

// Create a test token
echo "Creating test token...\n";
$token = $admin->createToken('test_token')->plainTextToken;
echo "Token created: " . substr($token, 0, 20) . "...\n\n";

// Try to find the token
echo "Looking up token in database...\n";
$accessToken = PersonalAccessToken::findToken($token);

if (!$accessToken) {
    echo "ERROR: Token not found in database!\n";
    exit(1);
}

echo "Token found!\n";
echo "  - Tokenable Type: {$accessToken->tokenable_type}\n";
echo "  - Tokenable ID: {$accessToken->tokenable_id}\n";
echo "  - Expected Type: " . Admin::class . "\n";
echo "  - Match: " . ($accessToken->tokenable_type === Admin::class ? "YES" : "NO") . "\n\n";

// Check the admin
$adminFromToken = $accessToken->tokenable;
echo "Admin from token:\n";
if ($adminFromToken) {
    echo "  - ID: {$adminFromToken->id}\n";
    echo "  - Email: {$adminFromToken->email}\n";
    echo "  - Is Admin instance: " . ($adminFromToken instanceof Admin ? "YES" : "NO") . "\n";
} else {
    echo "  - ERROR: Admin not found!\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test token: {$token}\n";
echo "Use this token in your Authorization header: Bearer {$token}\n";

