# Asset Module API Documentation

## Authentication
All endpoints require authentication via Bearer token (Sanctum).

---

## Endpoints

### 1. List Assets (Grid/List)
- **GET** `/api/assets`
- **Query Params:** `search`, `category_id`, `status`, `location_id`, `user_id`, `tag_id`, `min_value`, `max_value`, `sort_by`, `sort_dir`, `per_page`
- **Response:**
```json
{
  "success": true,
  "data": {
    "assets": [ ... ],
    "pagination": { ... },
    "filters": { ... }
  }
}
```

### 2. Show Asset Detail
- **GET** `/api/assets/{id}`
- **Response:**
```json
{
  "success": true,
  "data": {
    "asset": { ... }
  }
}
```

### 3. Create Asset
- **POST** `/api/assets`
- **Body:** multipart/form-data or JSON
- **Fields:** name, serial_number, company_id, (see StoreAssetRequest for all fields)
- **Response:**
```json
{
  "success": true,
  "message": "Asset created successfully",
  "data": { ... }
}
```

### 4. Update Asset
- **PUT** `/api/assets/{id}`
- **Body:** JSON or multipart/form-data (see UpdateAssetRequest)
- **Response:**
```json
{
  "success": true,
  "message": "Asset updated successfully",
  "data": { ... }
}
```

### 5. Delete/Archive Asset
- **DELETE** `/api/assets/{id}`
- **Body (optional):** `{ "archive_reason": "Reason for archiving" }`
- **Response:**
```json
{
  "success": true,
  "message": "Asset deleted (archived) successfully"
}
```

### 5b. Archive Asset (explicit)
- **POST** `/api/assets/{id}/archive`
- **Body (optional):** `{ "archive_reason": "Reason for archiving" }`
- **Response:**
```json
{
  "success": true,
  "message": "Asset archived successfully"
}
```

### 5c. Bulk Archive Assets
- **POST** `/api/assets/bulk-archive`
- **Body:** `{ "asset_ids": [1,2,3], "archive_reason": "Reason for archiving" }`
- **Response:**
```json
{
  "success": true,
  "archived": [1,2,3],
  "failed": []
}
```

### 5d. Restore Asset
- **POST** `/api/assets/{id}/restore`
- **Response:**
```json
{
  "success": true,
  "message": "Asset restored successfully",
  "data": { ... }
}
```

### 5e. Bulk Permanently Delete Assets
- **POST** `/api/assets/bulk-delete`
- **Body:** `{ "asset_ids": [1,2,3] }`
- **Response:**
```json
{
  "success": true,
  "deleted": [1,2,3],
  "failed": []
}
```

### 5f. Bulk Restore Assets
- **POST** `/api/assets/bulk-restore`
- **Body:** `{ "asset_ids": [1,2,3] }`
- **Response:**
```json
{
  "success": true,
  "restored": [1,2,3],
  "failed": []
}
```

### 6. Duplicate Asset
- **POST** `/api/assets/{id}/duplicate`
- **Body:** `{ "serial_number": "NEW_SERIAL" }` (and any fields to override)
- **Response:**
```json
{
  "success": true,
  "message": "Asset duplicated successfully",
  "data": { ... }
}
```

### 7. Bulk Import Assets
- **POST** `/api/assets/import/bulk`
- **Body:** file upload (CSV/XLSX)
- **Response:**
```json
{
  "success": true,
  "message": "Bulk import completed",
  "imported_count": 10,
  "errors": [ { "row": 3, "error": "Duplicate serial" } ]
}
```

### 8. Transfer Asset
- **POST** `/api/assets/{id}/transfer`
- **Body:** to_location_id, to_user_id, transfer_date, notes, condition_report
- **Response:**
```json
{
  "success": true,
  "message": "Asset transferred successfully",
  "data": { ... }
}
```

### 9. Maintenance Schedules
- **GET** `/api/assets/{id}/maintenance-schedules`
- **POST** `/api/assets/{id}/maintenance-schedules`
- **PUT** `/api/assets/{id}/maintenance-schedules/{scheduleId}`
- **DELETE** `/api/assets/{id}/maintenance-schedules/{scheduleId}`
- **Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

### 10. Activity History
- **GET** `/api/assets/{id}/activity-history`
- **Response:**
```json
{
  "success": true,
  "data": [ ... ]
}
```

### 11. Analytics
- **GET** `/api/assets/analytics`
- **Response:**
```json
{
  "success": true,
  "data": {
    "total_assets": 100,
    "active_assets": 80,
    "archived_assets": 20,
    "archived_by_month": [ { "year": 2024, "month": 7, "count": 5 }, ... ]
  }
}
```

### 12. Export Assets
- **GET** `/api/assets/export?archived=1`
- **Response:** CSV file download of archived assets

---

## Notes
- All endpoints require authentication.
- See FormRequests for required/optional fields.
- File uploads (images, import) use `multipart/form-data`.
- All responses are JSON.
- Archived assets have `status = 'archived'` and an optional `archive_reason` field.
- Use the restore endpoint to unarchive assets.
- Use the analytics and export endpoints for reporting and data extraction. 