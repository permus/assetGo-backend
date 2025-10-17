# Work Order Module - Testing Guide

## Testing Checklist

### 1. Rate Limiting Tests

**Test Analytics Rate Limit (30 requests/minute):**

```bash
# Run this command 31 times rapidly
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/work-orders/analytics

# Expected: First 30 succeed, 31st returns 429 Too Many Requests
```

**Test Statistics Rate Limit (30 requests/minute):**

```bash
# Run this command 31 times rapidly
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/work-orders/statistics

# Expected: First 30 succeed, 31st returns 429 Too Many Requests
```

**Test Filters Rate Limit (60 requests/minute):**

```bash
# Run this command 61 times rapidly
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/work-orders/filters

# Expected: First 60 succeed, 61st returns 429 Too Many Requests
```

---

### 2. Caching Tests

**Test Analytics Caching:**

1. Clear cache: `php artisan cache:clear`
2. Call analytics endpoint and note response time
3. Call analytics endpoint again immediately
4. **Expected:** Second call should be significantly faster (~85% reduction)

**Test Statistics Caching:**

1. Clear cache: `php artisan cache:clear`
2. Call statistics endpoint and note response time
3. Call statistics endpoint again immediately
4. **Expected:** Second call should be significantly faster (~85% reduction)

**Test Cache Invalidation:**

1. Call analytics endpoint (data is now cached)
2. Create a new work order
3. Call analytics endpoint again
4. **Expected:** Cache should be cleared, fresh data returned

---

### 3. Audit Logging Tests

**Check Log File:**

```bash
tail -f storage/logs/laravel.log
```

**Test Creation Logging:**

1. Create a work order via API
2. Check logs for: `Work order created` with work_order_id, title, user_id, ip_address

**Test Update Logging:**

1. Update a work order
2. Check logs for: `Work order updated` with changes array showing old/new values

**Test Status Change Logging:**

1. Change work order status
2. Check logs for: `Work order status changed` with old_status and new_status

**Test Deletion Logging:**

1. Delete a work order
2. Check logs for: `Work order deleted` with work_order_id, title, user_id

---

### 4. Cross-Company Validation Tests

**Test Asset Validation:**

```bash
# Try to create work order with asset_id from different company
curl -X POST http://localhost/api/work-orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test",
    "status_id": 1,
    "priority_id": 1,
    "asset_id": <ASSET_FROM_DIFFERENT_COMPANY>
  }'

# Expected: 422 Validation Error - "The selected asset does not belong to your company"
```

**Test Location Validation:**

```bash
# Try to create work order with location_id from different company
curl -X POST http://localhost/api/work-orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test",
    "status_id": 1,
    "priority_id": 1,
    "location_id": <LOCATION_FROM_DIFFERENT_COMPANY>
  }'

# Expected: 422 Validation Error - "The selected location does not belong to your company"
```

**Test User Assignment Validation:**

```bash
# Try to assign work order to user from different company
curl -X POST http://localhost/api/work-orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test",
    "status_id": 1,
    "priority_id": 1,
    "assigned_to": <USER_FROM_DIFFERENT_COMPANY>
  }'

# Expected: 422 Validation Error - "The selected user does not belong to your company"
```

---

### 5. Frontend Toast Notification Tests

**Test Work Order Creation:**

1. Navigate to Work Orders page
2. Click "Create Work Order"
3. Fill in required fields
4. Submit form
5. **Expected:** Green success toast: "Work order created successfully"

**Test Work Order Creation Failure:**

1. Create work order with invalid data
2. **Expected:** Red error toast with specific error message

**Test Work Order Update:**

1. Click edit on a work order
2. Modify fields
3. Save
4. **Expected:** Green success toast: "Work order updated successfully"

**Test Work Order Deletion:**

1. Select a work order
2. Click delete
3. Confirm deletion
4. **Expected:** Green success toast: "Work order deleted successfully"

**Test Parts Operations:**

1. Open "Add Parts" modal
2. Search for a part
3. **Expected (on search error):** Red error toast: "Failed to search parts"
4. Add part to cart
5. **Expected:** Green success toast: "Part added to cart"
6. Remove part from cart
7. **Expected:** Green success toast: "Part removed from cart"
8. Save parts
9. **Expected:** Green success toast: "Parts added to work order successfully"

**Test Metadata Loading Errors:**

1. Disconnect from backend
2. Reload work orders page
3. **Expected:** Red error toasts for failed status/priority/category loading

---

### 6. Authorization Fix Test

**Test WorkOrderPartController:**

1. Try to access parts for work order from different company
2. **Expected:** 404 error (not 500 server error)

---

## Performance Benchmarks

### Expected Performance Improvements

| Endpoint | Before Caching | After Caching | Improvement |
|----------|---------------|---------------|-------------|
| `/api/work-orders/analytics` | ~500ms | ~75ms | 85% faster |
| `/api/work-orders/statistics` | ~300ms | ~45ms | 85% faster |

### How to Measure

```bash
# Install Apache Bench (if not installed)
# Ubuntu: apt-get install apache2-utils
# Mac: Already installed

# Test analytics endpoint
ab -n 10 -c 1 -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/work-orders/analytics

# First run: Note the "Time per request" value
# Clear cache: php artisan cache:clear
# Second run: Should see significant improvement
```

---

## Browser Console Tests

Open browser developer tools and check:

1. **No console.log spam:** Most debug logs should be removed/replaced with toasts
2. **Network tab:** Check API responses are consistent
3. **Toast container:** Verify toasts appear in top-right corner
4. **Toast auto-dismiss:** Success toasts disappear after 5 seconds, error toasts after 7 seconds

---

## Automated Testing Script

Create a file `test_work_orders.sh`:

```bash
#!/bin/bash

API_URL="http://localhost/api"
TOKEN="YOUR_AUTH_TOKEN"

echo "🧪 Testing Work Order Module..."
echo ""

# Test 1: Rate Limiting
echo "1️⃣ Testing Rate Limiting..."
for i in {1..31}; do
  response=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Authorization: Bearer $TOKEN" \
    "$API_URL/work-orders/analytics")
  
  if [ $i -eq 31 ] && [ $response -eq 429 ]; then
    echo "✅ Rate limiting works (got 429 on request #31)"
  elif [ $i -lt 31 ] && [ $response -eq 200 ]; then
    continue
  else
    echo "❌ Rate limiting failed"
    break
  fi
done
echo ""

# Test 2: Caching
echo "2️⃣ Testing Caching..."
php artisan cache:clear > /dev/null 2>&1

start_time=$(date +%s%N)
curl -s -H "Authorization: Bearer $TOKEN" "$API_URL/work-orders/analytics" > /dev/null
first_time=$(($(date +%s%N) - start_time))

start_time=$(date +%s%N)
curl -s -H "Authorization: Bearer $TOKEN" "$API_URL/work-orders/analytics" > /dev/null
second_time=$(($(date +%s%N) - start_time))

if [ $second_time -lt $first_time ]; then
  echo "✅ Caching works (second request faster)"
  echo "   First: ${first_time}ns, Second: ${second_time}ns"
else
  echo "❌ Caching may not be working"
fi
echo ""

# Test 3: Audit Logging
echo "3️⃣ Testing Audit Logging..."
log_before=$(wc -l < storage/logs/laravel.log)

curl -s -X POST "$API_URL/work-orders" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test WO","status_id":1,"priority_id":1}' > /dev/null

log_after=$(wc -l < storage/logs/laravel.log)

if [ $log_after -gt $log_before ]; then
  echo "✅ Audit logging works (log file grew)"
else
  echo "❌ Audit logging may not be working"
fi
echo ""

echo "🎉 Testing complete!"
```

Make it executable and run:

```bash
chmod +x test_work_orders.sh
./test_work_orders.sh
```

---

## Success Criteria

All tests should pass with:

- ✅ Rate limiting blocks excessive requests (429 response)
- ✅ Analytics loads in <100ms when cached
- ✅ Statistics loads in <100ms when cached
- ✅ All CRUD operations appear in logs with full details
- ✅ Toast notifications appear for all user actions
- ✅ Cache clears automatically when data changes
- ✅ Cross-company validation prevents unauthorized access
- ✅ No regression in existing functionality
- ✅ No console errors in browser
- ✅ All linter checks pass

---

## Troubleshooting

**Cache not working:**
```bash
php artisan config:clear
php artisan cache:clear
```

**Logs not appearing:**
```bash
# Check log permissions
chmod -R 775 storage/logs
```

**Rate limiting not working:**
```bash
# Check if Redis is running (if using Redis cache)
redis-cli ping
```

**Toasts not appearing:**
```bash
# Check browser console for errors
# Verify ToastService is properly injected
# Check if toast component is in app.component.html
```

---

## Next Steps After Testing

1. If all tests pass → Deploy to staging
2. If any test fails → Review implementation and fix issues
3. Document any edge cases discovered during testing
4. Update API documentation with new rate limits
5. Train team on new audit logging features

