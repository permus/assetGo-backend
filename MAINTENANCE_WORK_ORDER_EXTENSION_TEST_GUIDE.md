# Maintenance Work Order Extension Command - Test Guide

## Overview
This guide explains how to test the `maintenance:extend-work-orders` command that automatically extends work orders for maintenance schedules.

## Command Features
- Extends work orders for schedules when the last work order is within 3 months
- Avoids duplicate work orders
- Maintains a 12-month rolling window
- Supports dry-run mode for testing
- Can target specific schedules
- Can force regeneration

## Manual Testing Steps

### 1. Test Dry-Run Mode (Safest First Test)
```bash
php artisan maintenance:extend-work-orders --dry-run
```
**Expected**: Shows what would be done without making changes

### 2. Test with No Schedules
If you have no active maintenance schedules:
```bash
php artisan maintenance:extend-work-orders
```
**Expected**: Message "No active maintenance schedules found."

### 3. Test with Existing Schedule (No Work Orders)
Create a maintenance schedule first, then:
```bash
php artisan maintenance:extend-work-orders --schedule-id=1
```
**Expected**: Skips the schedule (no work orders exist)

### 4. Test with Force Option
```bash
php artisan maintenance:extend-work-orders --schedule-id=1 --force
```
**Expected**: Generates initial work orders for the schedule

### 5. Test Extension Logic
1. Create a schedule with a work order that's 2 months away
2. Run the command:
```bash
php artisan maintenance:extend-work-orders --schedule-id=1
```
**Expected**: Generates new work orders extending from the last one

### 6. Test Skip Logic
1. Create a schedule with a work order that's 4+ months away
2. Run the command:
```bash
php artisan maintenance:extend-work-orders --schedule-id=1
```
**Expected**: Skips the schedule (last work order is too far in the future)

### 7. Test Duplicate Prevention
1. Create a schedule with existing work orders
2. Run the command multiple times:
```bash
php artisan maintenance:extend-work-orders --schedule-id=1 --force
php artisan maintenance:extend-work-orders --schedule-id=1 --force
```
**Expected**: No duplicate work orders created for the same due dates

## Automated Tests

Run the PHPUnit test suite:
```bash
php artisan test --filter ExtendMaintenanceWorkOrdersCommandTest
```

Or run all tests:
```bash
php artisan test
```

## Test Scenarios Covered

### Unit Tests
1. ✅ Command extends work orders when last is within 3 months
2. ✅ Command skips schedule when last work order is more than 3 months away
3. ✅ Command skips schedule with no work orders unless forced
4. ✅ Command generates work orders when forced
5. ✅ Dry-run mode does not create work orders
6. ✅ Command avoids duplicate work orders
7. ✅ Command only processes time-based plans

## Verification Checklist

After running the command, verify:

- [ ] Work orders are created with correct `due_date`
- [ ] Work orders have `meta->schedule_id` set correctly
- [ ] Work orders have `meta->plan_id` set correctly
- [ ] Work orders have `meta->auto_generated` set to true
- [ ] Work orders have `type` set to 'ppm'
- [ ] Schedule's `auto_generated_wo_ids` is updated
- [ ] No duplicate work orders for the same due date
- [ ] Work orders extend 12 months from the last one
- [ ] Parts from maintenance plan are added to work orders

## Troubleshooting

### Command Not Found
If you get "Command not found", ensure:
- Command file exists: `app/Console/Commands/ExtendMaintenanceWorkOrdersCommand.php`
- Run `php artisan list` to see if command is registered
- Clear cache: `php artisan cache:clear`

### No Work Orders Generated
Check:
- Schedule has an active plan with `frequency_type = 'time'`
- Plan has valid `frequency_value` and `frequency_unit`
- Last work order is within 3 months (or use `--force`)
- Schedule has `start_date` or existing work orders

### Duplicate Work Orders
The command should prevent duplicates, but if you see them:
- Check if work orders have the same `due_date`
- Verify the duplicate check logic in `extendWorkOrdersForSchedule`

## Cron Job Setup

The command is scheduled to run daily at 2:00 AM. To verify the schedule:

```bash
php artisan schedule:list
```

To test the scheduler:
```bash
php artisan schedule:run
```

## Database Queries for Verification

### Check work orders for a schedule:
```sql
SELECT * FROM work_orders 
WHERE JSON_EXTRACT(meta, '$.schedule_id') = 1 
ORDER BY due_date;
```

### Check schedule's auto_generated_wo_ids:
```sql
SELECT auto_generated_wo_ids FROM schedule_maintenance WHERE id = 1;
```

### Count work orders per schedule:
```sql
SELECT 
    JSON_EXTRACT(meta, '$.schedule_id') as schedule_id,
    COUNT(*) as work_order_count
FROM work_orders
WHERE JSON_EXTRACT(meta, '$.auto_generated') = true
GROUP BY schedule_id;
```

