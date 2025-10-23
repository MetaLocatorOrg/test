<?php
/**
 * MetaLocator REST API Sample Application - Configuration
 * 
 * Copy this file to config.php and fill in your API credentials
 */

return [
    // Your MetaLocator API Key (get this from your MetaLocator account)
    'api_key' => 'YOUR_API_KEY_HERE',
    
    // MetaLocator API Base URL
    'api_base_url' => 'https://metalocator.local/api',
    
    // Rate limiting settings (requests per second)
    'rate_limit_delay' => 1.0, // Delay in seconds between requests (1.0 = 1 request per second)
    
    // Batch size for bulk imports (max 200 per the API)
    'batch_size' => 50,
    
    // CSV file path (relative to this script)
    'csv_file' => 'sample_locations.csv',
    
    // Debug mode (set to true to see detailed output)
    'debug' => true,
];
