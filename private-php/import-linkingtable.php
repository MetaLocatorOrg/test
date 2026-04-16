<?php
/**
 * MetaLocator REST API Sample Application - Linking Table Import
 * 
 * This sample application demonstrates how to import a linking table
 * from a CSV file into MetaLocator using the REST API. A linking table
 * associates locations with products using a two-column CSV file.
 * 
 * Before importing, the script validates that the target account has:
 * - A products table with a column matching the configured product column (e.g. SKU)
 * - A locations table with a column matching the configured location column (e.g. storeno),
 *   configured as an external key
 * 
 * Features:
 * - Validates account field configuration via the /fields API
 * - Reads linking data from a simple two-column CSV file
 * - Imports via /data/bulk with mode=add and import_type=linkingtable
 * - Respects rate limits with configurable delays between requests
 * - Handles errors gracefully
 * - Provides detailed logging and progress updates
 * 
 * Requirements:
 * - PHP 7.0 or higher
 * - cURL extension enabled
 * 
 * Usage:
 * 1. Copy config.example.php to config.php
 * 2. Edit config.php with your API credentials
 * 3. Prepare sample_linkingtable.csv with your linking data
 * 4. Run: php import-linkingtable.php
 */

// Check for required PHP version
if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    die("Error: This script requires PHP 7.0 or higher. Current version: " . PHP_VERSION . "\n");
}

// Check for cURL extension
if (!extension_loaded('curl')) {
    die("Error: The cURL extension is required but not installed.\n");
}

// Load configuration
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die("Error: Configuration file not found. Please copy config.example.php to config.php and configure it.\n");
}
$config = require $configFile;

// Validate configuration
if (empty($config['api_key']) || $config['api_key'] === 'YOUR_API_KEY_HERE') {
    die("Error: Please configure your API key in config.php\n");
}

// Set defaults for linking table config
if (!isset($config['linkingtable_csv_file'])) {
    $config['linkingtable_csv_file'] = 'sample_linkingtable.csv';
}
if (!isset($config['linkingtable_location_column'])) {
    $config['linkingtable_location_column'] = 'storeno';
}
if (!isset($config['linkingtable_product_column'])) {
    $config['linkingtable_product_column'] = 'SKU';
}

/**
 * Log a message to console with timestamp
 */
function log_message($message, $config) {
    if ($config['debug']) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

/**
 * Send an API request and return the result
 */
function api_request($method, $url, $config, $data = null) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    // don't use these in production, but you already knew that 😀
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = [
        'Accept: application/json',
        'x-api-key: ' . $config['api_key'],
    ];

    if ($data !== null) {
        $jsonData = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($jsonData);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $curlError,
            'http_code' => $httpCode,
        ];
    }

    $responseData = json_decode($response, true);

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $responseData,
        'raw_response' => $response,
    ];
}

/**
 * Fetch fields from the /fields API endpoint
 */
function fetch_fields($config, $mltable) {
    $url = $config['api_base_url'] . '/fields?mltable=' . $mltable;
    log_message("Fetching field configuration from: " . $url, $config);

    $result = api_request('GET', $url, $config);

    if (!$result['success']) {
        return [
            'success' => false,
            'error' => 'Failed to fetch fields (HTTP ' . $result['http_code'] . '): '
                . ($result['error'] ?? $result['raw_response']),
        ];
    }

    return [
        'success' => true,
        'fields' => $result['response']['results'],
    ];
}

/**
 * Validate that the account has the required field configuration for linking table import.
 * 
 * Checks:
 * 1. Products table has a column matching the configured product column (e.g. SKU)
 * 2. Locations table has a column matching the configured location column (e.g. storeno),
 *    configured as an external key
 * 
 * Returns an array with 'valid' (bool) and 'errors' (array of strings)
 */
function validate_fields($fields, $config) {
    $errors = [];
    $locationColumn = $config['linkingtable_location_column'];
    $productColumn = $config['linkingtable_product_column'];

    $foundProductColumn = false;
    $foundLocationColumn = false;
    $locationColumnIsExternalKey = false;

    if (!is_array($fields)) {
        return [
            'valid' => false,
            'errors' => ['Unexpected response format from /fields API.'],
        ];
    }

    foreach ($fields as $field) {
        $fieldName = isset($field['name']) ? $field['name'] : '';
        $tableName = isset($field['mltable']) ? $field['mltable'] : '';
        $externalKey = ($field['type'] == 'externalkey');

        // Check for the product column in the products table
        if ($tableName === 'products' && strcasecmp($fieldName, $productColumn) === 0) {
            $foundProductColumn = true;
        }

        // Check for the location column in the locations table
        if ($tableName === 'locations' && strcasecmp($fieldName, $locationColumn) === 0) {
            $foundLocationColumn = true;
            if ($externalKey) {
                $locationColumnIsExternalKey = true;
            }
        }
    }

    if (!$foundProductColumn) {
        $errors[] = "Products table does not have a '$productColumn' column. "
            . "Please add a '$productColumn' field to your products table.";
    }

    if (!$foundLocationColumn) {
        $errors[] = "Locations table does not have a '$locationColumn' column. "
            . "Please add a '$locationColumn' field to your locations table.";
    } elseif (!$locationColumnIsExternalKey) {
        $errors[] = "The '$locationColumn' column in the locations table is not configured as an external key. "
            . "Please configure '$locationColumn' as an external key in your locations table settings.";
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors,
    ];
}

/**
 * Read CSV file and return array of rows
 */
function read_csv_file($filename, $config) {
    $locationColumn = $config['linkingtable_location_column'];
    $productColumn = $config['linkingtable_product_column'];

    if (!file_exists($filename)) {
        die("Error: CSV file not found: $filename\n");
    }

    $rows = [];
    $handle = fopen($filename, 'r');

    if ($handle === false) {
        die("Error: Could not open CSV file: $filename\n");
    }

    // Read header row
    $headers = fgetcsv($handle);

    if ($headers === false) {
        die("Error: CSV file is empty or invalid\n");
    }

    // Validate that expected columns are present
    $headerMap = array_flip($headers);
    if (!isset($headerMap[$locationColumn])) {
        die("Error: CSV file is missing the '$locationColumn' column. "
            . "Found columns: " . implode(', ', $headers) . "\n");
    }
    if (!isset($headerMap[$productColumn])) {
        die("Error: CSV file is missing the '$productColumn' column. "
            . "Found columns: " . implode(', ', $headers) . "\n");
    }

    // Read data rows
    $rowNum = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;

        // Skip empty rows
        if (count(array_filter($row)) === 0) {
            continue;
        }

        // Combine headers with values
        if (count($headers) !== count($row)) {
            echo "Warning: Row $rowNum has mismatched column count. Skipping.\n";
            continue;
        }

        $record = array_combine($headers, $row);
        $rows[] = $record;
    }

    fclose($handle);
    return $rows;
}

/**
 * Send linking table data to MetaLocator API using the bulk import endpoint
 * with mode=add and import_type=linkingtable
 */
function send_linkingtable_to_api($rows, $config) {
    $query = http_build_query([
        'mode' => 'add',
        'import_type' => 'linkingtable',
    ]);
    $url = $config['api_base_url'] . '/data/bulk?' . $query;

    // Prepare JSON payload (array of linking records)
    $jsonData = json_encode($rows);

    // Initialize cURL
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // don't use these in production, but you already knew that 😀
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData),
        'x-api-key: ' . $config['api_key'],
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    // Check for cURL errors
    if ($curlError) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $curlError,
            'http_code' => $httpCode,
        ];
    }

    // Parse response
    $responseData = json_decode($response, true);

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $responseData,
        'raw_response' => $response,
    ];
}

/**
 * Apply rate limiting delay
 */
function apply_rate_limit($config) {
    if ($config['rate_limit_delay'] > 0) {
        usleep((int)($config['rate_limit_delay'] * 1000000));
    }
}

/**
 * Display summary statistics
 */
function display_summary($stats) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Linking Table Import Summary\n";
    echo str_repeat("=", 50) . "\n";
    echo "Total Rows: " . $stats['total'] . "\n";
    echo "Successful: " . $stats['success'] . "\n";
    echo "Failed: " . $stats['failed'] . "\n";
    echo "Success Rate: " . ($stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 2) : 0) . "%\n";
    echo str_repeat("=", 50) . "\n";
}

// Main execution
echo "\n";
echo "MetaLocator REST API - Linking Table Import\n";
echo str_repeat("=", 50) . "\n\n";

$locationColumn = $config['linkingtable_location_column'];
$productColumn = $config['linkingtable_product_column'];

log_message("Starting linking table import process...", $config);
log_message("Location column: $locationColumn", $config);
log_message("Product column: $productColumn", $config);

// Step 1: Validate field configuration via /fields API
echo "Step 1: Validating account field configuration...\n";
$locationsFieldsResult = fetch_fields($config,'locations');

if (!$locationsFieldsResult['success']) {
    die("Error: " . $locationsFieldsResult['error'] . "\n");
}

$productsFieldsResult = fetch_fields($config,'products');

if (!$productsFieldsResult['success']) {
    die("Error: " . $productsFieldsResult['error'] . "\n");
}

$fields = array_merge($locationsFieldsResult['fields'], $productsFieldsResult['fields']);
$validation = validate_fields($fields, $config);

if (!$validation['valid']) {
    echo "\n✗ Field validation failed:\n";
    foreach ($validation['errors'] as $error) {
        echo "  - $error\n";
    }
    die("\nPlease fix the above issues in your MetaLocator account before running this import.\n");
}

echo "✓ Field validation passed:\n";
echo "  - Products table has '$productColumn' column\n";
echo "  - Locations table has '$locationColumn' column (external key)\n\n";

// Step 2: Read CSV file
echo "Step 2: Reading CSV file...\n";
$csvPath = __DIR__ . '/' . $config['linkingtable_csv_file'];
log_message("Reading CSV file: " . $csvPath, $config);
$rows = read_csv_file($csvPath, $config);
log_message("Found " . count($rows) . " linking record(s) to import", $config);

if (count($rows) === 0) {
    die("Error: No data rows found in CSV file.\n");
}

// Initialize statistics
$stats = [
    'total' => count($rows),
    'success' => 0,
    'failed' => 0,
];

// Step 3: Import linking table data
echo "\nStep 3: Importing linking table data...\n";

// Get batch size (max 200 per API specification)
$batchSize = min($config['batch_size'], 200);
$batches = array_chunk($rows, $batchSize);
$totalBatches = count($batches);

log_message("Splitting into " . $totalBatches . " batch(es) of up to " . $batchSize . " records each", $config);

// Import rows in batches
foreach ($batches as $batchIndex => $batch) {
    $batchNum = $batchIndex + 1;
    $batchStart = $batchIndex * $batchSize + 1;
    $batchEnd = min($batchStart + count($batch) - 1, $stats['total']);

    log_message("[$batchNum/$totalBatches] Processing batch (records $batchStart-$batchEnd)...", $config);

    // Send batch to API
    $result = send_linkingtable_to_api($batch, $config);

    if ($result['success']) {
        // Process response to get success/failure counts
        if (isset($result['response']['results'])) {
            $results = $result['response']['results'];
            $batchSuccess = count($results);
            $stats['success'] += $batchSuccess;

            log_message("[$batchNum/$totalBatches] ✓ Success: $batchSuccess record(s) imported", $config);

            if ($config['debug'] && isset($result['response'])) {
                log_message("  Response: " . json_encode($result['response']), $config);
            }

            // Display any warnings from the log
            if (isset($result['response']['log']) && !empty($result['response']['log'])) {
                foreach ($result['response']['log'] as $logCategory => $logEntries) {
                    foreach ($logEntries as $logEntry) {
                        echo "  Warning: " . ($logEntry['message'] ?? 'Unknown warning') . "\n";
                        if (isset($logEntry['lineNumber'])) {
                            echo "    Line: " . $logEntry['lineNumber'] . "\n";
                        }
                    }
                }
            }
        } else {
            // Assume all succeeded if no detailed results
            $stats['success'] += count($batch);
            log_message("[$batchNum/$totalBatches] ✓ Success: Batch imported", $config);
        }
    } else {
        $stats['failed'] += count($batch);
        echo "[$batchNum/$totalBatches] ✗ Failed: Batch import failed\n";
        echo "  HTTP Code: " . $result['http_code'] . "\n";
        echo "  Error: " . ($result['error'] ?? 'Unknown error') . "\n";

        if (isset($result['raw_response'])) {
            echo "  Response: " . substr($result['raw_response'], 0, 200) . "...\n";
        }
    }

    // Apply rate limiting between batches (except for last batch)
    if ($batchNum < $totalBatches) {
        log_message("  Waiting " . $config['rate_limit_delay'] . " seconds (rate limit)...", $config);
        apply_rate_limit($config);
    }
}

// Display summary
display_summary($stats);

log_message("Linking table import process completed!", $config);
echo "\n";
