# Reports Module - Week 1 Testing Guide

## 🧪 **Testing Checklist**

This guide will help you verify that all Week 1 implementations are working correctly.

---

## 📋 **Pre-Testing Setup**

### **1. Backend Setup**

```bash
# Navigate to project directory
cd d:\laragon-2025\www\assetGo-backend

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Ensure queue worker is running (for exports)
php artisan queue:work --tries=3 --timeout=300
```

### **2. Frontend Setup**

```bash
# Navigate to frontend directory
cd assetGo-frontend

# Ensure dev server is running
npm start
```

### **3. Database Check**

Ensure you have:
- ✅ At least 10-20 assets created
- ✅ At least 10-20 work orders created
- ✅ Assets with different statuses (active, maintenance, inactive)
- ✅ Work orders with different priorities and statuses
- ✅ At least 2-3 technicians assigned to work orders

---

## 🔍 **Test 1: Asset Reports API (Backend)**

### **Test Asset Summary Report**

```bash
# Using curl or Postman
GET http://assetgo-backend.test/api/reports/assets/summary

# With filters
GET http://assetgo-backend.test/api/reports/assets/summary?date_from=2024-01-01&date_to=2024-12-31&page=1&page_size=50
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "assets": [...],
    "totals": {
      "total_count": 50,
      "total_value": 250000,
      "average_value": 5000,
      "active_count": 40,
      "maintenance_count": 5,
      "inactive_count": 5
    },
    "status_distribution": {
      "active": 40,
      "maintenance": 5,
      "inactive": 5
    },
    "category_distribution": {
      "IT Equipment": 20,
      "Vehicles": 15,
      "Machinery": 15
    },
    "pagination": {...}
  }
}
```

### **Test Asset Depreciation Report**

```bash
GET http://assetgo-backend.test/api/reports/assets/depreciation?page=1&page_size=20
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "assets": [
      {
        "id": 1,
        "name": "Laptop Dell XPS",
        "purchase_price": 1500,
        "purchase_date": "2023-01-15",
        "depreciation_life": 60,
        "monthly_depreciation": 25,
        "accumulated_depreciation": 500,
        "book_value": 1000,
        "months_elapsed": 20
      }
    ],
    "totals": {
      "total_purchase_price": 50000,
      "total_accumulated_depreciation": 10000,
      "total_book_value": 40000,
      "depreciation_percentage": 20
    },
    "pagination": {...}
  }
}
```

### **Test Asset Warranty Report**

```bash
GET http://assetgo-backend.test/api/reports/assets/warranty
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "assets": [
      {
        "id": 1,
        "name": "Server HP",
        "warranty_end_date": "2025-12-31",
        "days_to_expire": 430,
        "status": "active",
        "location": "Data Center"
      }
    ],
    "summary": {
      "total": 30,
      "active": 20,
      "expiring_soon": 5,
      "expired": 5,
      "active_percentage": 66.67
    },
    "pagination": {...}
  }
}
```

---

## 🔍 **Test 2: Maintenance Reports API (Backend)**

### **Test Maintenance Summary Report**

```bash
GET http://assetgo-backend.test/api/reports/maintenance/summary?date_from=2024-01-01&date_to=2024-12-31
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "work_orders": [...],
    "kpis": {
      "total_work_orders": 150,
      "completed_work_orders": 120,
      "overdue_work_orders": 10,
      "completion_rate": 80,
      "overdue_rate": 6.67,
      "avg_resolution_time_hours": 48.5,
      "mttr_hours": 24.3
    },
    "status_distribution": {
      "Completed": 120,
      "In Progress": 20,
      "Pending": 10
    },
    "priority_distribution": {
      "High": 30,
      "Medium": 80,
      "Low": 40
    },
    "pagination": {...}
  }
}
```

### **Test Technician Performance Report**

```bash
GET http://assetgo-backend.test/api/reports/maintenance/technician-performance
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "technicians": [
      {
        "id": 5,
        "name": "John Doe",
        "email": "john@example.com",
        "total_work_orders": 50,
        "completed_work_orders": 45,
        "completion_rate": 90,
        "avg_resolution_time_days": 2.5,
        "total_hours_worked": 200,
        "efficiency_score": 0.23
      }
    ]
  }
}
```

---

## 🔍 **Test 3: Export Functionality (Backend)**

### **Test JSON Export**

```bash
POST http://assetgo-backend.test/api/reports/export
Content-Type: application/json

{
  "report_key": "assets.summary",
  "format": "json",
  "params": {
    "date_from": "2024-01-01",
    "date_to": "2024-12-31"
  }
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Report export queued successfully",
  "data": {
    "run_id": 123,
    "status": "queued",
    "format": "json",
    "report_key": "assets.summary",
    "estimated_time": "5 seconds"
  }
}
```

### **Test Export Status Polling**

```bash
GET http://assetgo-backend.test/api/reports/runs/123
```

**Expected Response (Queued):**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "status": "queued",
    "status_label": "Queued",
    "report_key": "assets.summary",
    "format": "json",
    "created_at": "2024-10-27T10:30:00Z",
    "started_at": null,
    "completed_at": null
  }
}
```

**Expected Response (Success):**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "status": "success",
    "status_label": "Completed",
    "report_key": "assets.summary",
    "format": "json",
    "row_count": 50,
    "execution_time_ms": 2500,
    "execution_time_formatted": "2.5s",
    "created_at": "2024-10-27T10:30:00Z",
    "started_at": "2024-10-27T10:30:01Z",
    "completed_at": "2024-10-27T10:30:03Z",
    "download_url": "http://assetgo-backend.test/api/reports/runs/123/download",
    "file_size": "25.5 KB"
  }
}
```

### **Test PDF Export**

```bash
POST http://assetgo-backend.test/api/reports/export
Content-Type: application/json

{
  "report_key": "maintenance.summary",
  "format": "pdf",
  "params": {
    "date_from": "2024-10-01",
    "date_to": "2024-10-31"
  }
}
```

**What to Check:**
1. ✅ Export job is queued (status = "queued")
2. ✅ Queue worker processes the job
3. ✅ Status changes to "running", then "success"
4. ✅ PDF file is generated in `storage/app/reports/`
5. ✅ PDF has professional styling (blue headers, tables, summary section)
6. ✅ Download URL works

### **Test XLSX Export**

```bash
POST http://assetgo-backend.test/api/reports/export
Content-Type: application/json

{
  "report_key": "assets.depreciation",
  "format": "xlsx",
  "params": {}
}
```

**What to Check:**
1. ✅ Excel file is generated (not just CSV renamed)
2. ✅ Headers have blue background and white text
3. ✅ Columns are auto-sized
4. ✅ Cells have borders
5. ✅ Data is properly formatted
6. ✅ File opens correctly in Excel/LibreOffice

### **Test Download**

```bash
GET http://assetgo-backend.test/api/reports/runs/123/download
```

**What to Check:**
1. ✅ File downloads successfully
2. ✅ Correct Content-Type header (application/pdf, application/vnd.ms-excel, etc.)
3. ✅ Correct filename
4. ✅ File is not corrupted

---

## 🔍 **Test 4: Frontend Integration**

### **Test Report Loading**

1. Open the Reports page in your browser:
   ```
   http://localhost:4200/reports
   ```

2. **Check Asset Reports Tab:**
   - ✅ Tab displays correctly
   - ✅ Report cards are visible
   - ✅ Checkboxes work
   - ✅ Export panel appears when reports are selected
   - ✅ Loading state shows when generating

3. **Check Maintenance Reports Tab:**
   - ✅ Tab switch works smoothly
   - ✅ Maintenance reports load
   - ✅ Different report types are available

### **Test Export from Frontend**

1. **Select a report:**
   - Click checkbox next to "Asset Summary"
   
2. **Choose format:**
   - Select "PDF" from dropdown
   
3. **Click "Export Reports" button:**
   - ✅ Button shows loading state
   - ✅ Export is queued
   - ✅ Status updates appear
   
4. **Watch real-time updates:**
   - ✅ Status changes from "Queued" → "Running" → "Success"
   - ✅ Progress indicator updates
   - ✅ Updates happen every 2 seconds
   
5. **Auto-download:**
   - ✅ File downloads automatically when complete
   - ✅ New tab opens with download
   - ✅ Correct filename

### **Test Multiple Exports**

1. **Select multiple reports:**
   - Check "Asset Summary"
   - Check "Maintenance Summary"
   
2. **Export both:**
   - ✅ Both exports are queued
   - ✅ Both show in export panel
   - ✅ Status updates for both
   - ✅ No duplicate polling
   
3. **Check history:**
   - ✅ Export history shows both reports
   - ✅ Can download again from history

---

## 🔍 **Test 5: Error Handling**

### **Test Invalid Report Key**

```bash
POST http://assetgo-backend.test/api/reports/export
Content-Type: application/json

{
  "report_key": "invalid.report",
  "format": "pdf"
}
```

**Expected:**
- ✅ Returns error response
- ✅ Error message is clear
- ✅ Frontend shows error toast

### **Test Queue Failure**

1. Stop the queue worker
2. Submit an export
3. **Check:**
   - ✅ Export stays in "queued" status
   - ✅ Frontend shows appropriate message
   - ✅ User can see status in export panel

### **Test Network Error**

1. Turn off backend server
2. Try to load reports
3. **Check:**
   - ✅ Frontend shows error message
   - ✅ Loading state stops
   - ✅ Retry functionality works when server is back

---

## 📊 **Test 6: Performance**

### **Test Large Dataset**

1. **Create test data:**
   ```bash
   php artisan tinker
   
   # Create 1000 assets
   \App\Models\Asset::factory()->count(1000)->create();
   
   # Create 500 work orders
   \App\Models\WorkOrder::factory()->count(500)->create();
   ```

2. **Test report generation:**
   ```bash
   GET /api/reports/assets/summary?page_size=100
   ```
   
   **Check:**
   - ✅ Response time < 3 seconds
   - ✅ Pagination works correctly
   - ✅ No timeout errors

3. **Test export:**
   - Export with 1000+ records
   - **Check:**
     - ✅ Export completes within 30 seconds
     - ✅ File size is reasonable
     - ✅ No memory errors

---

## 🎯 **Success Criteria**

### **Backend (All Must Pass):**
- ✅ All 5 asset reports return valid data
- ✅ All 6 maintenance reports return valid data
- ✅ JSON export works correctly
- ✅ CSV export works correctly
- ✅ XLSX export has proper Excel formatting
- ✅ PDF export has professional styling
- ✅ Status polling works (queued → running → success)
- ✅ Download endpoint returns files correctly
- ✅ Queue processing works without errors
- ✅ No database errors or N+1 queries

### **Frontend (All Must Pass):**
- ✅ Reports page loads without errors
- ✅ Tab switching works smoothly
- ✅ Report selection (checkboxes) works
- ✅ Export panel appears/hides correctly
- ✅ Export submission works
- ✅ Real-time status polling updates every 2 seconds
- ✅ Auto-download triggers on success
- ✅ Loading states show appropriately
- ✅ Error messages display correctly
- ✅ Export history works

### **Integration (All Must Pass):**
- ✅ Frontend can call all backend endpoints
- ✅ Authentication works
- ✅ CORS is configured correctly
- ✅ File downloads work from frontend
- ✅ Multiple simultaneous exports work
- ✅ Export history persists

---

## 🐛 **Common Issues & Solutions**

### **Issue 1: Queue Not Processing**
**Symptoms:** Exports stay in "queued" status forever

**Solution:**
```bash
# Start queue worker
php artisan queue:work --tries=3 --timeout=300

# Or restart queue
php artisan queue:restart
```

### **Issue 2: PDF/XLSX Generation Fails**
**Symptoms:** Export fails with "Class not found" error

**Solution:**
```bash
# Ensure dependencies are installed
composer require barryvdh/laravel-dompdf
composer require phpoffice/phpspreadsheet

# Clear caches
php artisan config:clear
```

### **Issue 3: Download URL Not Working**
**Symptoms:** 404 error when trying to download

**Solution:**
1. Check file exists: `storage/app/reports/`
2. Check storage link: `php artisan storage:link`
3. Check permissions: Files should be readable

### **Issue 4: Polling Not Stopping**
**Symptoms:** Frontend keeps polling even after completion

**Solution:**
- Check browser console for errors
- Verify `takeWhile` logic in `export.service.ts`
- Check timeout configuration

### **Issue 5: CORS Errors**
**Symptoms:** API calls fail with CORS error

**Solution:**
```php
// In config/cors.php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:4200'],
'allowed_methods' => ['*'],
```

---

## 📝 **Testing Checklist Summary**

Use this as a quick reference:

- [ ] Asset Summary Report - API works
- [ ] Asset Utilization Report - API works
- [ ] Asset Depreciation Report - API works
- [ ] Asset Warranty Report - API works
- [ ] Asset Compliance Report - API works
- [ ] Maintenance Summary Report - API works
- [ ] Maintenance Compliance Report - API works
- [ ] Maintenance Costs Report - API works
- [ ] Maintenance Downtime Report - API works
- [ ] Maintenance Failure Analysis Report - API works
- [ ] Technician Performance Report - API works
- [ ] JSON Export - Works and downloads
- [ ] CSV Export - Works and downloads
- [ ] XLSX Export - Professional formatting
- [ ] PDF Export - Professional styling
- [ ] Status Polling - Updates every 2 seconds
- [ ] Auto-Download - Triggers on success
- [ ] Error Handling - Shows proper messages
- [ ] Multiple Exports - No conflicts
- [ ] Export History - Displays correctly
- [ ] Frontend Loading States - Show/hide correctly

---

## ✅ **Sign-Off**

After completing all tests, fill in:

**Tested By:** _________________  
**Date:** _________________  
**All Tests Passed:** ☐ Yes  ☐ No  

**Notes:**
```
_______________________________________________
_______________________________________________
_______________________________________________
```

---

*Testing guide prepared for Week 1 implementation*  
*Version: 1.0*  
*Date: October 27, 2025*


