# Asset Activity API Documentation

This document describes the API endpoints for retrieving asset activity histories in the AssetGo system.

## Authentication

All endpoints require authentication using Laravel Sanctum. Include the Bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

## Endpoints

### 1. Get Asset Activity History

Retrieves the activity history for a specific asset with filtering, search, and pagination capabilities.

**Endpoint:** `GET /api/assets/{asset}/activity-history`

**Parameters:**
- `asset` (path parameter): The asset ID

**Query Parameters:**
- `search` (optional): Search in action, comment, or user name/email
- `action` (optional): Filter by specific action type
- `user_id` (optional): Filter by user who performed the action
- `date_from` (optional): Filter activities from this date (YYYY-MM-DD)
- `date_to` (optional): Filter activities until this date (YYYY-MM-DD)
- `sort_by` (optional): Sort field (created_at, action, user_id) - default: created_at
- `sort_dir` (optional): Sort direction (asc, desc) - default: desc
- `per_page` (optional): Number of items per page (1-100) - default: 15
- `page` (optional): Page number - default: 1

**Example Request:**
```bash
GET /api/assets/123/activity-history?search=transfer&action=transferred&date_from=2024-01-01&per_page=20
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "action": "transferred",
        "comment": "Asset transferred from Building A to Building B",
        "before": {
          "location_id": 1,
          "location_name": "Building A"
        },
        "after": {
          "location_id": 2,
          "location_name": "Building B"
        },
        "user": {
          "id": 5,
          "name": "John Doe",
          "email": "john@example.com"
        },
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z",
        "formatted_date": "Jan 15, 2024 10:30:00",
        "time_ago": "2 hours ago"
      }
    ],
    "first_page_url": "http://localhost/api/assets/123/activity-history?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost/api/assets/123/activity-history?page=1",
    "next_page_url": null,
    "path": "http://localhost/api/assets/123/activity-history",
    "per_page": 20,
    "prev_page_url": null,
    "to": 1,
    "total": 1
  },
  "asset": {
    "id": 123,
    "name": "Laptop Dell XPS 13",
    "serial_number": "DLXPS001"
  }
}
```

### 2. Get All Asset Activities

Retrieves activity history for all assets across the company with comprehensive filtering and search capabilities.

**Endpoint:** `GET /api/assets/activities`

**Query Parameters:**
- `search` (optional): Search in action, comment, asset name/serial, or user name/email
- `action` (optional): Filter by specific action type
- `asset_id` (optional): Filter by specific asset
- `user_id` (optional): Filter by user who performed the action
- `date_from` (optional): Filter activities from this date (YYYY-MM-DD)
- `date_to` (optional): Filter activities until this date (YYYY-MM-DD)
- `sort_by` (optional): Sort field (created_at, action, user_id, asset_id) - default: created_at
- `sort_dir` (optional): Sort direction (asc, desc) - default: desc
- `per_page` (optional): Number of items per page (1-100) - default: 15
- `page` (optional): Page number - default: 1

**Example Request:**
```bash
GET /api/assets/activities?search=laptop&action=created&date_from=2024-01-01&per_page=25
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "action": "created",
        "comment": "New laptop asset created",
        "before": null,
        "after": {
          "name": "Laptop Dell XPS 13",
          "serial_number": "DLXPS001",
          "category_id": 1
        },
        "asset": {
          "id": 123,
          "name": "Laptop Dell XPS 13",
          "serial_number": "DLXPS001"
        },
        "user": {
          "id": 5,
          "name": "John Doe",
          "email": "john@example.com"
        },
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z",
        "formatted_date": "Jan 15, 2024 10:30:00",
        "time_ago": "2 hours ago"
      }
    ],
    "first_page_url": "http://localhost/api/assets/activities?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://localhost/api/assets/activities?page=5",
    "next_page_url": "http://localhost/api/assets/activities?page=2",
    "path": "http://localhost/api/assets/activities",
    "per_page": 25,
    "prev_page_url": null,
    "to": 25,
    "total": 125
  }
}
```

## Common Action Types

The following action types are commonly used in the system:

- `created` - Asset was created
- `updated` - Asset details were updated
- `transferred` - Asset was transferred to a new location
- `archived` - Asset was archived
- `restored` - Asset was restored from archive
- `deleted` - Asset was deleted
- `maintenance_scheduled` - Maintenance was scheduled
- `maintenance_completed` - Maintenance was completed
- `status_changed` - Asset status was changed
- `assigned` - Asset was assigned to a user
- `unassigned` - Asset was unassigned from a user

## Error Responses

### 403 Forbidden
```json
{
  "success": false,
  "message": "Access denied"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Asset not found"
}
```

### 422 Validation Error
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "date_from": ["The date from field must be a valid date."],
    "per_page": ["The per page field must be between 1 and 100."]
  }
}
```

## Rate Limiting

API requests are rate-limited to prevent abuse. The current limits are:
- 60 requests per minute per authenticated user
- 1000 requests per hour per authenticated user

## Notes

1. All timestamps are returned in ISO 8601 format (UTC)
2. The `before` and `after` fields contain JSON objects representing the state before and after the action
3. User information is included when available, but may be null for system-generated activities
4. Pagination is handled using Laravel's built-in pagination with metadata
5. Search is case-insensitive and uses partial matching
6. Date filters use the server's timezone (UTC) 