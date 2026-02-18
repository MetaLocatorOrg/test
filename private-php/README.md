# MetaLocator REST API Sample Application

This sample application demonstrates how to import location data into MetaLocator using the REST API. It's designed to be simple, self-contained, and easy to understand for developers getting started with the MetaLocator API.

## Features

- ✅ **Self-contained**: All code in a single directory
- ✅ **CSV-based**: Uses a simple CSV file as the data source
- ✅ **Rate limiting**: Respects API rate limits with configurable delays
- ✅ **Error handling**: Gracefully handles errors with detailed logging
- ✅ **Progress tracking**: Shows real-time progress and summary statistics
- ✅ **Configurable**: Easy configuration through a simple PHP config file

## Requirements

- PHP 7.0 or higher
- cURL extension enabled (usually included with PHP)
- A MetaLocator account with API access

## Quick Start

### 1. Get Your API Credentials

You'll need:
- Your MetaLocator **API Key** (from your MetaLocator account settings).  The key should be a **private** scoped key.

### 2. Configure the Application

Copy the example configuration file and edit it with your credentials:

```bash
cp config.example.php config.php
```

Edit `config.php` and replace:
- `YOUR_API_KEY_HERE` with your actual API key

### 3. Prepare Your Data

The application includes a sample CSV file (`sample_locations.csv`) with example location data. You can:

- Use the sample data as-is for testing
- Edit `sample_locations.csv` to add your own locations
- Create your own CSV file and update the `csv_file` setting in `config.php`

**CSV Format:**

The CSV file should have these columns:
- `Name` - Location name (required)
- `Description` - Location description (supports HTML formatting)
- `Address` - Street address
- `Address2` - Suite, building, etc.
- `City` - City
- `State` - State/Province
- `PostalCode` - ZIP/Postal code
- `Phone` - Phone number
- `Country` - Country
- `Link` - Website URL
- `Email` - Email address
- `category1`, `category2`, `category3` - Category fields for classification
- `Monday Hours`, `Tuesday Hours`, etc. - Operating hours for each day (format: HH:MM-HH:MM, or "C" for closed)
- `published` - Set to "1" to publish, "0" to keep as draft

Example:
```csv
Name,Description,Address,Address2,City,State,PostalCode,Phone,Country,Link,Email,category1,category2,Monday Hours,Tuesday Hours,Wednesday Hours,Thursday Hours,Friday Hours,Saturday Hours,Sunday Hours,published
"Sample Location 1","<p>Description with HTML</p>","123 Main Street","Suite 100","Denver","Colorado","80202","303-555-0101","United States","http://www.example.com","info@example.com","Retail","Sales","09:00-17:00","09:00-17:00","09:00-17:00","09:00-17:00","09:00-17:00","10:00-15:00","C","1"
```

### 4. Run the Import

Execute the import script:

```bash
php import.php
```

You should see output like:
```
MetaLocator REST API Sample Import Application
==================================================

[2024-01-15 10:30:00] Starting import process...
[2024-01-15 10:30:00] Reading CSV file: /path/to/sample_locations.csv
[2024-01-15 10:30:00] Found 5 locations to import
[2024-01-15 10:30:00] Splitting into 1 batch(es) of up to 50 records each
[2024-01-15 10:30:01] [1/1] Processing batch (records 1-5)...
[2024-01-15 10:30:01] [1/1] ✓ Success: 5 record(s) imported
==================================================
Import Summary
==================================================
Total Locations: 5
Successful: 5
Failed: 0
Success Rate: 100%
==================================================
```

## Configuration Options

Edit `config.php` to customize these settings:

### API Settings

```php
'api_key' => 'YOUR_API_KEY_HERE',       // Your MetaLocator API Key
'api_base_url' => 'https://metalocator.local/api',  // MetaLocator API Base URL
```

### Batch Processing

Control how many records are sent per batch (max 200):

```php
'batch_size' => 50,  // Number of records per batch (max 200 per API spec)
```

### Rate Limiting

To respect API rate limits, adjust the delay between batch requests:

```php
'rate_limit_delay' => 1.0,  // Delay in seconds between batches
```

### Data Source

Change the CSV file path:

```php
'csv_file' => 'sample_locations.csv',  // Path relative to this directory
```

### Debug Mode

Enable/disable detailed logging:

```php
'debug' => true,  // Set to false for minimal output
```

## API Endpoints Used

This sample application uses the MetaLocator REST API's **bulk import** endpoint:

- **Endpoint**: `/api/data/bulk`
- **Method**: POST
- **Content-Type**: application/json
- **Authentication**: X-API-Key header

The bulk import endpoint:
- Accepts up to 200 records per request
- Creates or updates locations based on the data provided
- Returns detailed results for each record including any warnings or errors

## Troubleshooting

### "Configuration file not found"
Make sure you've copied `config.example.php` to `config.php`.

### "Please configure your API key"
Edit `config.php` and replace `YOUR_API_KEY_HERE` with your actual API key.

### "CSV file not found"
Make sure `sample_locations.csv` exists or update the `csv_file` setting in `config.php`.

### "cURL Error"
Check your internet connection and firewall settings. The application needs to access the MetaLocator API endpoint configured in your config file.

### Authentication Errors (401/403)
Verify that your API key is correct in `config.php`. The API uses X-API-Key header authentication.

### Rate Limit Errors (429)
Increase the `rate_limit_delay` value in `config.php` to slow down batch requests.

## Advanced Usage

### Adjusting Batch Size

The bulk import API supports up to 200 records per request. You can adjust the batch size in `config.php`:

```php
'batch_size' => 100,  // Increase for faster imports, decrease if experiencing issues
```

### Adding Custom Fields

To include custom fields in your import:

1. Add the field columns to your CSV file
2. The script will automatically include them in the API request

### Large Datasets

The application automatically handles batch processing. For very large datasets:

1. Increase `batch_size` up to 200 for faster imports
2. Adjust `rate_limit_delay` to balance speed and API limits
3. Monitor the import logs for any warnings or errors

## Files in This Package

- `import.php` - Main import script with bulk API support
- `config.example.php` - Example configuration file
- `sample_locations.csv` - Sample data file with Hours and Category fields
- `README.md` - This documentation file
- `test.php` - Verification script

## API Reference

For complete API documentation, see the OpenAPI specification at:
- `html/ops/api/metalocator.yml`

The bulk import endpoint (`/api/data/bulk`) is defined starting at line 1025.

## Support

For more information about the MetaLocator API:
- Visit the [MetaLocator Support Center](https://support.metalocator.com)
- Review the API specification in `html/ops/api/metalocator.yml`
- Contact MetaLocator Support for assistance

## License

This sample application is provided as-is for educational purposes.
