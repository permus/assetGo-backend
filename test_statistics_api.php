<?php

/**
 * Test file for Asset Statistics API
 * This file tests the statistics endpoints to verify maintenance counts
 */

// Base URL for your API
$baseUrl = 'https://assetgo.themeai.com/api';

// Your authentication token (replace with actual token for authenticated endpoints)
$token = 'your-auth-token-here';

// Headers for API requests
$headers = [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
];

// Headers for public API requests (no authentication)
$publicHeaders = [
    'Content-Type: application/json',
    'Accept: application/json'
];

/**
 * Test the authenticated statistics endpoint
 */
function testAuthenticatedStatistics() {
    global $baseUrl, $headers;
    
    echo "=== Testing Authenticated Statistics API ===\n";
    
    $url = "$baseUrl/assets/statistics";
    $response = makeRequest($url, 'GET', [], $headers);
    
    echo "URL: $url\n";
    echo "Status: " . $response['status_code'] . "\n";
    printStatisticsResponse($response);
}

/**
 * Test the public statistics endpoint
 */
function testPublicStatistics($companyId = 1) {
    global $baseUrl, $publicHeaders;
    
    echo "\n=== Testing Public Statistics API ===\n";
    
    $url = "$baseUrl/assets/public/statistics?company_id=$companyId";
    $response = makeRequest($url, 'GET', [], $publicHeaders);
    
    echo "URL: $url\n";
    echo "Status: " . $response['status_code'] . "\n";
    printStatisticsResponse($response);
}

/**
 * Test public statistics with company slug
 */
function testPublicStatisticsWithSlug($companySlug = 'acme-corp') {
    global $baseUrl, $publicHeaders;
    
    echo "\n=== Testing Public Statistics with Company Slug ===\n";
    
    $url = "$baseUrl/assets/public/statistics?company_slug=$companySlug";
    $response = makeRequest($url, 'GET', [], $publicHeaders);
    
    echo "URL: $url\n";
    echo "Status: " . $response['status_code'] . "\n";
    printStatisticsResponse($response);
}

/**
 * Test maintenance schedules endpoint to verify data exists
 */
function testMaintenanceSchedules() {
    global $baseUrl, $headers;
    
    echo "\n=== Testing Maintenance Schedules ===\n";
    
    // First get some assets
    $url = "$baseUrl/assets?per_page=5";
    $response = makeRequest($url, 'GET', [], $headers);
    
    $data = json_decode($response['body'], true);
    if ($data && isset($data['data']['assets']) && !empty($data['data']['assets'])) {
        $firstAsset = $data['data']['assets'][0];
        $assetId = $firstAsset['id'];
        
        echo "Testing maintenance schedules for Asset ID: $assetId\n";
        
        $maintenanceUrl = "$baseUrl/assets/$assetId/maintenance-schedules";
        $maintenanceResponse = makeRequest($maintenanceUrl, 'GET', [], $headers);
        
        echo "Maintenance URL: $maintenanceUrl\n";
        echo "Status: " . $maintenanceResponse['status_code'] . "\n";
        
        $maintenanceData = json_decode($maintenanceResponse['body'], true);
        if ($maintenanceData && isset($maintenanceData['success']) && $maintenanceData['success']) {
            $schedules = $maintenanceData['data']['schedules'] ?? [];
            echo "Found " . count($schedules) . " maintenance schedules\n";
            
            foreach ($schedules as $index => $schedule) {
                echo "  Schedule " . ($index + 1) . ":\n";
                echo "    ID: " . $schedule['id'] . "\n";
                echo "    Type: " . $schedule['schedule_type'] . "\n";
                echo "    Status: " . $schedule['status'] . "\n";
                echo "    Next Due: " . ($schedule['next_due'] ?? 'N/A') . "\n";
                echo "    Frequency: " . ($schedule['frequency'] ?? 'N/A') . "\n";
            }
        } else {
            echo "No maintenance schedules found or error occurred\n";
        }
    } else {
        echo "No assets found to test maintenance schedules\n";
    }
}

/**
 * Test database queries directly (if you have database access)
 */
function testDatabaseQueries() {
    echo "\n=== Database Query Test ===\n";
    echo "This section would test database queries directly.\n";
    echo "You can run these queries in your database to verify data:\n\n";
    
    echo "1. Check if maintenance schedules exist:\n";
    echo "   SELECT COUNT(*) as total_schedules FROM asset_maintenance_schedules;\n\n";
    
    echo "2. Check maintenance schedules by status:\n";
    echo "   SELECT status, COUNT(*) as count FROM asset_maintenance_schedules GROUP BY status;\n\n";
    
    echo "3. Check maintenance schedules with asset info:\n";
    echo "   SELECT ams.id, ams.status, ams.schedule_type, a.name as asset_name, a.company_id\n";
    echo "   FROM asset_maintenance_schedules ams\n";
    echo "   JOIN assets a ON ams.asset_id = a.id\n";
    echo "   LIMIT 10;\n\n";
    
    echo "4. Check assets with maintenance schedules:\n";
    echo "   SELECT COUNT(*) as assets_with_maintenance\n";
    echo "   FROM assets a\n";
    echo "   WHERE EXISTS (SELECT 1 FROM asset_maintenance_schedules ams WHERE ams.asset_id = a.id);\n";
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
 * Helper function to print statistics response
 */
function printStatisticsResponse($response) {
    $data = json_decode($response['body'], true);
    
    if ($data) {
        if (isset($data['success']) && $data['success']) {
            echo "✅ SUCCESS\n";
            $stats = $data['data'];
            
            echo "\n📊 Asset Statistics:\n";
            echo "   Total Assets: " . ($stats['total_assets'] ?? 'N/A') . "\n";
            echo "   Active Assets: " . ($stats['active_assets'] ?? 'N/A') . "\n";
            echo "   Inactive Assets: " . ($stats['inactive_assets'] ?? 'N/A') . "\n";
            
            if (isset($stats['maintenance'])) {
                echo "\n🔧 Maintenance Statistics:\n";
                $maintenance = $stats['maintenance'];
                echo "   Total Schedules: " . ($maintenance['total_schedules'] ?? 'N/A') . "\n";
                echo "   Active Schedules: " . ($maintenance['active_schedules'] ?? 'N/A') . "\n";
                echo "   Overdue Schedules: " . ($maintenance['overdue_schedules'] ?? 'N/A') . "\n";
                echo "   Assets with Maintenance: " . ($maintenance['assets_with_maintenance'] ?? 'N/A') . "\n";
            } else {
                echo "\n🔧 Maintenance Count (Legacy): " . ($stats['maintenance_count'] ?? 'N/A') . "\n";
            }
            
            echo "\n💰 Financial Statistics:\n";
            echo "   Total Asset Value: $" . number_format($stats['total_asset_value'] ?? 0, 2) . "\n";
            echo "   Total Health Score: " . ($stats['total_asset_health'] ?? 'N/A') . "\n";
            echo "   Average Health Score: " . ($stats['average_health_score'] ?? 'N/A') . "\n";
            
            if (isset($stats['status_breakdown'])) {
                echo "\n📈 Status Breakdown:\n";
                foreach ($stats['status_breakdown'] as $status => $count) {
                    echo "   $status: $count\n";
                }
            }
            
            if (isset($stats['category_breakdown'])) {
                echo "\n📂 Category Breakdown:\n";
                foreach ($stats['category_breakdown'] as $category => $count) {
                    echo "   $category: $count\n";
                }
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

// Run tests
echo "Asset Statistics API Test\n";
echo "========================\n\n";

// Test authenticated statistics
testAuthenticatedStatistics();

// Test public statistics
testPublicStatistics(1);

// Test public statistics with company slug
testPublicStatisticsWithSlug('acme-corp');

// Test maintenance schedules
testMaintenanceSchedules();

// Show database query suggestions
testDatabaseQueries();

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test completed!\n";
echo "\nTo test manually with curl:\n";
echo "curl -X GET \"$baseUrl/assets/statistics\" \\\n";
echo "  -H \"Authorization: Bearer $token\" \\\n";
echo "  -H \"Content-Type: application/json\"\n";
echo "\ncurl -X GET \"$baseUrl/assets/public/statistics?company_id=1\" \\\n";
echo "  -H \"Content-Type: application/json\"\n"; 