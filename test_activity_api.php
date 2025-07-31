<?php

/**
 * Test file for Asset Activity APIs
 * This file demonstrates how to use the asset activity endpoints
 */

// Base URL for your API
$baseUrl = 'http://localhost/api';

// Your authentication token (replace with actual token)
$token = 'your-auth-token-here';

// Headers for API requests
$headers = [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
];

/**
 * Test 1: Get activity history for a specific asset
 */
function testGetAssetActivityHistory($assetId = 1) {
    global $baseUrl, $headers;
    
    echo "=== Testing Asset Activity History for Asset ID: $assetId ===\n";
    
    // Basic request
    $url = "$baseUrl/assets/$assetId/activity-history";
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "Basic Response:\n";
    printResponse($response);
    
    // With search and filters
    $params = [
        'search' => 'transfer',
        'action' => 'transferred',
        'date_from' => '2024-01-01',
        'per_page' => 10,
        'sort_by' => 'created_at',
        'sort_dir' => 'desc'
    ];
    
    $urlWithParams = $url . '?' . http_build_query($params);
    $response = makeRequest($urlWithParams, 'GET', [], $headers);
    
    echo "\nWith Filters Response:\n";
    printResponse($response);
}

/**
 * Test 2: Get all asset activities across company
 */
function testGetAllAssetActivities() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing All Asset Activities ===\n";
    
    // Basic request
    $url = "$baseUrl/assets/activities";
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "Basic Response:\n";
    printResponse($response);
    
    // With search and filters
    $params = [
        'search' => 'laptop',
        'action' => 'created',
        'date_from' => '2024-01-01',
        'date_to' => '2024-12-31',
        'per_page' => 20,
        'page' => 1
    ];
    
    $urlWithParams = $url . '?' . http_build_query($params);
    $response = makeRequest($urlWithParams, 'GET', [], $headers);
    
    echo "\nWith Filters Response:\n";
    printResponse($response);
}

/**
 * Test 3: Test different action types
 */
function testDifferentActionTypes() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Different Action Types ===\n";
    
    $actions = ['created', 'updated', 'transferred', 'archived', 'restored'];
    
    foreach ($actions as $action) {
        echo "\n--- Testing Action: $action ---\n";
        
        $url = "$baseUrl/assets/activities?action=$action&per_page=5";
        $response = makeRequest($url, 'GET', [], $headers);
        
        echo "Response for action '$action':\n";
        printResponse($response);
    }
}

/**
 * Test 4: Test pagination
 */
function testPagination() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Pagination ===\n";
    
    $url = "$baseUrl/assets/activities?per_page=5&page=1";
    $response = makeRequest($url, 'GET', [], $headers);
    
    $data = json_decode($response, true);
    
    if ($data && isset($data['data'])) {
        echo "Page 1:\n";
        echo "Total: " . $data['data']['total'] . "\n";
        echo "Current Page: " . $data['data']['current_page'] . "\n";
        echo "Per Page: " . $data['data']['per_page'] . "\n";
        echo "Items on this page: " . count($data['data']['data']) . "\n";
        
        if ($data['data']['last_page'] > 1) {
            echo "\nTesting Page 2:\n";
            $url = "$baseUrl/assets/activities?per_page=5&page=2";
            $response = makeRequest($url, 'GET', [], $headers);
            $data2 = json_decode($response, true);
            
            if ($data2 && isset($data2['data'])) {
                echo "Page 2 Current Page: " . $data2['data']['current_page'] . "\n";
                echo "Items on page 2: " . count($data2['data']['data']) . "\n";
            }
        }
    }
}

/**
 * Helper function to make HTTP requests
 */
function makeRequest($url, $method = 'GET', $data = [], $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'body' => $response
    ];
}

/**
 * Helper function to print formatted response
 */
function printResponse($response) {
    echo "Status Code: " . $response['status_code'] . "\n";
    
    $data = json_decode($response['body'], true);
    if ($data) {
        if (isset($data['success']) && $data['success']) {
            echo "Success: true\n";
            if (isset($data['data']['data'])) {
                echo "Activities found: " . count($data['data']['data']) . "\n";
                echo "Total: " . $data['data']['total'] . "\n";
                
                // Show first activity as example
                if (!empty($data['data']['data'])) {
                    $firstActivity = $data['data']['data'][0];
                    echo "First Activity:\n";
                    echo "  - Action: " . $firstActivity['action'] . "\n";
                    echo "  - Comment: " . ($firstActivity['comment'] ?? 'N/A') . "\n";
                    echo "  - User: " . ($firstActivity['user']['name'] ?? 'N/A') . "\n";
                    echo "  - Date: " . $firstActivity['formatted_date'] . "\n";
                    echo "  - Time Ago: " . $firstActivity['time_ago'] . "\n";
                }
            }
        } else {
            echo "Success: false\n";
            echo "Message: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "Invalid JSON response\n";
        echo "Raw response: " . $response['body'] . "\n";
    }
}

// Run tests
echo "Asset Activity API Tests\n";
echo "=======================\n\n";

// Uncomment the tests you want to run
// testGetAssetActivityHistory(1);
// testGetAllAssetActivities();
// testDifferentActionTypes();
// testPagination();

echo "\nTo run tests, uncomment the test functions at the bottom of this file.\n";
echo "Make sure to:\n";
echo "1. Update the \$baseUrl variable to match your API URL\n";
echo "2. Replace 'your-auth-token-here' with a valid authentication token\n";
echo "3. Ensure your Laravel application is running\n";
echo "4. Have some test data in your database\n"; 