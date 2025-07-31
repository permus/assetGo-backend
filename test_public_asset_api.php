<?php

/**
 * Test file for Public Asset APIs
 * This file demonstrates how to use the public asset endpoints
 */

// Base URL for your API
$baseUrl = 'https://assetgo.themeai.com/api';

// Headers for API requests (no authentication required for public APIs)
$headers = [
    'Content-Type: application/json',
    'Accept: application/json'
];

/**
 * Test 1: Get public assets list
 */
function testGetPublicAssets() {
    global $baseUrl, $headers;
    
    echo "=== Testing Public Assets List ===\n";
    
    // Basic request
    $url = "$baseUrl/assets/public";
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "Basic Response:\n";
    printResponse($response);
    
    // With company filtering
    $params = [
        'company_slug' => 'acme-corp',
        'per_page' => 10
    ];
    
    $urlWithParams = $url . '?' . http_build_query($params);
    $response = makeRequest($urlWithParams, 'GET', [], $headers);
    
    echo "\nWith Company Filter Response:\n";
    printResponse($response);
}

/**
 * Test 2: Get public assets with search and filters
 */
function testGetPublicAssetsWithFilters() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Public Assets with Filters ===\n";
    
    $params = [
        'search' => 'laptop',
        'category_id' => 1,
        'status' => 'active',
        'sort_by' => 'name',
        'sort_dir' => 'asc',
        'per_page' => 5
    ];
    
    $url = "$baseUrl/assets/public?" . http_build_query($params);
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "With Search and Filters Response:\n";
    printResponse($response);
}

/**
 * Test 3: Get specific public asset
 */
function testGetPublicAsset($assetId = 34) {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Public Asset Details for Asset ID: $assetId ===\n";
    
    $url = "$baseUrl/assets/$assetId/public";
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "Asset Details Response:\n";
    printResponse($response);
}

/**
 * Test 4: Test pagination
 */
function testPagination() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Pagination ===\n";
    
    $url = "$baseUrl/assets/public?per_page=5&page=1";
    $response = makeRequest($url, 'GET', [], $headers);
    
    $data = json_decode($response['body'], true);
    
    if ($data && isset($data['data'])) {
        echo "Page 1:\n";
        echo "Total: " . $data['data']['pagination']['total'] . "\n";
        echo "Current Page: " . $data['data']['pagination']['current_page'] . "\n";
        echo "Per Page: " . $data['data']['pagination']['per_page'] . "\n";
        echo "Items on this page: " . count($data['data']['assets']) . "\n";
        
        if ($data['data']['pagination']['last_page'] > 1) {
            echo "\nTesting Page 2:\n";
            $url = "$baseUrl/assets/public?per_page=5&page=2";
            $response = makeRequest($url, 'GET', [], $headers);
            $data2 = json_decode($response['body'], true);
            
            if ($data2 && isset($data2['data'])) {
                echo "Page 2 Current Page: " . $data2['data']['pagination']['current_page'] . "\n";
                echo "Items on page 2: " . count($data2['data']['assets']) . "\n";
            }
        }
    }
}

/**
 * Test 5: Test different filter combinations
 */
function testFilterCombinations() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Filter Combinations ===\n";
    
    $filters = [
        [
            'name' => 'Category Filter',
            'params' => ['category_id' => 1, 'per_page' => 3]
        ],
        [
            'name' => 'Price Range Filter',
            'params' => ['min_value' => 500, 'max_value' => 2000, 'per_page' => 3]
        ],
        [
            'name' => 'Search by Manufacturer',
            'params' => ['search' => 'Dell', 'per_page' => 3]
        ],
        [
            'name' => 'Status Filter',
            'params' => ['status' => 'active', 'per_page' => 3]
        ]
    ];
    
    foreach ($filters as $filter) {
        echo "\n--- Testing: " . $filter['name'] . " ---\n";
        
        $url = "$baseUrl/assets/public?" . http_build_query($filter['params']);
        $response = makeRequest($url, 'GET', [], $headers);
        
        echo "Response:\n";
        printResponse($response);
    }
}

/**
 * Test 6: Test error handling
 */
function testErrorHandling() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Error Handling ===\n";
    
    // Test non-existent asset
    echo "\n--- Testing Non-existent Asset ---\n";
    $url = "$baseUrl/assets/99999/public";
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "Non-existent Asset Response:\n";
    printResponse($response);
    
    // Test invalid parameters
    echo "\n--- Testing Invalid Parameters ---\n";
    $url = "$baseUrl/assets/public?per_page=999&sort_by=invalid_field";
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "Invalid Parameters Response:\n";
    printResponse($response);
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
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
    
    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        echo "cURL Error: " . curl_error($ch) . "\n";
    }
    
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
            
            if (isset($data['data']['assets'])) {
                echo "Assets found: " . count($data['data']['assets']) . "\n";
                echo "Total: " . $data['data']['pagination']['total'] . "\n";
                
                // Show first asset as example
                if (!empty($data['data']['assets'])) {
                    $firstAsset = $data['data']['assets'][0];
                    echo "First Asset:\n";
                    echo "  - ID: " . $firstAsset['id'] . "\n";
                    echo "  - Name: " . $firstAsset['name'] . "\n";
                    echo "  - Serial: " . $firstAsset['serial_number'] . "\n";
                    echo "  - Status: " . $firstAsset['status'] . "\n";
                    echo "  - Category: " . ($firstAsset['category']['name'] ?? 'N/A') . "\n";
                }
            } elseif (isset($data['data']['asset'])) {
                $asset = $data['data']['asset'];
                echo "Asset Details:\n";
                echo "  - ID: " . $asset['id'] . "\n";
                echo "  - Name: " . $asset['name'] . "\n";
                echo "  - Serial: " . $asset['serial_number'] . "\n";
                echo "  - Status: " . $asset['status'] . "\n";
                echo "  - QR Code: " . ($asset['qr_code_url'] ? 'Available' : 'Not available') . "\n";
                echo "  - Images: " . count($asset['images']) . "\n";
                echo "  - Tags: " . count($asset['tags']) . "\n";
            }
        } else {
            echo "Success: false\n";
            echo "Message: " . ($data['message'] ?? 'Unknown error') . "\n";
            
            if (isset($data['errors'])) {
                echo "Errors:\n";
                foreach ($data['errors'] as $field => $errors) {
                    echo "  - $field: " . implode(', ', $errors) . "\n";
                }
            }
        }
    } else {
        echo "Invalid JSON response\n";
        echo "Raw response: " . substr($response['body'], 0, 500) . "\n";
    }
}

// Run tests
echo "Public Asset API Tests\n";
echo "=====================\n\n";

// Uncomment the tests you want to run
// testGetPublicAssets();
// testGetPublicAssetsWithFilters();
// testGetPublicAsset(34);
// testPagination();
// testFilterCombinations();
// testErrorHandling();

echo "\nTo run tests, uncomment the test functions at the bottom of this file.\n";
echo "Make sure to:\n";
echo "1. Update the \$baseUrl variable to match your API URL\n";
echo "2. Ensure your Laravel application is running\n";
echo "3. Have some test data in your database\n";
echo "4. The public API endpoints are accessible without authentication\n";
echo "\nExample usage:\n";
echo "curl \"$baseUrl/assets/public?company_slug=acme-corp&per_page=5\"\n";
echo "curl \"$baseUrl/assets/34/public\"\n"; 