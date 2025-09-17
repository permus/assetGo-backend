<?php

// Test the AI Asset Analytics API
$url = 'http://assetgo-backend.test/api/ai/asset-analytics/analyze';

$assetContext = [
    [
        'id' => 1,
        'name' => 'HVAC Unit A',
        'type' => 'HVAC',
        'manufacturer' => 'Carrier',
        'model' => 'Model 123',
        'age' => 5,
        'condition' => 'Good',
        'lastMaintenance' => '2024-01-15',
        'nextMaintenance' => '2024-07-15',
        'value' => 25000,
        'location' => 'Building A - Floor 2',
        'status' => 'Active'
    ],
    [
        'id' => 2,
        'name' => 'Generator B',
        'type' => 'Generator',
        'manufacturer' => 'Cummins',
        'model' => 'C150D5',
        'age' => 8,
        'condition' => 'Fair',
        'lastMaintenance' => '2023-12-01',
        'nextMaintenance' => '2024-06-01',
        'value' => 45000,
        'location' => 'Building B - Basement',
        'status' => 'Active'
    ]
];

$data = [
    'assetContext' => $assetContext,
    'assetImages' => [] // No images for this test
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer 1|test_token_here' // Add auth token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($error) {
    echo "cURL Error: $error\n";
}

$decoded = json_decode($response, true);
if ($decoded) {
    echo "\nDecoded Response:\n";
    print_r($decoded);
}
