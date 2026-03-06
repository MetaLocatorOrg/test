# MetaLocator API Sample Applications

This repository contains sample applications that demonstrate how to integrate with the [MetaLocator](https://metalocator.com) REST API. MetaLocator is a location management and store locator platform. These examples are intended to help developers quickly get started with common API operations such as searching for locations and importing location data.

## Sample Applications

### [`public-js`](./public-js) — JavaScript Search App

A self-contained HTML/JavaScript application that demonstrates how to use the MetaLocator `/search` API to find and display locations. This app runs entirely in the browser with no build step required and is suitable for use with a public-scoped API key.

**Key features:**
- Search for locations by postal code, radius, keyword, and state
- Displays location details including address, phone, email, and website
- Contact a location via a lead form or submit a review directly from the results
- Client-side rate limiting to help prevent API quota exhaustion
- Simple configuration via `config.json`

**Requirements:** A modern web browser and a MetaLocator public-scoped API key.

See [`public-js/README.md`](./public-js/README.md) for setup and usage instructions.

---

### [`private-php`](./private-php) — PHP Bulk Import App

A PHP command-line application that demonstrates how to import location data into MetaLocator in bulk using the REST API. It reads location records from a CSV file and sends them to the MetaLocator `/api/data/bulk` endpoint in configurable batches.

**Key features:**
- Reads location data from a CSV file (sample data included)
- Batch processing with configurable batch size (up to 200 records per request)
- Configurable rate limiting between batches
- Detailed progress logging and import summary statistics
- Simple configuration via `config.php`

**Requirements:** PHP 7.0 or higher with the cURL extension and a MetaLocator private-scoped API key.

See [`private-php/README.md`](./private-php/README.md) for setup and usage instructions.

---

## Getting Started

1. Clone this repository.
2. Choose the sample application that fits your use case.
3. Copy the example configuration file (e.g., `config.example.json` or `config.example.php`) to the active config file and add your MetaLocator API credentials.
4. Follow the instructions in the respective `README.md` for each application.

## API Documentation

Full MetaLocator API documentation is available at [https://metalocator.portal.swaggerhub.com/](https://metalocator.portal.swaggerhub.com/).

## License

These sample applications are provided as-is for demonstration and educational purposes.
