<?php
/**
 * AssetGo Reports Module - Backend Testing Script
 * 
 * This script tests all the Reports Module API endpoints to ensure
 * they're working correctly with the existing AssetGo system.
 */

// Configuration
$baseUrl = 'http://localhost:8000/api'; // Adjust as needed
$testUser = [
    'email' => 'admin@example.com', // Use your test user
    'password' => 'password'
];

// Test results storage
$testResults = [];
$authToken = null;

/**
 * Make HTTP request
 */
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    global $authToken;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
        'Content-Type: application/json',
        'Accept: application/json',
        $authToken ? "Authorization: Bearer $authToken" : ''
    ], $headers));
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'body' => $response,
        'error' => $error
    ];
}

/**
 * Test authentication
 */
function testAuthentication() {
    global $baseUrl, $testUser, $authToken, $testResults;
    
    echo "🔐 Testing Authentication...\n";
    
    $response = makeRequest("$baseUrl/login", 'POST', $testUser);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['body'], true);
        if (isset($data['data']['token'])) {
            $authToken = $data['data']['token'];
            $testResults['auth'] = ['status' => 'PASS', 'message' => 'Authentication successful'];
            echo "✅ Authentication successful\n";
            return true;
        }
    }
    
    $testResults['auth'] = ['status' => 'FAIL', 'message' => 'Authentication failed'];
    echo "❌ Authentication failed: " . $response['body'] . "\n";
    return false;
}

/**
 * Test Asset Reports
 */
function testAssetReports() {
    global $baseUrl, $testResults;
    
    echo "\n📊 Testing Asset Reports...\n";
    
    $assetReports = [
        'summary' => 'Asset Summary Report',
        'utilization' => 'Asset Utilization Report',
        'depreciation' => 'Asset Depreciation Report',
        'warranty' => 'Asset Warranty Report',
        'compliance' => 'Asset Compliance Report',
        'available' => 'Available Asset Reports'
    ];
    
    foreach ($assetReports as $endpoint => $name) {
        echo "  Testing $name...\n";
        
        $response = makeRequest("$baseUrl/reports/assets/$endpoint");
        
        if ($response['status_code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                $testResults["asset_$endpoint"] = ['status' => 'PASS', 'message' => 'Success'];
                echo "    ✅ $name - PASS\n";
            } else {
                $testResults["asset_$endpoint"] = ['status' => 'FAIL', 'message' => 'Invalid response format'];
                echo "    ❌ $name - FAIL (Invalid response format)\n";
            }
        } else {
            $testResults["asset_$endpoint"] = ['status' => 'FAIL', 'message' => "HTTP {$response['status_code']}"];
            echo "    ❌ $name - FAIL (HTTP {$response['status_code']})\n";
        }
    }
}

/**
 * Test Maintenance Reports
 */
function testMaintenanceReports() {
    global $baseUrl, $testResults;
    
    echo "\n🔧 Testing Maintenance Reports...\n";
    
    $maintenanceReports = [
        'summary' => 'Maintenance Summary Report',
        'compliance' => 'Maintenance Compliance Report',
        'costs' => 'Maintenance Costs Report',
        'downtime' => 'Downtime Analysis Report',
        'failure-analysis' => 'Failure Analysis Report',
        'technician-performance' => 'Technician Performance Report',
        'available' => 'Available Maintenance Reports'
    ];
    
    foreach ($maintenanceReports as $endpoint => $name) {
        echo "  Testing $name...\n";
        
        $response = makeRequest("$baseUrl/reports/maintenance/$endpoint");
        
        if ($response['status_code'] === 200) {
            $data = json_decode($response['body'], true);
            if (isset($data['success']) && $data['success'] === true) {
                $testResults["maintenance_$endpoint"] = ['status' => 'PASS', 'message' => 'Success'];
                echo "    ✅ $name - PASS\n";
            } else {
                $testResults["maintenance_$endpoint"] = ['status' => 'FAIL', 'message' => 'Invalid response format'];
                echo "    ❌ $name - FAIL (Invalid response format)\n";
            }
        } else {
            $testResults["maintenance_$endpoint"] = ['status' => 'FAIL', 'message' => "HTTP {$response['status_code']}"];
            echo "    ❌ $name - FAIL (HTTP {$response['status_code']})\n";
        }
    }
}

/**
 * Test Export Functionality
 */
function testExportFunctionality() {
    global $baseUrl, $testResults;
    
    echo "\n📤 Testing Export Functionality...\n";
    
    // Test export request
    echo "  Testing export request...\n";
    $exportData = [
        'report_key' => 'assets.summary',
        'format' => 'json',
        'params' => [
            'page' => 1,
            'page_size' => 10
        ]
    ];
    
    $response = makeRequest("$baseUrl/reports/export", 'POST', $exportData);
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['body'], true);
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['run_id'])) {
            $runId = $data['data']['run_id'];
            $testResults['export_request'] = ['status' => 'PASS', 'message' => 'Export queued successfully'];
            echo "    ✅ Export request - PASS (Run ID: $runId)\n";
            
            // Test export status check
            echo "  Testing export status check...\n";
            $statusResponse = makeRequest("$baseUrl/reports/runs/$runId");
            
            if ($statusResponse['status_code'] === 200) {
                $statusData = json_decode($statusResponse['body'], true);
                if (isset($statusData['success']) && $statusData['success'] === true) {
                    $testResults['export_status'] = ['status' => 'PASS', 'message' => 'Status check successful'];
                    echo "    ✅ Export status check - PASS\n";
                } else {
                    $testResults['export_status'] = ['status' => 'FAIL', 'message' => 'Invalid status response'];
                    echo "    ❌ Export status check - FAIL (Invalid response)\n";
                }
            } else {
                $testResults['export_status'] = ['status' => 'FAIL', 'message' => "HTTP {$statusResponse['status_code']}"];
                echo "    ❌ Export status check - FAIL (HTTP {$statusResponse['status_code']})\n";
            }
            
            // Test export history
            echo "  Testing export history...\n";
            $historyResponse = makeRequest("$baseUrl/reports/history");
            
            if ($historyResponse['status_code'] === 200) {
                $historyData = json_decode($historyResponse['body'], true);
                if (isset($historyData['success']) && $historyData['success'] === true) {
                    $testResults['export_history'] = ['status' => 'PASS', 'message' => 'History retrieval successful'];
                    echo "    ✅ Export history - PASS\n";
                } else {
                    $testResults['export_history'] = ['status' => 'FAIL', 'message' => 'Invalid history response'];
                    echo "    ❌ Export history - FAIL (Invalid response)\n";
                }
            } else {
                $testResults['export_history'] = ['status' => 'FAIL', 'message' => "HTTP {$historyResponse['status_code']}"];
                echo "    ❌ Export history - FAIL (HTTP {$historyResponse['status_code']})\n";
            }
            
        } else {
            $testResults['export_request'] = ['status' => 'FAIL', 'message' => 'Invalid export response'];
            echo "    ❌ Export request - FAIL (Invalid response)\n";
        }
    } else {
        $testResults['export_request'] = ['status' => 'FAIL', 'message' => "HTTP {$response['status_code']}"];
        echo "    ❌ Export request - FAIL (HTTP {$response['status_code']})\n";
    }
}

/**
 * Test with different parameters
 */
function testWithParameters() {
    global $baseUrl, $testResults;
    
    echo "\n🔍 Testing with Parameters...\n";
    
    // Test asset summary with filters
    echo "  Testing asset summary with filters...\n";
    $params = [
        'date_from' => '2024-01-01',
        'date_to' => '2024-12-31',
        'page' => 1,
        'page_size' => 5
    ];
    
    $queryString = http_build_query($params);
    $response = makeRequest("$baseUrl/reports/assets/summary?$queryString");
    
    if ($response['status_code'] === 200) {
        $data = json_decode($response['body'], true);
        if (isset($data['success']) && $data['success'] === true) {
            $testResults['asset_summary_with_params'] = ['status' => 'PASS', 'message' => 'Parameters handled correctly'];
            echo "    ✅ Asset summary with parameters - PASS\n";
        } else {
            $testResults['asset_summary_with_params'] = ['status' => 'FAIL', 'message' => 'Invalid response with parameters'];
            echo "    ❌ Asset summary with parameters - FAIL (Invalid response)\n";
        }
    } else {
        $testResults['asset_summary_with_params'] = ['status' => 'FAIL', 'message' => "HTTP {$response['status_code']}"];
        echo "    ❌ Asset summary with parameters - FAIL (HTTP {$response['status_code']})\n";
    }
}

/**
 * Test error handling
 */
function testErrorHandling() {
    global $baseUrl, $testResults;
    
    echo "\n⚠️  Testing Error Handling...\n";
    
    // Test invalid export format
    echo "  Testing invalid export format...\n";
    $invalidExportData = [
        'report_key' => 'assets.summary',
        'format' => 'invalid_format',
        'params' => []
    ];
    
    $response = makeRequest("$baseUrl/reports/export", 'POST', $invalidExportData);
    
    if ($response['status_code'] === 422 || $response['status_code'] === 400) {
        $testResults['error_handling'] = ['status' => 'PASS', 'message' => 'Invalid format properly rejected'];
        echo "    ✅ Invalid export format - PASS (Properly rejected)\n";
    } else {
        $testResults['error_handling'] = ['status' => 'FAIL', 'message' => 'Invalid format not properly rejected'];
        echo "    ❌ Invalid export format - FAIL (Should be rejected)\n";
    }
}

/**
 * Generate test report
 */
function generateTestReport() {
    global $testResults;
    
    echo "\n📋 Test Report Summary\n";
    echo "====================\n\n";
    
    $totalTests = count($testResults);
    $passedTests = count(array_filter($testResults, fn($result) => $result['status'] === 'PASS'));
    $failedTests = $totalTests - $passedTests;
    
    echo "Total Tests: $totalTests\n";
    echo "Passed: $passedTests\n";
    echo "Failed: $failedTests\n";
    echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";
    
    echo "Detailed Results:\n";
    echo "-----------------\n";
    
    foreach ($testResults as $testName => $result) {
        $status = $result['status'] === 'PASS' ? '✅' : '❌';
        echo "$status $testName: {$result['message']}\n";
    }
    
    if ($failedTests > 0) {
        echo "\n⚠️  Some tests failed. Please check the implementation.\n";
    } else {
        echo "\n🎉 All tests passed! The Reports Module is working correctly.\n";
    }
}

/**
 * Main test execution
 */
function runTests() {
    global $testUser;
    
    echo "🚀 Starting AssetGo Reports Module Backend Tests\n";
    echo "================================================\n\n";
    
    echo "Test Configuration:\n";
    echo "- Base URL: $GLOBALS[baseUrl]\n";
    echo "- Test User: {$testUser['email']}\n\n";
    
    // Run all tests
    if (!testAuthentication()) {
        echo "\n❌ Authentication failed. Cannot proceed with other tests.\n";
        return;
    }
    
    testAssetReports();
    testMaintenanceReports();
    testExportFunctionality();
    testWithParameters();
    testErrorHandling();
    
    generateTestReport();
}

// Run the tests
runTests();
