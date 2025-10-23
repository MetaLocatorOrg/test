<?php
/**
 * MetaLocator REST API Sample Application
 * 
 * This sample application demonstrates how to import location data
 * from a CSV file into MetaLocator using the REST API.
 * 
 * Features:
 * - Reads location data from a CSV file
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
 * 3. Optionally modify sample_locations.csv with your location data
 * 4. Run: php import.php
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

/**
 * Log a message to console with timestamp
 */
function log_message($message, $config) {
    if ($config['debug']) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

/**
 * Read CSV file and return array of locations
 */
function read_csv_file($filename) {
    if (!file_exists($filename)) {
        die("Error: CSV file not found: $filename\n");
    }
    
    $locations = [];
    $handle = fopen($filename, 'r');
    
    if ($handle === false) {
        die("Error: Could not open CSV file: $filename\n");
    }
    
    // Read header row
    $headers = fgetcsv($handle);
    
    if ($headers === false) {
        die("Error: CSV file is empty or invalid\n");
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
        
        $location = array_combine($headers, $row);
        $locations[] = $location;
    }
    
    fclose($handle);
    return $locations;
}

/**
 * Send locations to MetaLocator API using the bulk import endpoint
 * The API supports up to 200 records per request
 */
function send_locations_to_api($locations, $config) {
    $url = $config['api_base_url'] . '/data/bulk';
    
    // Prepare JSON payload (array of locations)
    $jsonData = json_encode($locations);
    
    // Initialize cURL
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData),
        'X-API-Key: ' . $config['api_key']
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
            'http_code' => $httpCode
        ];
    }
    
    // Parse response
    $responseData = json_decode($response, true);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $responseData,
        'raw_response' => $response
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
    echo "Import Summary\n";
    echo str_repeat("=", 50) . "\n";
    echo "Total Locations: " . $stats['total'] . "\n";
    echo "Successful: " . $stats['success'] . "\n";
    echo "Failed: " . $stats['failed'] . "\n";
    echo "Success Rate: " . ($stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 2) : 0) . "%\n";
    echo str_repeat("=", 50) . "\n";
}

// Main execution
echo "\n";
echo "MetaLocator REST API Sample Import Application\n";
echo str_repeat("=", 50) . "\n\n";

log_message("Starting import process...", $config);

// Read CSV file
$csvPath = __DIR__ . '/' . $config['csv_file'];
log_message("Reading CSV file: " . $csvPath, $config);
$locations = read_csv_file($csvPath);
log_message("Found " . count($locations) . " locations to import", $config);

// Initialize statistics
$stats = [
    'total' => count($locations),
    'success' => 0,
    'failed' => 0
];

// Get batch size (max 200 per API specification)
$batchSize = min($config['batch_size'], 200);
$batches = array_chunk($locations, $batchSize);
$totalBatches = count($batches);

log_message("Splitting into " . $totalBatches . " batch(es) of up to " . $batchSize . " records each", $config);

// Import locations in batches
foreach ($batches as $batchIndex => $batch) {
    $batchNum = $batchIndex + 1;
    $batchStart = $batchIndex * $batchSize + 1;
    $batchEnd = min($batchStart + count($batch) - 1, $stats['total']);
    
    log_message("[$batchNum/$totalBatches] Processing batch (records $batchStart-$batchEnd)...", $config);
    
    // Send batch to API
    $result = send_locations_to_api($batch, $config);
    
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

log_message("Import process completed!", $config);
echo "\n";
