<?php

/**
 * Asset Transfer API Test Script
 * 
 * This script demonstrates how to use the Asset Transfer API endpoint.
 * Run this script to test the transfer functionality.
 */

// Configuration
$baseUrl = 'http://localhost:8000/api'; // Adjust to your Laravel app URL
$email = 'admin@example.com'; // Replace with a valid user email
$password = 'password'; // Replace with the user's password

// Step 1: Login to get authentication token
echo "🔐 Logging in...\n";
$loginData = [
    'email' => $email,
    'password' => $password
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Login failed. HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$loginResult = json_decode($response, true);
$token = $loginResult['data']['token'] ?? null;

if (!$token) {
    echo "❌ No token received from login\n";
    exit(1);
}

echo "✅ Login successful. Token received.\n\n";

// Step 2: Get assets list to find an asset to transfer
echo "📋 Getting assets list...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/assets');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Failed to get assets. HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$assetsResult = json_decode($response, true);
$assets = $assetsResult['data']['assets'] ?? [];

if (empty($assets)) {
    echo "❌ No assets found. Please create some assets first.\n";
    exit(1);
}

$asset = $assets[0]; // Use the first asset
echo "✅ Found asset: {$asset['name']} (ID: {$asset['id']})\n\n";

// Step 3: Get locations list to find a destination
echo "📍 Getting locations list...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/locations');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Failed to get locations. HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$locationsResult = json_decode($response, true);
$locations = $locationsResult['data'] ?? [];

if (empty($locations)) {
    echo "❌ No locations found. Please create some locations first.\n";
    exit(1);
}

// Find a location different from the asset's current location
$destinationLocation = null;
foreach ($locations as $location) {
    if ($location['id'] != $asset['location_id']) {
        $destinationLocation = $location;
        break;
    }
}

if (!$destinationLocation) {
    echo "❌ No different location found for transfer.\n";
    exit(1);
}

echo "✅ Found destination location: {$destinationLocation['name']} (ID: {$destinationLocation['id']})\n\n";

// Step 4: Transfer the asset
echo "🚚 Transferring asset...\n";
$transferData = [
    'new_location_id' => $destinationLocation['id'],
    'transfer_reason' => 'Relocation',
    'transfer_date' => date('Y-m-d'),
    'notes' => 'Test transfer via API script'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/assets/' . $asset['id'] . '/transfer');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transferData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $transferResult = json_decode($response, true);
    if ($transferResult['success']) {
        echo "✅ Asset transfer successful!\n";
        echo "Transfer ID: {$transferResult['data']['transfer_id']}\n";
        echo "New Location: {$transferResult['data']['new_location']}\n";
        if (isset($transferResult['data']['new_department'])) {
            echo "New Department: {$transferResult['data']['new_department']}\n";
        }
    } else {
        echo "❌ Transfer failed: {$transferResult['message']}\n";
    }
} else {
    echo "❌ Transfer request failed with HTTP code: $httpCode\n";
}

echo "\n🎉 Test completed!\n"; 