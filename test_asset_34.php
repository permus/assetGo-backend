<?php

/**
 * Test file for Asset ID 34 Public API
 * This file tests the specific asset endpoint: /api/assets/34/public
 */

// Base URL for your API
$baseUrl = 'https://assetgo.themeai.com/api';

// Headers for API requests (no authentication required for public APIs)
$headers = [
    'Content-Type: application/json',
    'Accept: application/json'
];

/**
 * Test the specific asset 34 public endpoint
 */
function testAsset34() {
    global $baseUrl, $headers;
    
    echo "=== Testing Asset ID 34 Public Endpoint ===\n";
    
    $assetId = 34;
    $url = "$baseUrl/assets/$assetId/public";
    
    echo "URL: $url\n";
    echo "Method: GET\n";
    echo "Headers: " . json_encode($headers) . "\n\n";
    
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "Response Status: " . $response['status_code'] . "\n";
    echo "Response Body:\n";
    printResponse($response);
}

/**
 * Test with different asset IDs
 */
function testMultipleAssets() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Multiple Asset IDs ===\n";
    
    $assetIds = [1, 34, 100, 99999]; // Test valid and invalid IDs
    
    foreach ($assetIds as $assetId) {
        echo "\n--- Testing Asset ID: $assetId ---\n";
        
        $url = "$baseUrl/assets/$assetId/public";
        $response = makeRequest($url, 'GET', [], $headers);
        
        echo "Status: " . $response['status_code'] . "\n";
        
        $data = json_decode($response['body'], true);
        if ($data && isset($data['success']) && $data['success']) {
            $asset = $data['data']['asset'];
            echo "Asset Name: " . $asset['name'] . "\n";
            echo "Serial Number: " . $asset['serial_number'] . "\n";
            echo "Status: " . $asset['status'] . "\n";
        } else {
            echo "Error: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    }
}

/**
 * Test the assets list endpoint for comparison
 */
function testAssetsList() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Assets List Endpoint ===\n";
    
    $url = "$baseUrl/assets/public?per_page=5";
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "URL: $url\n";
    echo "Status: " . $response['status_code'] . "\n";
    
    $data = json_decode($response['body'], true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "Total Assets: " . $data['data']['pagination']['total'] . "\n";
        echo "Assets on this page: " . count($data['data']['assets']) . "\n";
        
        foreach ($data['data']['assets'] as $index => $asset) {
            echo ($index + 1) . ". ID: " . $asset['id'] . " - " . $asset['name'] . "\n";
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
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
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
    $data = json_decode($response['body'], true);
    
    if ($data) {
        if (isset($data['success']) && $data['success']) {
            echo "✅ SUCCESS\n";
            
            if (isset($data['data']['asset'])) {
                $asset = $data['data']['asset'];
                echo "\n📋 Asset Details:\n";
                echo "   ID: " . $asset['id'] . "\n";
                echo "   Name: " . $asset['name'] . "\n";
                echo "   Serial Number: " . $asset['serial_number'] . "\n";
                echo "   Description: " . ($asset['description'] ?? 'N/A') . "\n";
                echo "   Model: " . ($asset['model'] ?? 'N/A') . "\n";
                echo "   Manufacturer: " . ($asset['manufacturer'] ?? 'N/A') . "\n";
                echo "   Status: " . $asset['status'] . "\n";
                echo "   Purchase Price: $" . ($asset['purchase_price'] ?? 'N/A') . "\n";
                echo "   Health Score: " . ($asset['health_score'] ?? 'N/A') . "\n";
                
                if (isset($asset['category'])) {
                    echo "   Category: " . $asset['category']['name'] . "\n";
                }
                
                if (isset($asset['location'])) {
                    echo "   Location: " . $asset['location']['name'] . "\n";
                }
                
                if (isset($asset['company'])) {
                    echo "   Company: " . $asset['company']['name'] . "\n";
                }
                
                echo "   Tags: " . count($asset['tags']) . "\n";
                echo "   Images: " . count($asset['images']) . "\n";
                echo "   QR Code: " . (isset($asset['qr_code_url']) ? 'Available' : 'Not available') . "\n";
                
                echo "\n📅 Timestamps:\n";
                echo "   Created: " . $asset['created_at'] . "\n";
                echo "   Updated: " . $asset['updated_at'] . "\n";
                
            } else {
                echo "❌ No asset data found in response\n";
            }
        } else {
            echo "❌ FAILED\n";
            echo "Message: " . ($data['message'] ?? 'Unknown error') . "\n";
            
            if (isset($data['errors'])) {
                echo "Errors:\n";
                foreach ($data['errors'] as $field => $errors) {
                    echo "  - $field: " . implode(', ', $errors) . "\n";
                }
            }
        }
    } else {
        echo "❌ Invalid JSON response\n";
        echo "Raw response (first 500 chars):\n";
        echo substr($response['body'], 0, 500) . "\n";
    }
}

// Run the test
echo "Asset ID 34 Public API Test\n";
echo "===========================\n\n";

// Test the specific asset 34
testAsset34();

// Uncomment to test multiple assets
// testMultipleAssets();

// Uncomment to test the assets list
// testAssetsList();

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test completed!\n";
echo "\nTo test manually with curl:\n";
echo "curl -X GET \"$baseUrl/assets/34/public\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -H \"Accept: application/json\"\n"; 