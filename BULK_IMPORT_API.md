# Bulk Import API Documentation

## Overview

The Bulk Import API allows you to import multiple assets at once using a JSON payload. This API automatically creates or finds related entities (categories, locations, departments, asset types, and statuses) and generates unique asset IDs.

## Endpoint

```
POST /api/assets/import-bulk-json
```

## Authentication

- **Required**: Bearer token authentication via Laravel Sanctum
- **Authorization**: Only authenticated users can import assets
- **Company Scope**: All imported assets will be associated with the user's company

## Request Body

### Required Fields

| Field | Type | Description | Validation |
|-------|------|-------------|------------|
| `assets` | array | Array of asset objects | Required, minimum 1 asset |
| `assets[].name` | string | Asset name | Required, max 100 characters |

### Optional Fields

| Field | Type | Description | Validation |
|-------|------|-------------|------------|
| `assets[].description` | string | Asset description | Optional, max 500 characters |
| `assets[].category` | string | Asset category | Optional, max 255 characters |
| `assets[].type` | string | Asset type | Optional, max 255 characters |
| `assets[].serial_number` | string | Serial number | Optional, max 255 characters, unique per company |
| `assets[].model` | string | Asset model | Optional, max 255 characters |
| `assets[].manufacturer` | string | Manufacturer | Optional, max 255 characters |
| `assets[].purchase_date` | date | Purchase date | Optional, valid date format, not in future |
| `assets[].purchase_price` | numeric | Purchase price | Optional, numeric, minimum 0 |
| `assets[].depreciation` | numeric | Depreciation value | Optional, numeric |
| `assets[].location` | string | Location name | Optional, max 255 characters |
| `assets[].department` | string | Department name | Optional, max 255 characters |
| `assets[].warranty` | string | Warranty information | Optional, max 255 characters |
| `assets[].insurance` | string | Insurance information | Optional, max 255 characters |
| `assets[].health_score` | numeric | Health score | Optional, numeric, 0-100 |
| `assets[].status` | string | Asset status | Optional, max 50 characters |
| `assets[].tags` | array | Array of tag names | Optional, array of strings |

## Request Example

```json
{
  "assets": [
    {
      "name": "Sample Asset 1",
      "description": "Office computer for accounting",
      "category": "Equipment",
      "type": "Computer",
      "serial_number": "SN001",
      "model": "Dell OptiPlex",
      "manufacturer": "Dell",
      "purchase_date": "2024-01-15",
      "purchase_price": "1200.00",
      "depreciation": "100.00",
      "location": "Main Office",
      "department": "IT",
      "warranty": "3 years",
      "insurance": "Company policy",
      "health_score": 95,
      "status": "Active",
      "tags": ["Computer", "Office", "IT"]
    },
    {
      "name": "Sample Asset 2",
      "description": "Executive desk",
      "category": "Furniture",
      "type": "Desk",
      "serial_number": "SN002",
      "model": "Executive Desk",
      "manufacturer": "OfficeMax",
      "purchase_date": "2024-02-20",
      "purchase_price": "800.00",
      "depreciation": "50.00",
      "location": "Conference Room",
      "department": "HR",
      "warranty": "1 year",
      "insurance": "Company policy",
      "health_score": 90,
      "status": "Active",
      "tags": ["Furniture", "Desk", "Executive"]
    }
  ]
}
```

## Response

### Success Response (200)

```json
{
  "success": true,
  "message": "Bulk import completed",
  "data": {
    "total_processed": 2,
    "imported_count": 2,
    "failed_count": 0,
    "imported": [
      {
        "index": 1,
        "asset_id": "AST-212-0001",
        "name": "Sample Asset 1",
        "status": "success"
      },
      {
        "index": 2,
        "asset_id": "AST-212-0002",
        "name": "Sample Asset 2",
        "status": "success"
      }
    ],
    "failed": [],
    "errors": []
  }
}
```

### Error Response (422)

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "assets.0.name": ["The assets.0.name field is required."],
    "assets.1.status": ["The selected assets.1.status is invalid."]
  }
}
```

### Server Error (500)

```json
{
  "success": false,
  "message": "Bulk import failed",
  "error": "Database connection error"
}
```

## Business Logic

### Import Process

1. **Validation**: Validate all required fields and data types
2. **Entity Handling**: Handle related entities as follows:
   - **Categories**: Create if doesn't exist
   - **Locations**: Create if doesn't exist (with default "Office" type)
   - **Departments**: Create if doesn't exist
   - **Asset Types**: Create if doesn't exist
   - **Asset Statuses**: Create if doesn't exist
   - **Tags**: Create if doesn't exist (company-scoped)
3. **Asset ID Generation**: Generate unique asset IDs using format: `AST-{COMPANY_PREFIX}-{SEQUENTIAL_NUMBER}`
4. **Asset Creation**: Create assets with all provided data
5. **Tag Assignment**: Attach tags to assets if provided
6. **Activity Logging**: Log creation activity for each asset
7. **Transaction Safety**: Use database transactions for data consistency

### Asset ID Generation

The API generates unique asset IDs using the following format:
- **Format**: `AST-{COMPANY_PREFIX}-{SEQUENTIAL_NUMBER}`
- **Example**: `AST-123-0001`, `AST-123-0002`, etc.
- **Company Prefix**: First 3 characters of the company_id (uppercase)
- **Sequential Number**: 4-digit zero-padded number based on company's asset count

### Entity Handling

The API handles related entities as follows:

#### Categories
- **Auto-creation**: Creates if doesn't exist
- **Name**: As provided in the request
- **Description**: "{category} category"
- **Icon**: Default icon (📦)

#### Locations
- **Auto-creation**: Creates if doesn't exist
- **Name**: As provided in the request
- **Type**: Default "Office" location type
- **Description**: "{location} location"
- **Scope**: Company-scoped locations only
- **User**: Created by the importing user

#### Departments
- **Validation**: Must exist before import
- **Error**: Shows error if department doesn't exist
- **Scope**: Company-scoped departments only
- **Action**: Create departments manually before importing assets

#### Asset Types
- **Auto-creation**: Creates if doesn't exist
- **Name**: As provided in the request
- **Description**: "{type} type"
- **Icon**: Default icon (🏷️)

#### Asset Statuses
- **Auto-creation**: Creates if doesn't exist
- **Name**: As provided in the request
- **Description**: "{status} status"
- **Color**: Green for "Active", gray for others

#### Tags
- **Auto-creation**: Creates if doesn't exist
- **Name**: As provided in the request
- **Company**: User's company
- **Assignment**: Automatically attached to the asset

## Error Handling

### Validation Errors

The API validates each asset individually and provides detailed error messages:

```json
{
  "errors": {
    "assets.0.name": ["The assets.0.name field is required."],
    "assets.1.status": ["The selected assets.1.status is invalid."],
    "assets.2.purchase_cost": ["The assets.2.purchase_cost must be a number."]
  }
}
```

### Partial Success

The API processes all assets even if some fail:

- **Successful imports**: Listed in `imported` array
- **Failed imports**: Listed in `failed` array with error details
- **Transaction safety**: If any critical error occurs, all changes are rolled back

### Common Error Scenarios

1. **Missing required fields**: Returns 422 with field-specific errors
2. **Invalid date format**: Returns 422 for malformed dates
3. **Invalid numeric values**: Returns 422 for non-numeric purchase prices
4. **Duplicate serial numbers**: Returns 422 for duplicate serial numbers within company
5. **Invalid health score**: Returns 422 for health scores outside 0-100 range
6. **Location auto-created**: Locations are automatically created if they don't exist
7. **Department not found**: Returns error with specific department name
8. **Database errors**: Returns 500 with error details

## Testing

### Test Script

Use the provided test script to verify the API:

```bash
php test_bulk_import.php
```

### Manual Testing

```bash
curl -X POST http://your-domain.com/api/assets/import-bulk-json \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "assets": [
      {
        "name": "Test Asset",
        "category": "Equipment",
        "facility_id": "TEST",
        "asset_type": "Computer",
        "status": "Active",
        "description": "Test asset",
        "serial_number": "TEST001",
        "purchase_cost": "1000.00"
      }
    ]
  }'
```

## Frontend Integration

### JavaScript Example

```javascript
const bulkImportAssets = async (assets) => {
  try {
    const response = await axios.post('/api/assets/import-bulk-json', {
      assets: assets
    });
    
    if (response.data.success) {
      console.log(`Imported ${response.data.data.imported_count} assets`);
      console.log('Imported:', response.data.data.imported);
      
      if (response.data.data.failed_count > 0) {
        console.log('Failed:', response.data.data.failed);
      }
    }
  } catch (error) {
    if (error.response?.data?.errors) {
      console.error('Validation errors:', error.response.data.errors);
    } else {
      console.error('Import failed:', error.response?.data?.message);
    }
  }
};

// Usage
const assets = [
  {
    name: "Laptop 1",
    category: "Equipment",
    facility_id: "212",
    asset_type: "Computer",
    status: "Active",
    serial_number: "LAP001",
    purchase_cost: "1500.00"
  }
];

bulkImportAssets(assets);
```

### React Component Example

```jsx
import React, { useState } from 'react';
import axios from 'axios';

const BulkImportComponent = () => {
  const [assets, setAssets] = useState([]);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);

  const handleImport = async () => {
    setLoading(true);
    try {
      const response = await axios.post('/api/assets/import-bulk-json', {
        assets: assets
      });
      setResult(response.data);
    } catch (error) {
      setResult({
        success: false,
        message: error.response?.data?.message || 'Import failed',
        errors: error.response?.data?.errors
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <h2>Bulk Import Assets</h2>
      
      {/* Asset input form */}
      <div>
        {/* Add your asset input fields here */}
      </div>
      
      <button 
        onClick={handleImport} 
        disabled={loading || assets.length === 0}
      >
        {loading ? 'Importing...' : 'Import Assets'}
      </button>
      
      {/* Results display */}
      {result && (
        <div>
          <h3>Import Results</h3>
          <p>Total: {result.data?.total_processed}</p>
          <p>Imported: {result.data?.imported_count}</p>
          <p>Failed: {result.data?.failed_count}</p>
          
          {result.data?.imported?.map(asset => (
            <div key={asset.asset_id}>
              ✅ {asset.asset_id}: {asset.name}
            </div>
          ))}
          
          {result.data?.failed?.map(failure => (
            <div key={failure.index}>
              ❌ Row {failure.index}: {failure.error}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
```

## Performance Considerations

### Large Imports

For large imports (100+ assets):

1. **Batch Processing**: Consider splitting large imports into smaller batches
2. **Progress Tracking**: Implement progress indicators for large imports
3. **Memory Usage**: Monitor memory usage during large imports
4. **Timeout Handling**: Set appropriate timeout values for large requests

### Recommended Limits

- **Maximum assets per request**: 1000 assets
- **Request timeout**: 60 seconds
- **Memory limit**: 512MB

## Security Considerations

1. **Authentication**: All requests require valid authentication
2. **Authorization**: Assets are scoped to user's company
3. **Input Validation**: All input is validated and sanitized
4. **SQL Injection**: Uses Laravel's Eloquent ORM
5. **Rate Limiting**: Consider implementing rate limiting for production

## Monitoring and Logging

- All imports are logged in the `asset_activities` table
- Failed imports are tracked with detailed error messages
- Laravel's built-in logging captures any errors or exceptions

## Support

For questions or issues with the Bulk Import API:

1. Check the Laravel logs for detailed error information
2. Review the validation errors in the response
3. Test with the provided test script
4. Contact the development team for additional support 