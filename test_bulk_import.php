<?php

/**
 * Test script for bulk import API
 * 
 * This script tests the bulk import functionality with the specified payload format.
 */

// Configuration
$baseUrl = 'http://localhost:8000/api'; // Adjust to your Laravel app URL
$email = 'admin@example.com'; // Replace with a valid user email
$password = 'password'; // Replace with the user's password

// Test payload
$testPayload = [
    'assets' => [
        [
            'name' => 'Sample Asset 1',
            'category' => 'Equipment',
            'facility_id' => '212',
            'asset_type' => 'Computer',
            'status' => 'Active',
            'description' => 'Office computer for accounting',
            'serial_number' => 'SN001',
            'model' => 'Dell OptiPlex',
            'manufacturer' => 'Dell',
            'purchase_date' => '2024-01-15',
            'purchase_cost' => '1200.00',
            'location' => 'Main Office',
            'department' => 'IT'
        ],
        [
            'name' => 'Sample Asset 2',
            'category' => 'Furniture',
            'facility_id' => '212',
            'asset_type' => 'Desk',
            'status' => 'Active',
            'description' => 'Executive desk',
            'serial_number' => 'SN002',
            'model' => 'Executive Desk',
            'manufacturer' => 'OfficeMax',
            'purchase_date' => '2024-02-20',
            'purchase_cost' => '800.00',
            'location' => 'Conference Room',
            'department' => 'HR'
        ],
        [
            'name' => 'Sample Asset 3',
            'category' => 'Vehicle',
            'facility_id' => '212',
            'asset_type' => 'Car',
            'status' => 'Active',
            'description' => 'Company vehicle',
            'serial_number' => 'SN003',
            'model' => 'Toyota Camry',
            'manufacturer' => 'Toyota',
            'purchase_date' => '2024-03-10',
            'purchase_cost' => '25000.00',
            'location' => 'Parking Lot',
            'department' => 'Operations'
        ]
    ]
];

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

// Step 2: Test bulk import
echo "📦 Testing bulk import...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/assets/import-bulk-json');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayload));
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
    $result = json_decode($response, true);
    if ($result['success']) {
        echo "✅ Bulk import successful!\n";
        echo "Total processed: {$result['data']['total_processed']}\n";
        echo "Imported: {$result['data']['imported_count']}\n";
        echo "Failed: {$result['data']['failed_count']}\n\n";
        
        if (!empty($result['data']['imported'])) {
            echo "Imported assets:\n";
            foreach ($result['data']['imported'] as $asset) {
                echo "- {$asset['asset_id']}: {$asset['name']}\n";
            }
        }
        
        if (!empty($result['data']['failed'])) {
            echo "\nFailed imports:\n";
            foreach ($result['data']['failed'] as $failure) {
                echo "- Row {$failure['index']}: {$failure['name']} - {$failure['error']}\n";
            }
        }
    } else {
        echo "❌ Bulk import failed: {$result['message']}\n";
    }
} else {
    echo "❌ Bulk import request failed with HTTP code: $httpCode\n";
}

echo "\n🎉 Test completed!\n"; 