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

// Test payload with valid data
$testPayload = [
    'assets' => [
        [
            'name' => 'Sample Asset 1',
            'description' => 'Office computer for accounting',
            'category' => 'Equipment',
            'type' => 'Computer',
            'serial_number' => 'SN001',
            'model' => 'Dell OptiPlex',
            'manufacturer' => 'Dell',
            'purchase_date' => '2024-01-15',
            'purchase_price' => '1200.00',
            'depreciation' => '100.00',
            'location' => 'Main Office',
            'department' => 'IT',
            'warranty' => '3 years',
            'insurance' => 'Company policy',
            'health_score' => 95,
            'status' => 'Active',
            'tags' => ['Computer', 'Office', 'IT']
        ],
        [
            'name' => 'Sample Asset 2',
            'description' => 'Executive desk',
            'category' => 'Furniture',
            'type' => 'Desk',
            'serial_number' => 'SN002',
            'model' => 'Executive Desk',
            'manufacturer' => 'OfficeMax',
            'purchase_date' => '2024-02-20',
            'purchase_price' => '800.00',
            'depreciation' => '50.00',
            'location' => 'Conference Room',
            'department' => 'HR',
            'warranty' => '1 year',
            'insurance' => 'Company policy',
            'health_score' => 90,
            'status' => 'Active',
            'tags' => ['Furniture', 'Desk', 'Executive']
        ],
        [
            'name' => 'Sample Asset 3',
            'description' => 'Company vehicle',
            'category' => 'Vehicle',
            'type' => 'Car',
            'serial_number' => 'SN003',
            'model' => 'Toyota Camry',
            'manufacturer' => 'Toyota',
            'purchase_date' => '2024-03-10',
            'purchase_price' => '25000.00',
            'depreciation' => '2000.00',
            'location' => 'Parking Lot',
            'department' => 'Operations',
            'warranty' => '5 years',
            'insurance' => 'Auto insurance',
            'health_score' => 85,
            'status' => 'Active',
            'tags' => ['Vehicle', 'Car', 'Transport']
        ]
    ]
];

// Test payload with non-existent location
$testPayloadWithInvalidLocation = [
    'assets' => [
        [
            'name' => 'Test Asset with Invalid Location',
            'description' => 'Test asset with invalid location',
            'category' => 'Equipment',
            'type' => 'Computer',
            'serial_number' => 'SN004',
            'model' => 'Test Model',
            'manufacturer' => 'Test Manufacturer',
            'purchase_date' => '2024-01-15',
            'purchase_price' => '1000.00',
            'location' => 'Non Existent Location', // This location doesn't exist
            'department' => 'IT'
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

// Step 2: Test bulk import with invalid location
echo "🚫 Testing bulk import with invalid location...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/assets/import-bulk-json');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testPayloadWithInvalidLocation));
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
        echo "❌ Expected failure but got success!\n";
    } else {
        echo "✅ Correctly failed with error: {$result['message']}\n";
    }
} else {
    echo "✅ Correctly failed with HTTP code: $httpCode\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Step 3: Test bulk import with valid data
echo "📦 Testing bulk import with valid data...\n";
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