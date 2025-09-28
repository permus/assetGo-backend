# AssetGo Reports Module - Backend Testing Guide

This guide provides comprehensive instructions for testing the Reports Module backend implementation.

## 🚀 Quick Start

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Seed Test Data (Optional)
```bash
php artisan db:seed --class=ReportsTestDataSeeder
```

### 3. Run Built-in Tests
```bash
# Get a user and company ID first
php artisan tinker
>>> $user = App\Models\User::first();
>>> $company = App\Models\Company::first();
>>> echo "User ID: " . $user->id . "\n";
>>> echo "Company ID: " . $company->id . "\n";
>>> exit

# Run the test command
php artisan reports:test --user-id=1 --company-id=1 --verbose
```

## 📋 Testing Methods

### Method 1: Laravel Artisan Command (Recommended)

The built-in test command provides comprehensive testing:

```bash
php artisan reports:test --user-id=1 --company-id=1 --verbose
```

**Features:**
- Tests database migrations
- Tests all report services
- Tests export functionality
- Tests error handling
- Provides detailed output

### Method 2: cURL Script

Run the bash script for API endpoint testing:

```bash
# Make the script executable
chmod +x test_reports_curl.sh

# Update the configuration in the script
# Edit BASE_URL, TEST_USER_EMAIL, TEST_USER_PASSWORD

# Run the tests
./test_reports_curl.sh
```

### Method 3: PHP Script

Run the PHP test script:

```bash
# Update configuration in test_reports_api.php
# Edit $baseUrl and $testUser

# Run the tests
php test_reports_api.php
```

## 🔍 Manual API Testing

### 1. Authentication

First, get an authentication token:

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

### 2. Test Asset Reports

```bash
# Asset Summary
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/assets/summary?page=1&page_size=10"

# Asset Utilization
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/assets/utilization"

# Asset Depreciation
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/assets/depreciation"

# Asset Warranty
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/assets/warranty"

# Asset Compliance
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/assets/compliance"
```

### 3. Test Maintenance Reports

```bash
# Maintenance Summary
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/maintenance/summary"

# Maintenance Compliance
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/maintenance/compliance"

# Maintenance Costs
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/maintenance/costs"

# Downtime Analysis
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/maintenance/downtime"

# Failure Analysis
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/maintenance/failure-analysis"

# Technician Performance
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/maintenance/technician-performance"
```

### 4. Test Export Functionality

```bash
# Request Export
curl -X POST http://localhost:8000/api/reports/export \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "report_key": "assets.summary",
    "format": "json",
    "params": {"page": 1, "page_size": 10}
  }'

# Check Export Status (replace RUN_ID with actual ID)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/runs/RUN_ID"

# Get Export History
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8000/api/reports/history"
```

## 🧪 Test Scenarios

### 1. Basic Functionality Tests

- [ ] All report endpoints return 200 status
- [ ] Response format includes `success: true`
- [ ] Data structure is correct
- [ ] Pagination works correctly

### 2. Parameter Testing

- [ ] Date range filters work
- [ ] Page size limits are enforced
- [ ] Invalid parameters are rejected
- [ ] Default values are applied

### 3. Export Testing

- [ ] Export requests are queued
- [ ] Export status can be checked
- [ ] Export history is accessible
- [ ] Invalid export formats are rejected

### 4. Error Handling Tests

- [ ] Invalid report keys throw exceptions
- [ ] Invalid date ranges are rejected
- [ ] Missing authentication returns 401
- [ ] Rate limiting works correctly

### 5. Performance Tests

- [ ] Reports generate within 2 seconds
- [ ] Large datasets are paginated
- [ ] Memory usage is reasonable
- [ ] Database queries are optimized

## 📊 Expected Results

### Successful Response Format
```json
{
  "success": true,
  "data": {
    "assets": [...],
    "totals": {...},
    "pagination": {...}
  },
  "meta": {
    "generated_at": "2024-01-15T10:30:00.000000Z",
    "company_id": 1
  }
}
```

### Export Response Format
```json
{
  "success": true,
  "data": {
    "run_id": 123,
    "status": "queued",
    "message": "Export job queued successfully"
  }
}
```

## 🐛 Troubleshooting

### Common Issues

1. **Authentication Errors**
   - Check if user exists and is verified
   - Verify Sanctum token is valid
   - Ensure user has company_id

2. **Database Errors**
   - Run migrations: `php artisan migrate`
   - Check database connection
   - Verify table structure

3. **Service Errors**
   - Check if services are properly registered
   - Verify dependencies are injected
   - Check Laravel logs

4. **Export Errors**
   - Ensure queue is running: `php artisan queue:work`
   - Check storage permissions
   - Verify file paths

### Debug Commands

```bash
# Check queue status
php artisan queue:work --once

# Clear failed jobs
php artisan queue:flush

# Check logs
tail -f storage/logs/laravel.log

# Test specific service
php artisan tinker
>>> $service = app(App\Services\AssetReportService::class);
>>> $result = $service->generateSummary([]);
>>> dd($result);
```

## 📈 Performance Benchmarks

### Expected Performance
- **Report Generation**: < 2 seconds for < 10k records
- **Export Processing**: < 30 seconds for < 1k records
- **API Response Time**: < 500ms for cached data
- **Memory Usage**: < 128MB for standard reports

### Load Testing
```bash
# Test with multiple concurrent requests
for i in {1..10}; do
  curl -H "Authorization: Bearer YOUR_TOKEN" \
    "http://localhost:8000/api/reports/assets/summary" &
done
wait
```

## ✅ Success Criteria

The Reports Module is working correctly if:

1. **All API endpoints return 200 status**
2. **Response format is consistent**
3. **Export functionality works**
4. **Error handling is proper**
5. **Performance meets benchmarks**
6. **No critical errors in logs**

## 🔄 Next Steps

After successful backend testing:

1. **Frontend Implementation** - Create Angular components
2. **Additional Reports** - Add Inventory and Financial reports
3. **Custom Reports** - Implement report builder
4. **Scheduling** - Add automated report generation
5. **Production Testing** - Test with real data

## 📞 Support

If you encounter issues:

1. Check the Laravel logs: `storage/logs/laravel.log`
2. Verify database migrations: `php artisan migrate:status`
3. Test individual services in Tinker
4. Check queue worker status: `php artisan queue:work --once`

---

**Happy Testing! 🚀**
