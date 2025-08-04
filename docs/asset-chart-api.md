# Asset Chart Data API

## Overview

The Asset Chart Data API provides depreciation chart data for assets, allowing you to visualize the asset's value over time based on its depreciation schedule.

## Endpoint

```
GET /api/assets/{asset}/chart-data
```

## Authentication

This endpoint requires authentication. Include your Bearer token in the Authorization header:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

## Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `asset` | integer | The ID of the asset to get chart data for |

## Response Format

### Success Response (200)

```json
{
    "success": true,
    "data": {
        "asset": {
            "id": 1,
            "name": "Sample Asset",
            "asset_id": "AST-123456",
            "purchase_price": "10000.00",
            "depreciation": "1000.00",
            "depreciation_life": 60,
            "created_at": "2025-01-01T00:00:00.000000Z"
        },
        "chart_data": {
            "depreciation_months": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            "depreciation_values": [10000, 9850, 9700, 9550, 9400, 9250, 9100, 8950, 8800, 8650, 8500, 8350],
            "current_index": 6,
            "has_data": true,
            "total_months": 12,
            "depreciation_per_month": 150.00,
            "life_in_months": 60
        }
    }
}
```

### Error Response (403)

```json
{
    "success": false,
    "message": "Access denied"
}
```

### Error Response (404)

```json
{
    "success": false,
    "message": "Asset not found"
}
```

### Error Response (500)

```json
{
    "success": false,
    "message": "Failed to get chart data: Error details"
}
```

## Response Fields

### Asset Object
- `id`: Asset database ID
- `name`: Asset name
- `asset_id`: Unique asset identifier
- `purchase_price`: Original purchase price
- `depreciation`: End of life value (salvage value)
- `depreciation_life`: Asset life in months
- `created_at`: Asset creation date

### Chart Data Object
- `depreciation_months`: Array of month numbers (1, 2, 3, ...)
- `depreciation_values`: Array of corresponding asset values at each month
- `current_index`: Current month index in the depreciation timeline (0-based)
- `has_data`: Boolean indicating if chart data is available
- `total_months`: Total number of months in the depreciation period
- `depreciation_per_month`: Monthly depreciation amount
- `life_in_months`: Total asset life in months

## Calculation Logic

The chart data is calculated using straight-line depreciation:

1. **Monthly Depreciation**: `(purchase_price - depreciation) / life_in_months`
2. **Asset Value at Month N**: `purchase_price - (monthly_depreciation × N)`
3. **Current Index**: Determined by comparing current date with asset creation date

## Prerequisites

For chart data to be available, the asset must have:
- `purchase_price` (numeric, > 0)
- `depreciation` (numeric, ≥ 0)
- `depreciation_life` (integer, > 0)

If any of these fields are missing or invalid, the API will return empty chart data with `has_data: false`.

## Example Usage

### cURL

```bash
curl -X GET "http://localhost:8000/api/assets/1/chart-data" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### JavaScript (Fetch)

```javascript
const response = await fetch('/api/assets/1/chart-data', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN_HERE',
    'Accept': 'application/json'
  }
});

const data = await response.json();
console.log(data);
```

### PHP

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/assets/1/chart-data');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer YOUR_TOKEN_HERE',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);
```

## Chart Visualization

The returned data can be used to create various chart visualizations:

### Line Chart Example (Chart.js)

```javascript
const ctx = document.getElementById('depreciationChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartData.depreciation_months,
        datasets: [{
            label: 'Asset Value',
            data: chartData.depreciation_values,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
```

## Notes

- The API automatically handles edge cases like division by zero
- Asset life is calculated in months for precise depreciation tracking
- The current index helps identify where the asset is in its depreciation timeline
- Chart data is calculated on-demand and not cached
- All monetary values are returned as strings to preserve decimal precision 