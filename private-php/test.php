<?php
/**
 * Test script to verify the sample application structure
 */

echo "Testing MetaLocator REST API Sample Application\n";
echo str_repeat("=", 50) . "\n";

// Test 1: Check PHP version
echo "\n1. Checking PHP version...\n";
if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    echo "   ✗ FAIL: PHP 7.0 or higher required. Current: " . PHP_VERSION . "\n";
    exit(1);
}
echo "   ✓ PASS: PHP version " . PHP_VERSION . "\n";

// Test 2: Check cURL extension
echo "\n2. Checking cURL extension...\n";
if (!extension_loaded('curl')) {
    echo "   ✗ FAIL: cURL extension not found\n";
    exit(1);
}
echo "   ✓ PASS: cURL extension loaded\n";

// Test 3: Check required files
echo "\n3. Checking required files...\n";
$requiredFiles = [
    'config.example.php',
    'sample_locations.csv',
    'import.php',
    'README.md'
];

foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . 'test.php/' . $file)) {
        echo "   ✗ FAIL: Missing file: $file\n";
        exit(1);
    }
    echo "   ✓ PASS: Found $file\n";
}

// Test 4: Verify CSV structure
echo "\n4. Verifying CSV file structure...\n";
$csvFile = __DIR__ . '/sample_locations.csv';
$handle = fopen($csvFile, 'r');
$headers = fgetcsv($handle);
$expectedHeaders = ['Name', 'Description', 'Address', 'Address2', 'City', 'State', 'PostalCode', 'Phone', 'Country', 'Link', 'Email', 'category1', 'category2', 'category3', 'Monday Hours', 'Tuesday Hours', 'Wednesday Hours', 'Thursday Hours', 'Friday Hours', 'Saturday Hours', 'Sunday Hours', 'published'];

// Check if essential headers are present (not all need to be in exact order)
$missingHeaders = array_diff(['Name', 'Address', 'City', 'State', 'Monday Hours', 'category1'], $headers);
if (count($missingHeaders) > 0) {
    echo "   ✗ FAIL: CSV is missing essential headers\n";
    echo "   Missing: " . implode(', ', $missingHeaders) . "\n";
    exit(1);
}
echo "   ✓ PASS: CSV headers include essential fields\n";

// Count rows
$rowCount = 0;
while (($row = fgetcsv($handle)) !== false) {
    $rowCount++;
}
fclose($handle);
echo "   ✓ PASS: Found $rowCount location(s) in CSV\n";

// Test 5: Load configuration example
echo "\n5. Testing configuration loading...\n";
$configFile = __DIR__ . '/config.example.php';
$config = require $configFile;

$requiredKeys = ['api_key', 'api_base_url', 'rate_limit_delay', 'batch_size', 'csv_file', 'debug'];
foreach ($requiredKeys as $key) {
    if (!isset($config[$key])) {
        echo "   ✗ FAIL: Missing config key: $key\n";
        exit(1);
    }
}
echo "   ✓ PASS: Configuration structure is valid\n";

// Test 6: Check import.php syntax
echo "\n6. Checking import.php syntax...\n";
$output = [];
$returnCode = 0;
exec('php -l ' . __DIR__ . '/import.php 2>&1', $output, $returnCode);
if ($returnCode !== 0) {
    echo "   ✗ FAIL: Syntax error in import.php\n";
    echo "   " . implode("\n   ", $output) . "\n";
    exit(1);
}
echo "   ✓ PASS: import.php has no syntax errors\n";

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "All tests passed! ✓\n";
echo "\nThe sample application is ready to use.\n";
echo "Next steps:\n";
echo "  1. Copy config.example.php to config.php\n";
echo "  2. Edit config.php with your API credentials\n";
echo "  3. Run: php import.php\n";
echo "\n";
