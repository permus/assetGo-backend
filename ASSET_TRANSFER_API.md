# Asset Transfer API Documentation

## Overview

The Asset Transfer API allows authenticated users to transfer assets between locations and departments within their company. This feature provides a complete audit trail of asset movements and maintains data integrity throughout the transfer process.

## Endpoint

```
POST /api/assets/{asset_id}/transfer
```

## Authentication

- **Required**: Bearer token authentication via Laravel Sanctum
- **Authorization**: Only authenticated users can transfer assets
- **Company Validation**: Asset must belong to the user's company

## Request Body

### Required Fields

| Field | Type | Description | Validation |
|-------|------|-------------|------------|
| `new_location_id` | integer | ID of the destination location | Required, must exist, must differ from current location |
| `transfer_reason` | string | Reason for the transfer | Required, must be one of the predefined reasons |
| `transfer_date` | date | Date of the transfer | Required, must not be in the future |

### Optional Fields

| Field | Type | Description | Validation |
|-------|------|-------------|------------|
| `new_department_id` | integer | ID of the destination department | Optional, must exist if provided |
| `notes` | string | Additional notes about the transfer | Optional, max 1000 characters |
| `to_user_id` | integer | ID of the user receiving the asset | Optional, must exist if provided |
| `condition_report` | string | Condition report of the asset | Optional, max 1000 characters |

### Transfer Reasons

The following transfer reasons are supported:

- `Relocation` - Asset is being moved to a new location
- `Department Change` - Asset is being transferred to a different department
- `Maintenance` - Asset is being moved for maintenance purposes
- `Upgrade` - Asset is being moved for upgrades or improvements
- `Storage` - Asset is being moved to storage
- `Disposal` - Asset is being moved for disposal
- `Other` - Any other reason not covered above

## Request Example

```json
{
  "new_location_id": 123,
  "new_department_id": 45,
  "transfer_reason": "Relocation",
  "transfer_date": "2025-07-30",
  "notes": "Moving due to office renovation",
  "to_user_id": 67,
  "condition_report": "Asset in good condition"
}
```

## Response

### Success Response (200)

```json
{
  "success": true,
  "message": "Asset transfer completed.",
  "data": {
    "transfer_id": 789,
    "asset_id": "AST-101",
    "new_location": "1st Floor",
    "new_department": "Manufacturing"
  }
}
```

### Error Responses

#### Asset Not Found or Unauthorized (404)

```json
{
  "success": false,
  "message": "Asset not found or unauthorized"
}
```

#### Same Location Transfer (400)

```json
{
  "success": false,
  "message": "Transfer location must be different from current."
}
```

#### Validation Errors (422)

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "new_location_id": ["The new location id field is required."],
    "transfer_reason": ["The transfer reason field is required."],
    "transfer_date": ["The transfer date field is required."]
  }
}
```

#### Server Error (500)

```json
{
  "success": false,
  "message": "Asset transfer failed",
  "error": "Database connection error"
}
```

## Business Logic

### Transfer Process

1. **Authorization Check**: Verify the user is authenticated and the asset belongs to their company
2. **Location Validation**: Ensure the new location is different from the current location
3. **Data Validation**: Validate all required fields and data types
4. **Asset Update**: Update the asset's location and department
5. **Transfer Record**: Create a record in the `asset_transfers` table
6. **Activity Log**: Log the transfer activity for audit purposes
7. **Response**: Return success response with transfer details

### Database Changes

The transfer process updates the following:

- **Assets Table**: Updates `location_id` and optionally `department_id`
- **Asset Transfers Table**: Creates a new transfer record with:
  - Old and new location/department IDs
  - Transfer reason and date
  - Notes and condition report
  - User who initiated the transfer
- **Asset Activities Table**: Logs the transfer activity

### Audit Trail

Every transfer creates a complete audit trail including:

- Previous and new location/department information
- Transfer reason and date
- User who initiated the transfer
- Timestamp of the transfer
- Activity log entry

## Database Schema

### Asset Transfers Table

```sql
CREATE TABLE asset_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    old_location_id BIGINT UNSIGNED NULL,
    new_location_id BIGINT UNSIGNED NULL,
    old_department_id BIGINT UNSIGNED NULL,
    new_department_id BIGINT UNSIGNED NULL,
    from_user_id BIGINT UNSIGNED NULL,
    to_user_id BIGINT UNSIGNED NULL,
    reason VARCHAR(255) NOT NULL,
    transfer_date DATE NOT NULL,
    notes TEXT NULL,
    condition_report TEXT NULL,
    status VARCHAR(255) DEFAULT 'completed',
    approved_by BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (old_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (new_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (old_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (new_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
```

## Testing

### Unit Tests

The API includes comprehensive tests covering:

- ✅ Successful asset transfer
- ✅ Transfer to same location (should fail)
- ✅ Transfer asset from different company (should fail)
- ✅ Validation of required fields
- ✅ Validation of transfer reasons

### Test Command

```bash
php artisan test --filter="transfer"
```

### Manual Testing

Use the provided test script:

```bash
php test_transfer_api.php
```

## Error Handling

### Common Error Scenarios

1. **Invalid Asset ID**: Returns 404 if asset doesn't exist
2. **Unauthorized Access**: Returns 404 if asset doesn't belong to user's company
3. **Same Location**: Returns 400 if trying to transfer to the same location
4. **Invalid Location**: Returns 422 if destination location doesn't exist
5. **Invalid Department**: Returns 422 if destination department doesn't exist
6. **Future Date**: Returns 422 if transfer date is in the future
7. **Invalid Reason**: Returns 422 if transfer reason is not in the allowed list

### Transaction Safety

All transfers are wrapped in database transactions to ensure data consistency. If any part of the transfer process fails, all changes are rolled back.

## Security Considerations

1. **Authentication**: All requests require valid authentication tokens
2. **Authorization**: Users can only transfer assets belonging to their company
3. **Input Validation**: All input is validated and sanitized
4. **SQL Injection**: Uses Laravel's Eloquent ORM to prevent SQL injection
5. **CSRF Protection**: Protected by Laravel's CSRF middleware

## Rate Limiting

The API endpoint is subject to Laravel's default rate limiting. Consider implementing additional rate limiting for production use.

## Monitoring and Logging

- All transfers are logged in the `asset_activities` table
- Transfer records are maintained in the `asset_transfers` table
- Laravel's built-in logging captures any errors or exceptions

## Future Enhancements

Potential improvements for the Asset Transfer API:

1. **Bulk Transfers**: Support for transferring multiple assets at once
2. **Transfer Approval Workflow**: Multi-step approval process for transfers
3. **Transfer Scheduling**: Schedule transfers for future dates
4. **Transfer Templates**: Predefined transfer reasons and workflows
5. **Email Notifications**: Notify relevant parties about transfers
6. **Transfer History**: API endpoint to retrieve transfer history
7. **Transfer Reversal**: Ability to reverse transfers if needed

## Support

For questions or issues with the Asset Transfer API:

1. Check the Laravel logs for detailed error information
2. Review the test cases for usage examples
3. Consult the API documentation for field requirements
4. Contact the development team for additional support 