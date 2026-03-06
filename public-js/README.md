# MetaLocator API Search Sample Application

A simple, self-contained HTML/JavaScript application that demonstrates how to use the MetaLocator `/search` API to retrieve and display location search results.

## Features

- ✅ Simple, self-contained application (no build process required)
- ✅ Clean and responsive user interface
- ✅ Rate limiting to prevent API quota exhaustion
- ✅ Configuration file for API credentials
- ✅ Error handling and user feedback
- ✅ JSONP support for cross-origin requests
- ✅ Search by postal code with radius filtering
- ✅ Display detailed location information
- ✅ Real-time rate limit monitoring

## Setup

1. **Copy the configuration file:**
   ```bash
   cp config.example.json config.json
   ```

2. **Edit `config.json` and add your credentials:**
   ```json
   {
     "apiKey": "your-actual-public-scoped-api-key-here",
     "itemId": "your-interface-id-here",
     "apiBaseUrl": "https://api.metalocator.com/api/v1",
     "rateLimitPerMinute": 60,
     "defaultRadius": 25,
     "defaultLimit": 10
   }
   ```

   - `apiKey`: Your MetaLocator API key, obtained from the MetaLocator dashboard under Profile > API Keys
   - `itemId`: Your MetaLocator Interface ID Number, obtained from the MetaLocator dashboard under Interfaces.
   - `apiBaseUrl`: The base URL for the MetaLocator API (usually `https://api.metalocator.com/api/v1`)
   - `rateLimitPerMinute`: Maximum number of requests per minute (default: 60)
   - `defaultRadius`: Default search radius in miles (default: 25)
   - `defaultLimit`: Default maximum number of results (default: 10)

3. **Open `index.html` in your web browser:**
   - You can simply double-click the file or
   - Serve it through a local web server (recommended):
     ```bash
     # Using Python 3
     python3 -m http.server 8000
     
     # Using PHP
     php -S localhost:8000
     
     # Then open http://localhost:8000 in your browser
     ```

## Usage

1. Enter a postal code in the search form (e.g., "80205" for Denver, CO)
2. Optionally adjust the search radius and result limit
3. Optionally add a keyword to filter results
4. Click "Search" to retrieve and display results
5. View the rate limit information to monitor your API usage

## Rate Limiting

The application implements client-side rate limiting to help prevent exceeding API quotas:

- Tracks requests within a rolling 1-minute window
- Displays current request count and remaining capacity
- Prevents new requests when limit is reached
- Shows wait time when rate limit is exceeded

**Note:** This is client-side rate limiting only. The actual API may have its own rate limits enforced server-side.

## Files

- **index.html** - Main HTML page with the user interface
- **app.js** - JavaScript application logic and API integration
- **styles.css** - Styling for the application
- **config.json** - Configuration file (not included in git, see config.example.json)
- **config.example.json** - Example configuration file template
- **README.md** - This file

## API Endpoint

The application calls the MetaLocator search API:

```
GET https://api.metalocator.com/api/v1/interfaces/{Itemid}/search
```

### Parameters

- `apikey` (required) - Your Public-scoped API key
- `Itemid` (required) - Your Item ID
- `postal_code` - Search postal code
- `radius` - Search radius in miles
- `limit` - Maximum number of results
- `keyword` - Optional keyword filter

## Browser Compatibility

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Any modern browser with JavaScript enabled

## Security Notes

- The API key is exposed in client-side code.  Ensure you are using a public-scoped key.
- This is a sample application for demonstration purposes

## Troubleshooting

### "Please configure your API key"
- Make sure you've updated the values in to `config.json`
- Verify that your API key and Item ID are correctly set in `config.json`

### "No results found"
- Try a different postal code
- Increase the search radius
- Verify that your API credentials have access to location data

### CORS errors
- Make sure your API key is configured with `public` scope.

### Rate limit exceeded
- Wait for the specified time before making more requests
- Consider increasing `rateLimitPerMinute` if your API plan allows

## API Documentation

For more information about the MetaLocator API, visit:
[https://metalocator.portal.swaggerhub.com/](https://metalocator.portal.swaggerhub.com/)

## Support

For issues with the MetaLocator API or service, contact MetaLocator support. 

## License

This sample application is provided as-is for demonstration purposes.
