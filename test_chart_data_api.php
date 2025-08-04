<?php

// Test file for Asset Chart Data API
// Usage: php test_chart_data_api.php

// Configuration
$baseUrl = 'http://localhost:8000/api'; // Adjust this to your local server URL
$assetId = 1; // Replace with an actual asset ID from your database

// Test data for creating an asset with depreciation data
$testAssetData = [
    'name' => 'Test Asset for Chart',
    'description' => 'Asset to test depreciation chart functionality',
    'serial_number' => 'TEST-CHART-001',
    'purchase_price' => 10000.00,
    'depreciation' => 1000.00,
    'depreciation_life' => 60, // 5 years in months
    'status' => 'active'
];

echo "=== Asset Chart Data API Test ===\n\n";

// Function to make HTTP requests
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $headers = array_merge($defaultHeaders, $headers);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'response' => $response,
        'data' => json_decode($response, true)
    ];
}

// Test 1: Get chart data for existing asset
echo "1. Testing Chart Data API for Asset ID: $assetId\n";
echo "URL: {$baseUrl}/assets/{$assetId}/chart-data\n\n";

$response = makeRequest("{$baseUrl}/assets/{$assetId}/chart-data");

echo "Status Code: {$response['status_code']}\n";
echo "Response:\n";
print_r($response['data']);
echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 2: Create a new asset with depreciation data (if you have authentication)
echo "2. Testing Asset Creation with Depreciation Data\n";
echo "Note: This requires authentication. You may need to add a Bearer token.\n\n";

// Uncomment the following lines if you have authentication set up
/*
$authHeaders = [
    'Authorization: Bearer YOUR_TOKEN_HERE'
];

$createResponse = makeRequest("{$baseUrl}/assets", 'POST', $testAssetData, $authHeaders);
echo "Create Asset Status Code: {$createResponse['status_code']}\n";
echo "Create Asset Response:\n";
print_r($createResponse['data']);

if ($createResponse['status_code'] === 201 && isset($createResponse['data']['data']['id'])) {
    $newAssetId = $createResponse['data']['data']['id'];
    echo "\n3. Testing Chart Data for Newly Created Asset ID: $newAssetId\n";
    
    $chartResponse = makeRequest("{$baseUrl}/assets/{$newAssetId}/chart-data", 'GET', null, $authHeaders);
    echo "Chart Data Status Code: {$chartResponse['status_code']}\n";
    echo "Chart Data Response:\n";
    print_r($chartResponse['data']);
}
*/

echo "\n=== Test Complete ===\n";
echo "\nTo test with authentication:\n";
echo "1. Get a Bearer token by logging in via POST /api/login\n";
echo "2. Add the token to the Authorization header\n";
echo "3. Uncomment the authentication test code above\n";
echo "\nExpected Chart Data Response Structure:\n";
echo "- depreciation_months: Array of month numbers\n";
echo "- depreciation_values: Array of corresponding asset values\n";
echo "- current_index: Current month index in the depreciation timeline\n";
echo "- has_data: Boolean indicating if chart data is available\n";
echo "- total_months: Total number of months in the depreciation period\n";
echo "- depreciation_per_month: Monthly depreciation amount\n";
echo "- life_in_months: Total asset life in months\n"; 