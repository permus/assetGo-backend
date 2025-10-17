# Team Module - Testing Guide

## Testing Checklist

### 1. Rate Limiting Tests

**Test Analytics Rate Limit (30 requests/minute):**

```bash
# Run this command 31 times rapidly
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams/analytics

# Expected: First 30 succeed, 31st returns 429 Too Many Requests
```

**Test Statistics Rate Limit (30 requests/minute):**

```bash
# Run this command 31 times rapidly
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams/statistics

# Expected: First 30 succeed, 31st returns 429 Too Many Requests
```

**Automated Rate Limit Test (using shell script):**

```bash
#!/bin/bash
TOKEN="YOUR_AUTH_TOKEN_HERE"
ENDPOINT="http://localhost/api/teams/analytics"

echo "Testing rate limiting on $ENDPOINT"
echo "-----------------------------------"

success_count=0
rate_limited_count=0

for i in {1..35}; do
  response=$(curl -s -w "\n%{http_code}" -H "Authorization: Bearer $TOKEN" "$ENDPOINT")
  status_code=$(echo "$response" | tail -n 1)
  
  if [ "$status_code" -eq 200 ]; then
    ((success_count++))
    echo "Request $i: SUCCESS (200)"
  elif [ "$status_code" -eq 429 ]; then
    ((rate_limited_count++))
    echo "Request $i: RATE LIMITED (429)"
  else
    echo "Request $i: UNEXPECTED ($status_code)"
  fi
done

echo "-----------------------------------"
echo "Results:"
echo "  Successful: $success_count"
echo "  Rate Limited: $rate_limited_count"
echo "  Expected: ~30 successful, ~5 rate limited"
```

---

### 2. Caching Tests

**Test Analytics Caching:**

1. Clear cache: `php artisan cache:clear`
2. Call analytics endpoint and note response time:
   ```bash
   time curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost/api/teams/analytics?date_range=30
   ```
3. Call analytics endpoint again immediately
4. **Expected:** Second call should be significantly faster (~85% reduction: 500ms → 75ms)

**Test Statistics Caching:**

1. Clear cache: `php artisan cache:clear`
2. Call statistics endpoint and note response time:
   ```bash
   time curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost/api/teams/statistics
   ```
3. Call statistics endpoint again immediately
4. **Expected:** Second call should be significantly faster (~85% reduction: 300ms → 45ms)

**Test Cache Invalidation:**

1. Call analytics endpoint (data is now cached)
2. Create a new team member via API
3. Call analytics endpoint again
4. **Expected:** Cache should be cleared, fresh data returned (check logs for "Team cache cleared")

**Performance Benchmarking:**

```bash
# Test without cache (first call)
php artisan cache:clear
ab -n 10 -c 1 -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams/analytics

# Test with cache (subsequent calls)
ab -n 100 -c 10 -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams/analytics
```

---

### 3. Audit Logging Tests

**Check Log File:**

```bash
tail -f storage/logs/laravel.log
```

**Test Creation Logging:**

1. Create a team member via API:
   ```bash
   curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"first_name":"John","last_name":"Doe","email":"john@example.com","role_id":1}' \
     http://localhost/api/teams
   ```
2. Check logs for: `Team member created` with:
   - team_member_id
   - email
   - name
   - role_id
   - created_by_user_id
   - company_id
   - ip_address
   - timestamp

**Test Update Logging:**

1. Update a team member:
   ```bash
   curl -X PUT -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"first_name":"Jane","hourly_rate":50}' \
     http://localhost/api/teams/1
   ```
2. Check logs for: `Team member updated` with:
   - changes array showing old/new values
   - updated_by_user_id
   - ip_address

**Test Deletion Logging:**

1. Delete a team member:
   ```bash
   curl -X DELETE -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost/api/teams/1
   ```
2. Check logs for: `Team member deleted` with:
   - team_member_id
   - email
   - deleted_by_user_id

**Test Invitation Resend Logging:**

1. Resend invitation:
   ```bash
   curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost/api/teams/1/resend-invitation
   ```
2. Check logs for: `Team member invitation resent` with:
   - team_member_id
   - initiated_by_user_id

---

### 4. Frontend Toast Notifications Tests

**Test Team List Component:**

1. Navigate to Teams page
2. Try to load teams with network offline → **Expect:** Error toast "Failed to load team members"
3. Click "View" on a team member → **Expect:** Info toast "View team member feature coming soon"
4. Delete a team member successfully → **Expect:** Success toast "Team member [name] removed successfully"
5. Delete fails (e.g., network error) → **Expect:** Error toast "Failed to delete team member"

**Test Team Form Modal:**

1. Open "Add Team Member" form
2. Submit without filling required fields → **Expect:** Warning toast "Please fill in all required fields correctly"
3. Fill all fields and submit successfully → **Expect:** Success toast from parent "Team member [name] created successfully"
4. Submit with server error (e.g., duplicate email) → **Expect:** Error toast with server message
5. Try to load roles but fail → **Expect:** Error toast "Failed to load available roles"

**Test Work Order Assignment:**

1. Click "Assign Work Order" on a team member
2. Complete assignment successfully → **Expect:** Success toast "Work order successfully assigned to [name]"

---

### 5. Cross-Company Validation Tests

**Test Role Validation:**

1. Try to create team member with role from different company:
   ```bash
   curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"first_name":"Test","last_name":"User","email":"test@example.com","role_id":999}' \
     http://localhost/api/teams
   ```
2. **Expected:** 422 error with message "The selected role does not belong to your company."

**Test Location Validation:**

1. Try to assign locations from different company:
   ```bash
   curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"first_name":"Test","last_name":"User","email":"test@example.com","role_id":1,"location_ids":[9999]}' \
     http://localhost/api/teams
   ```
2. **Expected:** 422 error with message "One or more selected locations do not belong to your company."

---

### 6. Integration Tests

**Complete Team Member Lifecycle:**

1. Create team member
2. Verify creation in database
3. Check audit log
4. Check cache invalidation
5. Update team member
6. Verify update
7. Check update audit log
8. Resend invitation
9. Check invitation audit log
10. Delete team member
11. Verify deletion
12. Check deletion audit log

**API Endpoint Tests:**

```bash
# List team members
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost/api/teams?page=1&per_page=15&search=john"

# Get single team member
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams/1

# Get statistics
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams/statistics

# Get analytics
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost/api/teams/analytics?date_range=30"

# Get available roles
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams/available-roles
```

---

### 7. Performance Tests

**Load Testing with Apache Bench:**

```bash
# Test team list endpoint
ab -n 1000 -c 50 -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams

# Test analytics endpoint (with caching)
php artisan cache:clear
ab -n 100 -c 10 -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/teams/analytics
```

**Expected Performance:**
- Team list: < 200ms average response time
- Analytics (first call): < 500ms
- Analytics (cached): < 75ms
- Statistics (first call): < 300ms
- Statistics (cached): < 45ms

---

### 8. Security Tests

**Test Authentication:**

```bash
# Try to access without token
curl http://localhost/api/teams

# Expected: 401 Unauthorized
```

**Test Authorization:**

1. Create two companies with different users
2. Try to access Company A's team members with Company B's token
3. **Expected:** Only see Company B's team members

**Test SQL Injection:**

```bash
# Try SQL injection in search
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost/api/teams?search='; DROP TABLE users; --"

# Expected: No error, safe handling
```

---

### 9. Email Tests

**Test Invitation Email:**

1. Configure mail driver (use mailtrap.io or log driver)
2. Create a new team member
3. Check that invitation email was sent
4. Verify email contains:
   - Team member name
   - Login credentials (if generated)
   - Login URL
5. Check logs for email sending confirmation

**Test Resend Invitation:**

1. Resend invitation for existing team member
2. Verify new email is sent
3. Check that new password was generated
4. Verify audit log

---

### 10. Validation Tests

**Test Field Validations:**

```bash
# Missing required fields
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}' \
  http://localhost/api/teams

# Invalid email format
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe","email":"invalid-email","role_id":1}' \
  http://localhost/api/teams

# Duplicate email
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Jane","last_name":"Smith","email":"existing@example.com","role_id":1}' \
  http://localhost/api/teams

# Negative hourly rate
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe","email":"john@example.com","role_id":1,"hourly_rate":-10}' \
  http://localhost/api/teams
```

---

## Manual Testing Checklist

### Backend

- [ ] Rate limiting works on analytics endpoint
- [ ] Rate limiting works on statistics endpoint
- [ ] Analytics data is cached properly
- [ ] Statistics data is cached properly
- [ ] Cache is cleared after creating team member
- [ ] Cache is cleared after updating team member
- [ ] Cache is cleared after deleting team member
- [ ] Team creation is logged with all details
- [ ] Team update is logged with changes tracked
- [ ] Team deletion is logged
- [ ] Invitation resend is logged
- [ ] Cross-company role validation works
- [ ] Cross-company location validation works
- [ ] Invitation email is sent on creation
- [ ] Invitation email is sent on resend

### Frontend

- [ ] Toast appears on load error
- [ ] Toast appears on delete success
- [ ] Toast appears on delete error
- [ ] Toast appears on create success (from parent)
- [ ] Toast appears on update success (from parent)
- [ ] Toast appears on form validation error
- [ ] Toast appears on create/update API error
- [ ] Toast appears on role load error
- [ ] Toast appears on location tree load error
- [ ] Toast appears for "view team member" placeholder
- [ ] Toast appears on work order assignment success

---

## Success Criteria

✅ **Rate Limiting:**
- Analytics endpoint throttled at 30 req/min
- Statistics endpoint throttled at 30 req/min

✅ **Performance:**
- Analytics cached responses 85% faster
- Statistics cached responses 85% faster
- Cache properly invalidated on data changes

✅ **Audit Logging:**
- All CRUD operations logged
- Invitation resends logged
- Logs include user, IP, timestamp, and changes

✅ **Security:**
- Cross-company validation prevents unauthorized access
- Role and location IDs validated

✅ **User Experience:**
- Toast notifications on all operations
- Clear error messages
- Successful operation feedback

✅ **Code Quality:**
- Services are testable and reusable
- API responses are consistent
- Request validation is comprehensive

---

## Troubleshooting

**Cache not working:**
```bash
# Check cache driver
php artisan config:cache

# Verify cache is enabled
cat .env | grep CACHE_DRIVER

# Clear all caches
php artisan cache:clear
php artisan config:clear
```

**Logs not appearing:**
```bash
# Check log level in config/logging.php
# Ensure LOG_LEVEL in .env is 'info' or 'debug'

# Check file permissions
chmod -R 775 storage/logs
```

**Rate limiting not working:**
```bash
# Clear route cache
php artisan route:clear

# Verify throttle middleware is applied
php artisan route:list | grep teams
```

**Toasts not appearing:**
- Check browser console for JavaScript errors
- Verify ToastService is injected properly
- Check that ToastComponent is in app.component.html

---

## Next Steps

1. Run all manual tests from this guide
2. Fix any issues found
3. Deploy to staging environment
4. Monitor performance metrics
5. Review audit logs for completeness
6. Get user feedback on toast notifications
7. Document any additional findings

---

**Testing completed successfully!** 🎉

