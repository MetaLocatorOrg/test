<?php
/**
 * MetaLocator REST API Sample Application - Data Operations
 * 
 * This sample application demonstrates how to perform basic CRUD operations
 * on records in MetaLocator using the REST API.
 * 
 * Features:
 * - Lists existing records (GET /api/v1/data)
 * - Creates a new record (POST /api/v1/data)
 * - Updates an existing record (PUT /api/v1/data/{id})
 * - Deletes a record (DELETE /api/v1/data/{id})
 * 
 * Requirements:
 * - PHP 7.0 or higher
 * - cURL extension enabled
 * 
 * Usage:
 * 1. Copy config.example.php to config.php
 * 2. Edit config.php with your API credentials
 * 3. Run: php data-operations.php
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
 * Send an API request and return the result
 */
function api_request($method, $url, $config, $data = null) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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
 * List records via GET /api/v1/data
 */
function list_records($config) {
    $url = $config['api_base_url'] . '/data';
    log_message("GET " . $url, $config);

    $result = api_request('GET', $url, $config);

    if ($result['success']) {
        echo "✓ Records retrieved successfully (HTTP " . $result['http_code'] . ")\n";
        $records = $result['response'];
        if (is_array($records)) {
            $count = isset($records['data']) ? count($records['data']) : count($records);
            echo "  Found $count record(s).\n";
        }
        if ($config['debug']) {
            echo "  Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "✗ Failed to retrieve records (HTTP " . $result['http_code'] . ")\n";
        echo "  Error: " . ($result['error'] ?? $result['raw_response']) . "\n";
    }

    return $result;
}

/**
 * Create a new record via POST /api/v1/data
 */
function create_record($config) {
    $url = $config['api_base_url'] . '/data';

    // Sample record data – adjust fields to match your MetaLocator installation
    $newRecord = [
        'Name'        => 'Sample Location',
        'Address'     => '123 Main Street',
        'City'        => 'Springfield',
        'State'       => 'IL',
        'PostalCode'  => '62701',
        'Country'     => 'US',
        'Phone'       => '555-555-5555',
        'published'   => '1',
    ];

    log_message("POST " . $url, $config);
    log_message("Payload: " . json_encode($newRecord), $config);

    $result = api_request('POST', $url, $config, $newRecord);

    if ($result['success']) {
        echo "✓ Record created successfully (HTTP " . $result['http_code'] . ")\n";
        if ($config['debug']) {
            echo "  Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "✗ Failed to create record (HTTP " . $result['http_code'] . ")\n";
        echo "  Error: " . ($result['error'] ?? $result['raw_response']) . "\n";
    }

    return $result;
}

/**
 * Update an existing record via PUT /api/v1/data/{id}
 */
function update_record($id, $config) {
    $url = $config['api_base_url'] . '/data/' . $id;

    $updatedFields = [
        'Name'    => 'Sample Location (Updated)',
        'Phone'   => '555-999-9999',
    ];

    log_message("PUT " . $url, $config);
    log_message("Payload: " . json_encode($updatedFields), $config);

    $result = api_request('PUT', $url, $config, $updatedFields);

    if ($result['success']) {
        echo "✓ Record $id updated successfully (HTTP " . $result['http_code'] . ")\n";
        if ($config['debug']) {
            echo "  Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "✗ Failed to update record $id (HTTP " . $result['http_code'] . ")\n";
        echo "  Error: " . ($result['error'] ?? $result['raw_response']) . "\n";
    }

    return $result;
}

/**
 * Delete a record via DELETE /api/v1/data/{id}
 */
function delete_record($id, $config) {
    $url = $config['api_base_url'] . '/data/' . $id;

    log_message("DELETE " . $url, $config);

    $result = api_request('DELETE', $url, $config);

    if ($result['success']) {
        echo "✓ Record $id deleted successfully (HTTP " . $result['http_code'] . ")\n";
        if ($config['debug']) {
            echo "  Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "✗ Failed to delete record $id (HTTP " . $result['http_code'] . ")\n";
        echo "  Error: " . ($result['error'] ?? $result['raw_response']) . "\n";
    }

    return $result;
}

/**
 * Display the main menu and return the user's choice
 */
function show_menu() {
    echo "\n";
    echo str_repeat("-", 40) . "\n";
    echo "MetaLocator Data Operations Menu\n";
    echo str_repeat("-", 40) . "\n";
    echo "1. List records (GET)\n";
    echo "2. Create a new record (POST)\n";
    echo "3. Update a record (PUT)\n";
    echo "4. Delete a record (DELETE)\n";
    echo "5. Run full demo (create → update → delete)\n";
    echo "0. Exit\n";
    echo str_repeat("-", 40) . "\n";
    echo "Enter your choice: ";

    return trim(fgets(STDIN));
}

// ─── Main execution ────────────────────────────────────────────────────────────

echo "\n";
echo "MetaLocator REST API Sample - Data Operations\n";
echo str_repeat("=", 50) . "\n";
echo "API Base URL: " . $config['api_base_url'] . "\n";

while (true) {
    $choice = show_menu();

    switch ($choice) {
        case '1':
            echo "\n--- List Records ---\n";
            list_records($config);
            break;

        case '2':
            echo "\n--- Create Record ---\n";
            create_record($config);
            break;

        case '3':
            echo "\nEnter the record ID to update: ";
            $id = trim(fgets(STDIN));
            if ($id === '') {
                echo "No ID provided. Cancelling.\n";
                break;
            }
            echo "\n--- Update Record $id ---\n";
            update_record($id, $config);
            break;

        case '4':
            echo "\nEnter the record ID to delete: ";
            $id = trim(fgets(STDIN));
            if ($id === '') {
                echo "No ID provided. Cancelling.\n";
                break;
            }
            echo "\n--- Delete Record $id ---\n";
            delete_record($id, $config);
            break;

        case '5':
            echo "\n--- Full Demo: Create → Update → Delete ---\n";

            // Step 1: Create
            echo "\n[Step 1] Creating record...\n";
            $createResult = create_record($config);

            // Extract the new record's ID from the response
            $newId = null;
            if ($createResult['success'] && isset($createResult['response'])) {
                $resp = $createResult['response'];
                // Common response shapes: {id: …} or {data: {id: …}} or [{id: …}]
                if (isset($resp['id'])) {
                    $newId = $resp['id'];
                } elseif (isset($resp['data']['id'])) {
                    $newId = $resp['data']['id'];
                } elseif (is_array($resp) && isset($resp[0]['id'])) {
                    $newId = $resp[0]['id'];
                }
            }

            if ($newId === null) {
                echo "  Could not determine new record ID from response. Stopping demo.\n";
                break;
            }

            echo "  New record ID: $newId\n";

            // Step 2: Update
            echo "\n[Step 2] Updating record $newId...\n";
            update_record($newId, $config);

            // Step 3: Delete
            echo "\n[Step 3] Deleting record $newId...\n";
            delete_record($newId, $config);

            echo "\n--- Demo complete ---\n";
            break;

        case '0':
            echo "\nGoodbye!\n\n";
            exit(0);

        default:
            echo "Invalid choice. Please enter a number from the menu.\n";
            break;
    }
}
