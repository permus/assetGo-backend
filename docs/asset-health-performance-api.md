# Asset Health & Performance Chart API

## Overview

The Asset Health & Performance Chart API provides comprehensive health and performance tracking data for assets, allowing you to visualize the asset's condition and operational efficiency over time based on maintenance activities, inspections, and repairs.

## Endpoint

```
GET /api/assets/{asset}/health-performance-chart
```

## Authentication

This endpoint requires authentication. Include your Bearer token in the Authorization header:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

## Parameters

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `asset` | integer | The ID of the asset to get chart data for | Required |
| `months` | integer | Number of months to look back (1-60) | 12 |

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
            "health_score": 85.5,
            "status": "active",
            "created_at": "2025-01-01T00:00:00.000000Z"
        },
        "chart_data": {
            "dates": ["2024-01", "2024-02", "2024-03", "2024-04", "2024-05", "2024-06"],
            "health_scores": [100, 98.5, 95.2, 92.1, 88.7, 85.5],
            "performance_scores": [100, 105, 102, 98, 95, 92],
            "maintenance_counts": [0, 1, 2, 1, 0, 1],
            "current_index": 5,
            "has_data": true,
            "total_months": 6,
            "metrics": {
                "average_health_score": 93.3,
                "average_performance_score": 98.7,
                "total_maintenance_count": 5,
                "health_trend": -14.5,
                "performance_trend": -8,
                "current_health_score": 85.5,
                "current_performance_score": 92
            }
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
    "message": "Failed to get health & performance chart data: Error details"
}
```

## Response Fields

### Asset Object
- `id`: Asset database ID
- `name`: Asset name
- `asset_id`: Unique asset identifier
- `health_score`: Current health score (0-100)
- `status`: Asset status
- `created_at`: Asset creation date

### Chart Data Object
- `dates`: Array of month labels (YYYY-MM format)
- `health_scores`: Array of health scores for each month
- `performance_scores`: Array of performance scores for each month
- `maintenance_counts`: Array of maintenance activities count for each month
- `current_index`: Current month index in the timeline (0-based)
- `has_data`: Boolean indicating if chart data is available
- `total_months`: Total number of months in the data

### Metrics Object
- `average_health_score`: Average health score over the period
- `average_performance_score`: Average performance score over the period
- `total_maintenance_count`: Total number of maintenance activities
- `health_trend`: Overall health trend (positive = improving, negative = declining)
- `performance_trend`: Overall performance trend (positive = improving, negative = declining)
- `current_health_score`: Current health score
- `current_performance_score`: Current performance score

## Calculation Logic

### Health Score Calculation
The health score is calculated based on asset activities and natural degradation:

1. **Base Health Score**: Starts with the asset's current health_score or 100
2. **Activity Impact**: 
   - `maintenance_completed`: +5 points
   - `maintenance_overdue`: -10 points
   - `repair`: -15 points
   - `inspection_passed`: +3 points
   - `inspection_failed`: -8 points
3. **Natural Degradation**: -0.5 points per month
4. **Score Range**: Clamped between 0 and 100

### Performance Score Calculation
The performance score is calculated based on maintenance schedules:

1. **Base Performance**: Starts at 100
2. **Maintenance Impact**:
   - `completed`: +5 points
   - `overdue`: -15 points
   - `scheduled`: +2 points
3. **Score Range**: Clamped between 0 and 100

## Example Usage

### cURL

```bash
# Get last 12 months of data
curl -X GET "http://localhost:8000/api/assets/1/health-performance-chart" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"

# Get last 6 months of data
curl -X GET "http://localhost:8000/api/assets/1/health-performance-chart?months=6" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### JavaScript (Fetch)

```javascript
// Get health & performance data
const response = await fetch('/api/assets/1/health-performance-chart?months=12', {
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
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/assets/1/health-performance-chart?months=12');
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

### Multi-Line Chart Example (Chart.js)

```javascript
const ctx = document.getElementById('healthPerformanceChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartData.dates,
        datasets: [
            {
                label: 'Health Score',
                data: chartData.health_scores,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.1,
                yAxisID: 'y'
            },
            {
                label: 'Performance Score',
                data: chartData.performance_scores,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.1,
                yAxisID: 'y'
            },
            {
                label: 'Maintenance Count',
                data: chartData.maintenance_counts,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.1,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                min: 0,
                max: 100,
                title: {
                    display: true,
                    text: 'Health & Performance Score'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                min: 0,
                title: {
                    display: true,
                    text: 'Maintenance Count'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
```

### Dashboard Widget Example

```javascript
// Display key metrics
const metrics = chartData.metrics;

document.getElementById('avgHealth').textContent = metrics.average_health_score;
document.getElementById('avgPerformance').textContent = metrics.average_performance_score;
document.getElementById('maintenanceCount').textContent = metrics.total_maintenance_count;

// Health trend indicator
const healthTrendElement = document.getElementById('healthTrend');
if (metrics.health_trend > 0) {
    healthTrendElement.innerHTML = `<span class="text-green-500">↗ +${metrics.health_trend.toFixed(1)}</span>`;
} else if (metrics.health_trend < 0) {
    healthTrendElement.innerHTML = `<span class="text-red-500">↘ ${metrics.health_trend.toFixed(1)}</span>`;
} else {
    healthTrendElement.innerHTML = `<span class="text-gray-500">→ 0.0</span>`;
}
```

## Data Sources

The chart data is calculated from:

1. **Asset Activities**: Historical activity records from the asset_activities table
2. **Maintenance Schedules**: Maintenance schedule records from the asset_maintenance_schedules table
3. **Asset Health Score**: Current health_score field from the assets table

## Performance Considerations

- Data is calculated on-demand and not cached
- Time range is limited to 1-60 months for performance
- Large datasets may take longer to process
- Consider implementing caching for frequently accessed data

## Notes

- Health scores are automatically clamped between 0 and 100
- Performance scores are automatically clamped between 0 and 100
- Natural degradation is applied monthly to simulate real-world asset aging
- Activity impacts are based on common maintenance and inspection scenarios
- The API automatically handles missing data and edge cases
- All scores are returned with one decimal place precision 