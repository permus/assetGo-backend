# Work Order API Documentation

## Overview

The Work Order API provides comprehensive functionality for managing maintenance work orders, including CRUD operations, filtering, pagination, analytics, and statistics. This API follows the same patterns as the existing Asset API.

## Authentication

All endpoints require authentication using Laravel Sanctum. Include the Bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

## Base URL

```
/api/work-orders
```

## Endpoints

### 1. List Work Orders

**GET** `/api/work-orders`

Retrieve a paginated list of work orders with filtering and search capabilities.

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `search` | string | Search in title, description, notes, asset name, location name, or assigned user | `?search=maintenance` |
| `status` | string | Filter by status | `?status=open` |
| `priority` | string | Filter by priority | `?priority=high` |
| `asset_id` | integer | Filter by asset ID | `?asset_id=123` |
| `location_id` | integer | Filter by location ID | `?location_id=456` |
| `assigned_to` | integer | Filter by assigned user ID | `?assigned_to=789` |
| `created_by` | integer | Filter by creator user ID | `?created_by=101` |
| `is_overdue` | boolean | Filter overdue work orders | `?is_overdue=true` |
| `start_date` | date | Filter by creation start date | `?start_date=2024-01-01` |
| `end_date` | date | Filter by creation end date | `?end_date=2024-12-31` |
| `due_start_date` | date | Filter by due date start | `?due_start_date=2024-01-01` |
| `due_end_date` | date | Filter by due date end | `?due_end_date=2024-12-31` |
| `sort_by` | string | Sort field (default: created_at) | `?sort_by=due_date` |
| `sort_dir` | string | Sort direction (asc/desc, default: desc) | `?sort_dir=asc` |
| `per_page` | integer | Items per page (max: 100, default: 15) | `?per_page=25` |

#### Response

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "title": "Fix HVAC System",
                "description": "HVAC system needs maintenance",
                "priority": "high",
                "status": "in_progress",
                "due_date": "2024-02-15T10:00:00.000000Z",
                "completed_at": null,
                "asset_id": 123,
                "location_id": 456,
                "assigned_to": 789,
                "assigned_by": 101,
                "created_by": 101,
                "company_id": 1,
                "estimated_hours": 4.5,
                "actual_hours": null,
                "notes": "Check refrigerant levels",
                "meta": {
                    "requires_special_tools": true
                },
                "is_overdue": false,
                "days_until_due": 5,
                "days_since_created": 2,
                "resolution_time_days": null,
                "created_at": "2024-02-10T08:00:00.000000Z",
                "updated_at": "2024-02-10T08:00:00.000000Z",
                "asset": {
                    "id": 123,
                    "name": "HVAC Unit 1",
                    "asset_id": "HVAC001"
                },
                "location": {
                    "id": 456,
                    "name": "Building A - Floor 2"
                },
                "assigned_to": {
                    "id": 789,
                    "first_name": "John",
                    "last_name": "Doe"
                },
                "assigned_by": {
                    "id": 101,
                    "first_name": "Jane",
                    "last_name": "Smith"
                },
                "created_by": {
                    "id": 101,
                    "first_name": "Jane",
                    "last_name": "Smith"
                }
            }
        ],
        "first_page_url": "...",
        "from": 1,
        "last_page": 5,
        "last_page_url": "...",
        "links": [...],
        "next_page_url": "...",
        "path": "...",
        "per_page": 15,
        "prev_page_url": null,
        "to": 15,
        "total": 75
    },
    "message": "Work orders retrieved successfully"
}
```

### 2. Get Work Order Count

**GET** `/api/work-orders/count`

Get the total count of work orders with optional filtering.

#### Query Parameters

Same as list endpoint, but without pagination parameters.

#### Response

```json
{
    "success": true,
    "data": {
        "count": 75
    },
    "message": "Work order count retrieved successfully"
}
```

### 3. Show Work Order

**GET** `/api/work-orders/{id}`

Retrieve a specific work order by ID.

#### Response

```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Fix HVAC System",
        "description": "HVAC system needs maintenance",
        "priority": "high",
        "status": "in_progress",
        "due_date": "2024-02-15T10:00:00.000000Z",
        "completed_at": null,
        "asset_id": 123,
        "location_id": 456,
        "assigned_to": 789,
        "assigned_by": 101,
        "created_by": 101,
        "company_id": 1,
        "estimated_hours": 4.5,
        "actual_hours": null,
        "notes": "Check refrigerant levels",
        "meta": {
            "requires_special_tools": true
        },
        "is_overdue": false,
        "days_until_due": 5,
        "days_since_created": 2,
        "resolution_time_days": null,
        "created_at": "2024-02-10T08:00:00.000000Z",
        "updated_at": "2024-02-10T08:00:00.000000Z",
        "asset": {...},
        "location": {...},
        "assigned_to": {...},
        "assigned_by": {...},
        "created_by": {...}
    },
    "message": "Work order retrieved successfully"
}
```

### 4. Create Work Order

**POST** `/api/work-orders`

Create a new work order.

#### Request Body

```json
{
    "title": "Fix HVAC System",
    "description": "HVAC system needs maintenance",
    "priority": "high",
    "status": "open",
    "due_date": "2024-02-15T10:00:00.000000Z",
    "asset_id": 123,
    "location_id": 456,
    "assigned_to": 789,
    "estimated_hours": 4.5,
    "notes": "Check refrigerant levels",
    "meta": {
        "requires_special_tools": true
    }
}
```

#### Required Fields

- `title` (string, max 255 characters)
- `priority` (enum: low, medium, high, critical)

#### Optional Fields

- `description` (string, max 1000 characters)
- `status` (enum: open, in_progress, completed, on_hold, cancelled)
- `due_date` (datetime, must be in the future)
- `asset_id` (integer, must exist in assets table)
- `location_id` (integer, must exist in locations table)
- `assigned_to` (integer, must exist in users table)
- `estimated_hours` (decimal, 0-999999.99)
- `actual_hours` (decimal, 0-999999.99)
- `notes` (string, max 1000 characters)
- `meta` (object, for extensibility)

#### Response

```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Fix HVAC System",
        "description": "HVAC system needs maintenance",
        "priority": "high",
        "status": "open",
        "due_date": "2024-02-15T10:00:00.000000Z",
        "completed_at": null,
        "asset_id": 123,
        "location_id": 456,
        "assigned_to": 789,
        "assigned_by": 101,
        "created_by": 101,
        "company_id": 1,
        "estimated_hours": 4.5,
        "actual_hours": null,
        "notes": "Check refrigerant levels",
        "meta": {
            "requires_special_tools": true
        },
        "created_at": "2024-02-10T08:00:00.000000Z",
        "updated_at": "2024-02-10T08:00:00.000000Z",
        "asset": {...},
        "location": {...},
        "assigned_to": {...},
        "assigned_by": {...},
        "created_by": {...}
    },
    "message": "Work order created successfully"
}
```

### 5. Update Work Order

**PUT** `/api/work-orders/{id}`

Update an existing work order.

#### Request Body

Same as create, but all fields are optional.

#### Response

```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Fix HVAC System - Updated",
        "description": "HVAC system needs maintenance",
        "priority": "high",
        "status": "in_progress",
        "due_date": "2024-02-15T10:00:00.000000Z",
        "completed_at": null,
        "asset_id": 123,
        "location_id": 456,
        "assigned_to": 789,
        "assigned_by": 101,
        "created_by": 101,
        "company_id": 1,
        "estimated_hours": 4.5,
        "actual_hours": 2.5,
        "notes": "Check refrigerant levels - In progress",
        "meta": {
            "requires_special_tools": true
        },
        "updated_at": "2024-02-10T10:00:00.000000Z",
        "asset": {...},
        "location": {...},
        "assigned_to": {...},
        "assigned_by": {...},
        "created_by": {...}
    },
    "message": "Work order updated successfully"
}
```

### 6. Delete Work Order

**DELETE** `/api/work-orders/{id}`

Delete a work order (soft delete).

#### Response

```json
{
    "success": true,
    "message": "Work order deleted successfully"
}
```

### 7. Get Work Order Analytics

**GET** `/api/work-orders/analytics`

Get comprehensive analytics data for work orders dashboard.

#### Query Parameters

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `date_range` | integer | Number of days for trend analysis | 30 |

#### Response

```json
{
    "success": true,
    "data": {
        "total_work_orders": 186,
        "open_work_orders": 45,
        "in_progress_work_orders": 32,
        "completed_work_orders": 90,
        "overdue_work_orders": 12,
        "average_resolution_time_days": 4.2,
        "completion_rate_percentage": 48.4,
        "active_technicians": 8,
        
        "status_distribution": {
            "open": 45,
            "in_progress": 32,
            "completed": 90,
            "on_hold": 12,
            "cancelled": 7
        },
        
        "priority_distribution": {
            "low": 30,
            "medium": 85,
            "high": 45,
            "critical": 26
        },
        
        "monthly_performance_trend": [
            {
                "year": 2024,
                "month": 1,
                "created_count": 45,
                "completed_count": 38
            },
            {
                "year": 2024,
                "month": 2,
                "created_count": 50,
                "completed_count": 40
            }
        ],
        
        "top_technician_performance": [
            {
                "assigned_to": 789,
                "completed_count": 15,
                "avg_resolution_days": 3.2,
                "assigned_to_user": {
                    "id": 789,
                    "first_name": "John",
                    "last_name": "Doe"
                }
            }
        ]
    },
    "message": "Work order analytics retrieved successfully"
}
```

### 8. Get Work Order Statistics

**GET** `/api/work-orders/statistics`

Get basic statistics for work orders.

#### Response

```json
{
    "success": true,
    "data": {
        "status_counts": {
            "open": 45,
            "in_progress": 32,
            "completed": 90,
            "on_hold": 12,
            "cancelled": 7
        },
        "priority_counts": {
            "low": 30,
            "medium": 85,
            "high": 45,
            "critical": 26
        },
        "overdue_count": 12,
        "recent_created": 8,
        "recent_completed": 5
    },
    "message": "Work order statistics retrieved successfully"
}
```

### 9. Get Work Order Filters

**GET** `/api/work-orders/filters`

Get available filter options for work orders.

#### Response

```json
{
    "success": true,
    "data": {
        "assets": [
            {
                "id": 123,
                "name": "HVAC Unit 1",
                "asset_id": "HVAC001"
            }
        ],
        "locations": [
            {
                "id": 456,
                "name": "Building A - Floor 2"
            }
        ],
        "users": [
            {
                "id": 789,
                "first_name": "John",
                "last_name": "Doe"
            }
        ],
        "status_options": {
            "open": "Open",
            "in_progress": "In Progress",
            "completed": "Completed",
            "on_hold": "On Hold",
            "cancelled": "Cancelled"
        },
        "priority_options": {
            "low": "Low",
            "medium": "Medium",
            "high": "High",
            "critical": "Critical"
        }
    },
    "message": "Work order filters retrieved successfully"
}
```

## Status Values

- `open` - Work order is created but not started
- `in_progress` - Work order is currently being worked on
- `completed` - Work order has been finished
- `on_hold` - Work order is temporarily paused
- `cancelled` - Work order has been cancelled

## Priority Values

- `low` - Low priority work order
- `medium` - Medium priority work order (default)
- `high` - High priority work order
- `critical` - Critical priority work order

## Error Responses

### Validation Error (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "title": ["The title field is required."],
        "priority": ["The priority must be one of: low, medium, high, critical."]
    }
}
```

### Not Found Error (404)

```json
{
    "success": false,
    "message": "Work order not found"
}
```

### Unauthorized Error (401)

```json
{
    "message": "Unauthenticated."
}
```

## Usage Examples

### Create a High Priority Work Order

```bash
curl -X POST /api/work-orders \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Emergency HVAC Repair",
    "description": "HVAC system completely down",
    "priority": "critical",
    "due_date": "2024-02-12T18:00:00.000000Z",
    "asset_id": 123,
    "assigned_to": 789
  }'
```

### Get Overdue Work Orders

```bash
curl -X GET "/api/work-orders?is_overdue=true&status=open,in_progress" \
  -H "Authorization: Bearer {token}"
```

### Get Analytics for Last 60 Days

```bash
curl -X GET "/api/work-orders/analytics?date_range=60" \
  -H "Authorization: Bearer {token}"
```

### Update Work Order Status

```bash
curl -X PUT /api/work-orders/1 \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "completed",
    "actual_hours": 3.5,
    "notes": "Completed successfully"
  }'
```
