<?php

// Test file for Asset Health & Performance Chart API
// Usage: php test_health_performance_api.php

// Configuration
$baseUrl = 'http://localhost:8000/api'; // Adjust this to your local server URL
$assetId = 1; // Replace with an actual asset ID from your database

echo "=== Asset Health & Performance Chart API Test ===\n\n";

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

// Test 1: Get health & performance chart data for existing asset (default 12 months)
echo "1. Testing Health & Performance Chart API for Asset ID: $assetId (12 months)\n";
echo "URL: {$baseUrl}/assets/{$assetId}/health-performance-chart\n\n";

$response = makeRequest("{$baseUrl}/assets/{$assetId}/health-performance-chart");

echo "Status Code: {$response['status_code']}\n";
echo "Response:\n";
print_r($response['data']);
echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 2: Get health & performance chart data for 6 months
echo "2. Testing Health & Performance Chart API for Asset ID: $assetId (6 months)\n";
echo "URL: {$baseUrl}/assets/{$assetId}/health-performance-chart?months=6\n\n";

$response = makeRequest("{$baseUrl}/assets/{$assetId}/health-performance-chart?months=6");

echo "Status Code: {$response['status_code']}\n";
echo "Response:\n";
print_r($response['data']);
echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 3: Get health & performance chart data for 3 months
echo "3. Testing Health & Performance Chart API for Asset ID: $assetId (3 months)\n";
echo "URL: {$baseUrl}/assets/{$assetId}/health-performance-chart?months=3\n\n";

$response = makeRequest("{$baseUrl}/assets/{$assetId}/health-performance-chart?months=3");

echo "Status Code: {$response['status_code']}\n";
echo "Response:\n";
print_r($response['data']);
echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 4: Test with authentication (if you have a token)
echo "4. Testing with Authentication\n";
echo "Note: This requires authentication. You may need to add a Bearer token.\n\n";

// Uncomment the following lines if you have authentication set up
/*
$authHeaders = [
    'Authorization: Bearer YOUR_TOKEN_HERE'
];

$authResponse = makeRequest("{$baseUrl}/assets/{$assetId}/health-performance-chart?months=12", 'GET', null, $authHeaders);
echo "Authenticated Request Status Code: {$authResponse['status_code']}\n";
echo "Authenticated Response:\n";
print_r($authResponse['data']);
*/

echo "\n=== Test Complete ===\n";
echo "\nTo test with authentication:\n";
echo "1. Get a Bearer token by logging in via POST /api/login\n";
echo "2. Add the token to the Authorization header\n";
echo "3. Uncomment the authentication test code above\n";
echo "\nExpected Health & Performance Chart Data Response Structure:\n";
echo "- dates: Array of month labels (YYYY-MM format)\n";
echo "- health_scores: Array of health scores for each month (0-100)\n";
echo "- performance_scores: Array of performance scores for each month (0-100)\n";
echo "- maintenance_counts: Array of maintenance activities count for each month\n";
echo "- current_index: Current month index in the timeline\n";
echo "- has_data: Boolean indicating if chart data is available\n";
echo "- total_months: Total number of months in the data\n";
echo "- metrics: Object containing calculated metrics\n";
echo "  - average_health_score: Average health score over the period\n";
echo "  - average_performance_score: Average performance score over the period\n";
echo "  - total_maintenance_count: Total number of maintenance activities\n";
echo "  - health_trend: Overall health trend (positive = improving, negative = declining)\n";
echo "  - performance_trend: Overall performance trend\n";
echo "  - current_health_score: Current health score\n";
echo "  - current_performance_score: Current performance score\n";
echo "\nHealth Score Calculation:\n";
echo "- Base: Asset's current health_score or 100\n";
echo "- Activity Impact: maintenance_completed (+5), maintenance_overdue (-10), repair (-15), etc.\n";
echo "- Natural Degradation: -0.5 points per month\n";
echo "- Range: Clamped between 0 and 100\n";
echo "\nPerformance Score Calculation:\n";
echo "- Base: 100\n";
echo "- Maintenance Impact: completed (+5), overdue (-15), scheduled (+2)\n";
echo "- Range: Clamped between 0 and 100\n"; 