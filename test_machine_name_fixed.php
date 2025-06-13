<?php
// Test script to verify machine name field handling (FIXED VERSION)

echo "Testing Machine Name Field Handling (Fixed)...\n\n";

// Helper function to get machine name (same logic as API)
function getMachineName($data) {
    return !empty($data['machine_name_sales']) ? $data['machine_name_sales'] : 
           (!empty($data['machine_name_promotion']) ? $data['machine_name_promotion'] : 
           (!empty($data['machine_name']) ? $data['machine_name'] : ''));
}

// Test 1: Simulate form data for machine_sales QR type
$test_data = [
    'qr_type' => 'machine_sales',
    'machine_name_sales' => 'Test Sales Machine',
    'machine_name_promotion' => '',
    'machine_name' => '',
    'location' => 'Test Location'
];

echo "Test 1: machine_sales with machine_name_sales field\n";
$machine_name = getMachineName($test_data);
echo "Extracted machine name: '$machine_name'\n";
echo "Validation result: " . (empty($machine_name) ? "FAIL - Machine name is required" : "PASS - Machine name found") . "\n\n";

// Test 2: Simulate form data for machine_sales QR type with promotion field
$test_data2 = [
    'qr_type' => 'machine_sales',
    'machine_name_sales' => '',
    'machine_name_promotion' => 'Test Promotion Machine',
    'machine_name' => '',
    'location' => 'Test Location'
];

echo "Test 2: machine_sales with machine_name_promotion field\n";
$machine_name2 = getMachineName($test_data2);
echo "Extracted machine name: '$machine_name2'\n";
echo "Validation result: " . (empty($machine_name2) ? "FAIL - Machine name is required" : "PASS - Machine name found") . "\n\n";

// Test 3: Simulate form data for dynamic_vending QR type
$test_data3 = [
    'qr_type' => 'dynamic_vending',
    'machine_name' => 'Test Vending Machine',
    'campaign_id' => '123',
    'location' => 'Test Location'
];

echo "Test 3: dynamic_vending with machine_name field\n";
$machine_name3 = getMachineName($test_data3);
echo "Extracted machine name: '$machine_name3'\n";
echo "Validation result: " . (empty($machine_name3) ? "FAIL - Machine name is required" : "PASS - Machine name found") . "\n\n";

// Test 4: Simulate empty machine name
$test_data4 = [
    'qr_type' => 'machine_sales',
    'machine_name_sales' => '',
    'machine_name_promotion' => '',
    'machine_name' => '',
    'location' => 'Test Location'
];

echo "Test 4: machine_sales with empty machine name fields\n";
$machine_name4 = getMachineName($test_data4);
echo "Extracted machine name: '$machine_name4'\n";
echo "Validation result: " . (empty($machine_name4) ? "FAIL - Machine name is required (EXPECTED)" : "PASS - Machine name found") . "\n\n";

echo "All tests completed!\n"; 