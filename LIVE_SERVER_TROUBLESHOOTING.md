# Live Server Troubleshooting - Report Download Button Not Showing

## Quick Checklist

### 1. Frontend Deployment
- [ ] Ensure latest frontend code is built and deployed
- [ ] Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)
- [ ] Check browser console for JavaScript errors
- [ ] Verify `lastCompletedDownloadUrl` is being set in component

### 2. Backend Deployment
- [ ] Ensure latest backend code is deployed
- [ ] Run migrations: `php artisan migrate`
- [ ] Clear caches: `php artisan route:clear && php artisan config:clear && php artisan cache:clear`
- [ ] Check `.env` file for correct `QUEUE_CONNECTION` setting

### 3. Queue Configuration
Check your `.env` file:
```env
QUEUE_CONNECTION=database  # or 'sync' for immediate processing
```

If using `database` queue:
- [ ] Ensure queue worker is running: `php artisan queue:work --queue=reports --tries=3 --timeout=300`
- [ ] Check if jobs are being processed: `php artisan queue:work --queue=reports`

If using `sync` queue:
- [ ] Reports should process immediately (no worker needed)

### 4. API Response Check
Test the export API directly:
```bash
curl -X POST http://your-domain.com/api/reports/export \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "report_key": "assets.asset-summary",
    "format": "pdf",
    "params": {
      "date_from": "2025-10-31",
      "date_to": "2025-11-29",
      "format": "pdf"
    }
  }'
```

Check response:
- Should return `"status": "success"` if sync driver
- Should return `"status": "queued"` if async driver
- Should include `run_id` in response

### 5. Status Polling Check
After export, check status:
```bash
curl -X GET http://your-domain.com/api/reports/runs/{run_id} \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Verify response includes:
- `status: "success"`
- `download_url: "http://your-domain.com/api/reports/runs/{run_id}/download"`

### 6. Frontend Console Debugging
Open browser console and check:
1. When clicking "Generate Report", look for:
   - `Export response received, run ID: X, status: Y`
   - `✅ Export completed immediately (sync driver)` OR `Start polling for status`
   
2. If polling, check for:
   - `[Poll X/30] Status: success`
   - `✅ Export completed! Download URL: ...`

3. Check if `lastCompletedDownloadUrl` is set:
   - In console, type: `angular.getComponent(document.querySelector('app-reports')).lastCompletedDownloadUrl`
   - Should show the download URL if export completed

### 7. Common Issues

#### Issue: Button never appears
**Cause**: `lastCompletedDownloadUrl` is never set
**Fix**: 
- Check if export completes successfully
- Verify status polling is working
- Check browser console for errors

#### Issue: Button appears but download fails
**Cause**: Download URL incorrect or file missing
**Fix**:
- Verify file exists in storage: `storage/app/reports/`
- Check download route permissions
- Verify `download_url` in API response

#### Issue: Export stuck in "queued" status
**Cause**: Queue worker not running
**Fix**:
- Start queue worker: `php artisan queue:work --queue=reports`
- Or switch to sync driver: `QUEUE_CONNECTION=sync`

#### Issue: 429 Too Many Requests
**Cause**: Throttle limit reached
**Fix**:
- Already increased to 500/min, but verify routes are cleared
- Run: `php artisan route:clear`

### 8. Environment-Specific Checks

#### Check API Base URL
In `assetGo-frontend/src/environments/environment.prod.ts`:
```typescript
export const environment = {
  production: true,
  apiUrl: 'https://your-live-domain.com/api'  // Verify this is correct
};
```

#### Check CORS Settings
In `config/cors.php`, ensure your frontend domain is allowed:
```php
'allowed_origins' => [
    'https://your-frontend-domain.com'
],
```

### 9. Database Check
Verify `report_runs` table has `progress` column:
```sql
DESCRIBE report_runs;
```
Should show `progress` column. If not, run migration.

### 10. File Permissions
Ensure storage directory is writable:
```bash
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

## Quick Fix Commands

Run these on live server:

```bash
# Clear all caches
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Run migrations (if not done)
php artisan migrate

# Restart queue worker (if using database queue)
php artisan queue:restart
php artisan queue:work --queue=reports --tries=3 --timeout=300 &
```

## Testing Steps

1. Open browser console (F12)
2. Navigate to Reports page
3. Select a report and click "Generate"
4. Watch console for:
   - Export API call
   - Status polling (if async)
   - Download URL assignment
   - Button appearance
5. Check Network tab for API responses
6. Verify `lastCompletedDownloadUrl` in component state

## Still Not Working?

Check these files on live server:
- `routes/api.php` - Verify throttle limits are 500
- `app/Jobs/ExportReportJob.php` - Verify progress tracking code exists
- `app/Http/Controllers/Api/ReportExportController.php` - Verify sync driver handling
- Frontend `reports.page.ts` - Verify download button logic
- Frontend `reports.page.html` - Verify button HTML exists

