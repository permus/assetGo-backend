# Public Asset API Documentation

This document describes the public API endpoints for retrieving asset information in the AssetGo system. These endpoints are publicly accessible and do not require authentication.

## Overview

The public asset API provides read-only access to asset information, allowing external applications, websites, or mobile apps to display asset data without requiring user authentication.

## Endpoints

### 1. Get Public Assets List

Retrieves a list of public assets with filtering, search, and pagination capabilities.

**Endpoint:** `GET /api/assets/public`

**Query Parameters:**
- `company_id` (optional): Filter assets by company ID
- `company_slug` (optional): Filter assets by company slug
- `search` (optional): Search in asset name, serial number, description, tags, or category
- `category_id` (optional): Filter by asset category
- `type` (optional): Filter by asset type
- `status` (optional): Filter by asset status (defaults to 'active')
- `location_id` (optional): Filter by location
- `tag_id` (optional): Filter by tag
- `min_value` (optional): Filter by minimum purchase price
- `max_value` (optional): Filter by maximum purchase price
- `sort_by` (optional): Sort field (name, serial_number, purchase_price, created_at, updated_at) - default: created_at
- `sort_dir` (optional): Sort direction (asc, desc) - default: desc
- `per_page` (optional): Number of items per page (1-100) - default: 15
- `page` (optional): Page number - default: 1

**Example Request:**
```bash
GET /api/assets/public?company_slug=acme-corp&search=laptop&category_id=1&per_page=20
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "assets": [
      {
        "id": 34,
        "name": "Dell XPS 13 Laptop",
        "serial_number": "DLXPS001",
        "description": "High-performance laptop for development work",
        "model": "XPS 13 9310",
        "manufacturer": "Dell",
        "purchase_date": "2024-01-15",
        "purchase_price": 1299.99,
        "warranty": "3 years",
        "health_score": 95,
        "status": "active",
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z",
        "category": {
          "id": 1,
          "name": "Computers",
          "description": "Desktop and laptop computers"
        },
        "asset_type": {
          "id": 2,
          "name": "Laptop",
          "description": "Portable computers"
        },
        "asset_status": {
          "id": 1,
          "name": "Active",
          "color": "#28a745"
        },
        "location": {
          "id": 5,
          "name": "IT Department",
          "address": "Floor 3, Building A"
        },
        "department": {
          "id": 3,
          "name": "Information Technology"
        },
        "company": {
          "id": 1,
          "name": "Acme Corporation",
          "slug": "acme-corp"
        },
        "tags": [
          {
            "id": 1,
            "name": "Development",
            "color": "#007bff"
          },
          {
            "id": 2,
            "name": "High Priority",
            "color": "#dc3545"
          }
        ],
        "images": [
          {
            "id": 1,
            "url": "https://example.com/storage/assets/images/dell-xps-13.jpg",
            "alt": "Dell XPS 13 Laptop"
          }
        ]
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 100,
      "from": 1,
      "to": 20
    },
    "filters": {
      "company_slug": "acme-corp",
      "search": "laptop",
      "category_id": "1",
      "per_page": "20"
    }
  }
}
```

### 2. Get Public Asset Details

Retrieves detailed information about a specific asset.

**Endpoint:** `GET /api/assets/{asset}/public`

**Parameters:**
- `asset` (path parameter): The asset ID

**Example Request:**
```bash
GET /api/assets/34/public
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "asset": {
      "id": 34,
      "name": "Dell XPS 13 Laptop",
      "serial_number": "DLXPS001",
      "description": "High-performance laptop for development work",
      "model": "XPS 13 9310",
      "manufacturer": "Dell",
      "purchase_date": "2024-01-15",
      "purchase_price": 1299.99,
      "warranty": "3 years",
      "health_score": 95,
      "status": "active",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z",
      "qr_code_url": "https://example.com/storage/qr-codes/asset-34-qr.png",
      "category": {
        "id": 1,
        "name": "Computers",
        "description": "Desktop and laptop computers"
      },
      "asset_type": {
        "id": 2,
        "name": "Laptop",
        "description": "Portable computers"
      },
      "asset_status": {
        "id": 1,
        "name": "Active",
        "color": "#28a745"
      },
      "location": {
        "id": 5,
        "name": "IT Department",
        "address": "Floor 3, Building A",
        "type": {
          "id": 1,
          "name": "Office"
        }
      },
      "department": {
        "id": 3,
        "name": "Information Technology"
      },
      "company": {
        "id": 1,
        "name": "Acme Corporation",
        "slug": "acme-corp"
      },
      "tags": [
        {
          "id": 1,
          "name": "Development",
          "color": "#007bff"
        },
        {
          "id": 2,
          "name": "High Priority",
          "color": "#dc3545"
        }
      ],
      "images": [
        {
          "id": 1,
          "url": "https://example.com/storage/assets/images/dell-xps-13.jpg",
          "alt": "Dell XPS 13 Laptop"
        }
      ]
    }
  }
}
```

## Data Security

### What's Included in Public API
- Asset basic information (name, serial number, description)
- Asset specifications (model, manufacturer, purchase details)
- Asset status and health information
- Category, type, and status details
- Location and department information
- Company information (name and slug only)
- Tags and images
- QR code URL (for individual asset view)

### What's Excluded from Public API
- User assignment information
- Internal notes or comments
- Financial details beyond purchase price
- Maintenance schedules
- Activity history
- Archive/deletion reasons
- Internal company IDs or sensitive data

## Filtering Options

### Company Filtering
- Use `company_id` to filter by specific company ID
- Use `company_slug` to filter by company slug (more user-friendly)

### Search Functionality
The search parameter searches across:
- Asset name
- Serial number
- Description
- Tag names
- Category names

### Status Filtering
By default, only active assets are returned. You can filter by:
- `active` - Currently in use
- `maintenance` - Under maintenance
- `retired` - No longer in use

## Error Responses

### 404 Not Found
```json
{
  "success": false,
  "message": "Asset not found or not available"
}
```

### 422 Validation Error
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "per_page": ["The per page field must be between 1 and 100."],
    "sort_by": ["The selected sort by is invalid."]
  }
}
```

## Rate Limiting

Public API requests are rate-limited to prevent abuse:
- 120 requests per minute per IP address
- 2000 requests per hour per IP address

## CORS Support

The public API supports Cross-Origin Resource Sharing (CORS) for web applications:
- Allowed origins: Configured in your Laravel application
- Allowed methods: GET
- Allowed headers: Content-Type, Accept

## Usage Examples

### JavaScript/Fetch API
```javascript
// Get all assets for a company
fetch('/api/assets/public?company_slug=acme-corp')
  .then(response => response.json())
  .then(data => {
    console.log('Assets:', data.data.assets);
  });

// Get specific asset
fetch('/api/assets/34/public')
  .then(response => response.json())
  .then(data => {
    console.log('Asset:', data.data.asset);
  });
```

### cURL Examples
```bash
# Get assets with search and filtering
curl "https://assetgo.themeai.com/api/assets/public?search=laptop&category_id=1&per_page=10"

# Get specific asset
curl "https://assetgo.themeai.com/api/assets/34/public"
```

### PHP Example
```php
$url = 'https://assetgo.themeai.com/api/assets/public?company_slug=acme-corp';
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['data']['assets'] as $asset) {
        echo "Asset: " . $asset['name'] . "\n";
    }
}
```

## Best Practices

1. **Use Pagination**: Always implement pagination for large datasets
2. **Cache Responses**: Cache API responses when possible to reduce server load
3. **Handle Errors**: Implement proper error handling for failed requests
4. **Respect Rate Limits**: Implement exponential backoff for rate limit errors
5. **Use HTTPS**: Always use HTTPS for production API calls

## Integration Tips

1. **QR Code Integration**: Use the `qr_code_url` to display QR codes for asset identification
2. **Image Handling**: Display asset images using the provided URLs
3. **Search Implementation**: Implement client-side search using the search parameter
4. **Filtering**: Use the various filter parameters to create advanced search interfaces
5. **Real-time Updates**: Consider implementing polling for real-time asset status updates 