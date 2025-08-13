<?php
/**
 * Test Purchase Order Creation API
 * This file demonstrates the complete purchase order creation with all 5 sections
 */

// Test data for creating a purchase order
$purchaseOrderData = [
    // 1. Vendor Information
    'supplier_id' => null, // Optional - can be null if creating new vendor
    'vendor_name' => 'ABC Electronics Ltd.',
    'vendor_contact' => 'John Doe - +1-555-0123',
    
    // 2. Order Details
    'order_date' => '2025-08-13',
    'expected_date' => '2025-08-20',
    
    // 3. Line Items
    'items' => [
        [
            'part_id' => null, // Optional - can be null if part doesn't exist in inventory
            'part_number' => 'ELEC-001',
            'description' => 'High-quality HDMI cables, 2m length',
            'qty' => 50,
            'unit_cost' => 12.50
        ],
        [
            'part_id' => null,
            'part_number' => 'ELEC-002',
            'description' => 'USB-C to HDMI adapters',
            'qty' => 25,
            'unit_cost' => 18.75
        ],
        [
            'part_id' => null,
            'part_number' => 'ELEC-003',
            'description' => 'Wireless presentation remotes',
            'qty' => 10,
            'unit_cost' => 45.00
        ]
    ],
    
    // 4. Order Summary (Tax calculation)
    'tax_rate' => 5.0, // 5% tax rate
    
    // 5. Additional Information
    'terms' => 'Net 30 days. Early payment discount: 2% if paid within 10 days.',
    'notes' => 'Please ensure all items are properly packaged. Contact us for any delivery issues.'
];

// Calculate expected totals for verification
$subtotal = 0;
foreach ($purchaseOrderData['items'] as $item) {
    $subtotal += $item['qty'] * $item['unit_cost'];
}
$taxAmount = ($subtotal * $purchaseOrderData['tax_rate']) / 100;
$total = $subtotal + $taxAmount;

echo "=== Purchase Order Creation Test ===\n";
echo "Expected Calculations:\n";
echo "Subtotal: $" . number_format($subtotal, 2) . "\n";
echo "Tax (5%): $" . number_format($taxAmount, 2) . "\n";
echo "Total: $" . number_format($total, 2) . "\n\n";

// cURL command to test the API
$curlCommand = "curl -X POST http://localhost:8000/api/inventory/purchase-orders \\\n";
$curlCommand .= "  -H \"Content-Type: application/json\" \\\n";
$curlCommand .= "  -H \"Authorization: Bearer YOUR_TOKEN_HERE\" \\\n";
$curlCommand .= "  -d '" . json_encode($purchaseOrderData, JSON_PRETTY_PRINT) . "'";

echo "cURL Command:\n";
echo $curlCommand . "\n\n";

echo "=== API Response Structure ===\n";
echo "The API will return:\n";
echo "- Success status\n";
echo "- Purchase order data with:\n";
echo "  - PO number (auto-generated)\n";
echo "  - All vendor information\n";
echo "  - Order dates\n";
echo "  - Line items with calculated totals\n";
echo "  - Calculated subtotal, tax, and total\n";
echo "  - Terms and notes\n";
echo "  - Status: 'draft'\n";
echo "  - Created by user ID\n\n";

echo "=== Database Tables Updated ===\n";
echo "1. purchase_orders - Main purchase order record\n";
echo "2. purchase_order_items - Individual line items\n";
echo "3. Both tables include company_id for multi-tenancy\n\n";

echo "=== Validation Rules ===\n";
echo "✓ supplier_id: optional, must exist if provided\n";
echo "✓ vendor_name: required, max 255 characters\n";
echo "✓ vendor_contact: required, max 255 characters\n";
echo "✓ order_date: required, valid date\n";
echo "✓ expected_date: required, valid date\n";
echo "✓ items: required array, minimum 1 item\n";
echo "✓ part_id: optional, must exist if provided\n";
echo "✓ part_number: required, max 255 characters\n";
echo "✓ description: required\n";
echo "✓ qty: required, integer, minimum 1\n";
echo "✓ unit_cost: required, numeric, minimum 0\n";
echo "✓ tax_rate: optional, numeric, 0-100\n";
echo "✓ tax_amount: optional, numeric, minimum 0\n";
echo "✓ terms: optional\n";
echo "✓ notes: optional\n";
