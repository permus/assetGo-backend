<?php

require_once 'vendor/autoload.php';

use App\Http\Requests\Asset\TransferAssetRequest;
use Illuminate\Http\Request;

// Create a mock request
$request = Request::create('/api/assets/1/transfer', 'POST', [
    'new_location_id' => 5,
    'new_department_id' => 3,
    'to_user_id' => null,
    'transfer_reason' => 'Relocation',
    'transfer_date' => '2025-07-30',
    'notes' => 'test 1',
    'condition_report' => ''
]);

// Create the form request
$formRequest = TransferAssetRequest::createFromBase($request);

// Get the validation rules
$rules = $formRequest->rules();

echo "Current validation rules:\n";
print_r($rules);

echo "\n\nRequest data:\n";
print_r($request->all()); 