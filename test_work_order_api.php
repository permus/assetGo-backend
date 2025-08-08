<?php
/**
 * Work Order API Test Script
 * 
 * This script demonstrates how to use the Work Order API endpoints.
 * Make sure to replace {YOUR_TOKEN} with your actual authentication token.
 * 
 * Usage: php test_work_order_api.php
 */

$baseUrl = 'http://localhost:8000/api';
$token = '{YOUR_TOKEN}'; // Replace with your actual token

$headers = [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
];

echo "=== Work Order API Test Script ===\n\n";

// Test 1: Get Work Order Filters
echo "1. Getting Work Order Filters...\n";
$filters = makeRequest('GET', '/work-orders/filters', null, $headers);
echo "Response: " . json_encode($filters, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Create a Work Order
echo "2. Creating a Work Order...\n";
$workOrderData = [
    'title' => 'Fix HVAC System',
    'description' => 'HVAC system needs maintenance and repair',
    'priority' => 'high',
    'status' => 'open',
    'due_date' => date('Y-m-d\TH:i:s.000000\Z', strtotime('+7 days')),
    'estimated_hours' => 4.5,
    'notes' => 'Check refrigerant levels and clean filters'
];

$createdWorkOrder = makeRequest('POST', '/work-orders', $workOrderData, $headers);
echo "Response: " . json_encode($createdWorkOrder, JSON_PRETTY_PRINT) . "\n\n";

if (isset($createdWorkOrder['data']['id'])) {
    $workOrderId = $createdWorkOrder['data']['id'];
    
    // Test 3: Get Work Order Count
    echo "3. Getting Work Order Count...\n";
    $count = makeRequest('GET', '/work-orders/count', null, $headers);
    echo "Response: " . json_encode($count, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 4: List Work Orders
    echo "4. Listing Work Orders...\n";
    $workOrders = makeRequest('GET', '/work-orders?per_page=5', null, $headers);
    echo "Response: " . json_encode($workOrders, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 5: Show Specific Work Order
    echo "5. Showing Work Order #{$workOrderId}...\n";
    $workOrder = makeRequest('GET', "/work-orders/{$workOrderId}", null, $headers);
    echo "Response: " . json_encode($workOrder, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 6: Update Work Order
    echo "6. Updating Work Order #{$workOrderId}...\n";
    $updateData = [
        'status' => 'in_progress',
        'actual_hours' => 2.0,
        'notes' => 'Started work - checking refrigerant levels'
    ];
    $updatedWorkOrder = makeRequest('PUT', "/work-orders/{$workOrderId}", $updateData, $headers);
    echo "Response: " . json_encode($updatedWorkOrder, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 7: Get Work Order Statistics
    echo "7. Getting Work Order Statistics...\n";
    $statistics = makeRequest('GET', '/work-orders/statistics', null, $headers);
    echo "Response: " . json_encode($statistics, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 8: Get Work Order Analytics
    echo "8. Getting Work Order Analytics...\n";
    $analytics = makeRequest('GET', '/work-orders/analytics?date_range=30', null, $headers);
    echo "Response: " . json_encode($analytics, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 9: Complete Work Order
    echo "9. Completing Work Order #{$workOrderId}...\n";
    $completeData = [
        'status' => 'completed',
        'actual_hours' => 4.0,
        'notes' => 'Completed successfully - system working properly'
    ];
    $completedWorkOrder = makeRequest('PUT', "/work-orders/{$workOrderId}", $completeData, $headers);
    echo "Response: " . json_encode($completedWorkOrder, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 10: Filter Work Orders
    echo "10. Filtering Work Orders (completed status)...\n";
    $filteredWorkOrders = makeRequest('GET', '/work-orders?status=completed&per_page=3', null, $headers);
    echo "Response: " . json_encode($filteredWorkOrders, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 11: Search Work Orders
    echo "11. Searching Work Orders (HVAC)...\n";
    $searchResults = makeRequest('GET', '/work-orders?search=HVAC&per_page=3', null, $headers);
    echo "Response: " . json_encode($searchResults, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 12: Get Overdue Work Orders
    echo "12. Getting Overdue Work Orders...\n";
    $overdueWorkOrders = makeRequest('GET', '/work-orders?is_overdue=true', null, $headers);
    echo "Response: " . json_encode($overdueWorkOrders, JSON_PRETTY_PRINT) . "\n\n";
}

echo "=== Test Script Completed ===\n";

/**
 * Make HTTP request
 */
function makeRequest($method, $endpoint, $data = null, $headers = []) {
    $url = 'http://localhost:8000/api' . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decodedResponse = json_decode($response, true);
    
    echo "HTTP {$httpCode} - {$method} {$endpoint}\n";
    
    return $decodedResponse;
}

/**
 * Alternative: Curl Commands for Manual Testing
 */
echo "\n=== Manual Curl Commands ===\n\n";

echo "# 1. Get Work Order Filters\n";
echo "curl -X GET '{$baseUrl}/work-orders/filters' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "# 2. Create Work Order\n";
echo "curl -X POST '{$baseUrl}/work-orders' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -H 'Accept: application/json' \\\n";
echo "  -d '{\n";
echo "    \"title\": \"Fix HVAC System\",\n";
echo "    \"description\": \"HVAC system needs maintenance\",\n";
echo "    \"priority\": \"high\",\n";
echo "    \"status\": \"open\",\n";
echo "    \"due_date\": \"" . date('Y-m-d\TH:i:s.000000\Z', strtotime('+7 days')) . "\",\n";
echo "    \"estimated_hours\": 4.5,\n";
echo "    \"notes\": \"Check refrigerant levels\"\n";
echo "  }'\n\n";

echo "# 3. List Work Orders\n";
echo "curl -X GET '{$baseUrl}/work-orders?per_page=5' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "# 4. Get Work Order Count\n";
echo "curl -X GET '{$baseUrl}/work-orders/count' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "# 5. Get Work Order Analytics\n";
echo "curl -X GET '{$baseUrl}/work-orders/analytics?date_range=30' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "# 6. Get Work Order Statistics\n";
echo "curl -X GET '{$baseUrl}/work-orders/statistics' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "# 7. Filter Work Orders by Status\n";
echo "curl -X GET '{$baseUrl}/work-orders?status=open&priority=high' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "# 8. Search Work Orders\n";
echo "curl -X GET '{$baseUrl}/work-orders?search=HVAC' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "# 9. Get Overdue Work Orders\n";
echo "curl -X GET '{$baseUrl}/work-orders?is_overdue=true' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "# 10. Update Work Order (replace {id} with actual ID)\n";
echo "curl -X PUT '{$baseUrl}/work-orders/{id}' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -H 'Accept: application/json' \\\n";
echo "  -d '{\n";
echo "    \"status\": \"completed\",\n";
echo "    \"actual_hours\": 4.0,\n";
echo "    \"notes\": \"Completed successfully\"\n";
echo "  }'\n\n";

echo "# 11. Delete Work Order (replace {id} with actual ID)\n";
echo "curl -X DELETE '{$baseUrl}/work-orders/{id}' \\\n";
echo "  -H 'Authorization: Bearer {$token}' \\\n";
echo "  -H 'Accept: application/json'\n\n";
?>
