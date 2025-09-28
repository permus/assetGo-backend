# Reports Module Testing Documentation

This document provides comprehensive information about testing the Reports Module, including unit tests, feature tests, and E2E tests.

## 📋 Table of Contents

1. [Overview](#overview)
2. [Test Structure](#test-structure)
3. [Running Tests](#running-tests)
4. [Test Categories](#test-categories)
5. [Test Data Setup](#test-data-setup)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)
8. [Continuous Integration](#continuous-integration)

## 🎯 Overview

The Reports Module testing suite ensures that all functionality works correctly across different layers:

- **Unit Tests**: Test individual services, controllers, and components in isolation
- **Feature Tests**: Test API endpoints and their integration with the database
- **E2E Tests**: Test complete user workflows from start to finish

## 🏗️ Test Structure

```
tests/
├── Unit/Reports/
│   ├── AssetReportServiceTest.php
│   ├── MaintenanceReportServiceTest.php
│   ├── ReportExportServiceTest.php
│   └── AssetReportControllerTest.php
├── Feature/Reports/
│   ├── AssetReportsApiTest.php
│   └── ExportApiTest.php
├── E2E/Reports/
│   └── ReportsModuleE2ETest.php
└── ReportsTestSuite.php

assetGo-frontend/src/app/reports/
├── services/
│   ├── reports-api.service.spec.ts
│   └── export.service.spec.ts
└── components/
    ├── reports-header.component.spec.ts
    └── reports-kpi-cards.component.spec.ts
```

## 🚀 Running Tests

### Backend Tests

#### Run All Reports Tests
```bash
# Using PHPUnit
php artisan test --testsuite=Reports

# Using custom test runner
php run_reports_tests.php

# Using PHPUnit directly
./vendor/bin/phpunit tests/ReportsTestSuite.php
```

#### Run Specific Test Categories
```bash
# Unit tests only
php artisan test tests/Unit/Reports/

# Feature tests only
php artisan test tests/Feature/Reports/

# E2E tests only
php artisan test tests/E2E/Reports/
```

#### Run Individual Test Files
```bash
# Asset report service tests
php artisan test tests/Unit/Reports/AssetReportServiceTest.php

# Export API tests
php artisan test tests/Feature/Reports/ExportApiTest.php

# Complete workflow tests
php artisan test tests/E2E/Reports/ReportsModuleE2ETest.php
```

### Frontend Tests

#### Run Angular Tests
```bash
# Run all tests
ng test

# Run tests in watch mode
ng test --watch

# Run tests with coverage
ng test --code-coverage

# Run specific test files
ng test --include="**/reports/**/*.spec.ts"
```

#### Run Specific Component Tests
```bash
# Test reports API service
ng test --include="**/reports-api.service.spec.ts"

# Test export service
ng test --include="**/export.service.spec.ts"

# Test header component
ng test --include="**/reports-header.component.spec.ts"
```

## 📊 Test Categories

### 1. Unit Tests

#### Backend Unit Tests
- **AssetReportServiceTest**: Tests asset report generation logic
- **MaintenanceReportServiceTest**: Tests maintenance report generation logic
- **ReportExportServiceTest**: Tests export functionality
- **AssetReportControllerTest**: Tests controller methods and error handling

#### Frontend Unit Tests
- **reports-api.service.spec.ts**: Tests API service methods
- **export.service.spec.ts**: Tests export service functionality
- **reports-header.component.spec.ts**: Tests header component behavior
- **reports-kpi-cards.component.spec.ts**: Tests KPI cards component

### 2. Feature Tests

#### API Endpoint Tests
- **AssetReportsApiTest**: Tests all asset report API endpoints
- **ExportApiTest**: Tests export-related API endpoints

#### Test Coverage
- Authentication and authorization
- Request validation
- Response formatting
- Error handling
- Rate limiting
- Company isolation

### 3. E2E Tests

#### Complete Workflow Tests
- **ReportsModuleE2ETest**: Tests end-to-end user workflows

#### Test Scenarios
- Full reports workflow from data setup to export
- Asset reports workflow
- Maintenance reports workflow
- Export workflow
- Filtering and pagination
- Error handling
- Company isolation
- Performance testing
- Concurrent requests
- Rate limiting

## 🗄️ Test Data Setup

### Backend Test Data

#### Factories Used
```php
// Company factory
Company::factory()->create();

// User factory
User::factory()->create(['company_id' => $company->id]);

// Asset factory
Asset::factory()->create([
    'company_id' => $company->id,
    'location_id' => $location->id,
    'status' => 'active'
]);

// WorkOrder factory
WorkOrder::factory()->create([
    'company_id' => $company->id,
    'status_id' => $status->id,
    'priority_id' => $priority->id
]);

// ReportRun factory
ReportRun::factory()->create([
    'company_id' => $company->id,
    'user_id' => $user->id
]);
```

#### Test Data Scenarios
- **Small Dataset**: 5-10 records for basic functionality
- **Medium Dataset**: 100-500 records for pagination testing
- **Large Dataset**: 1000+ records for performance testing
- **Multi-Company**: Data for different companies to test isolation

### Frontend Test Data

#### Mock Data
```typescript
// Mock API responses
const mockResponse = {
  success: true,
  data: {
    rows: [],
    totals: {},
    pagination: {}
  }
};

// Mock KPI data
const mockKPIs = [
  {
    key: 'total_count',
    label: 'Total Count',
    value: 100,
    format: 'number',
    icon: 'package',
    color: 'blue'
  }
];
```

## ✅ Best Practices

### Backend Testing

#### 1. Test Isolation
- Use `RefreshDatabase` trait for database tests
- Create fresh data for each test
- Use factories for consistent test data

#### 2. Authentication
- Use `Sanctum::actingAs()` for authenticated tests
- Test both authenticated and unauthenticated scenarios

#### 3. Error Handling
- Test both success and failure scenarios
- Verify proper error messages and status codes
- Test edge cases and invalid inputs

#### 4. Performance
- Test with realistic data volumes
- Measure execution times
- Test memory usage

### Frontend Testing

#### 1. Component Testing
- Test component initialization
- Test user interactions
- Test data binding
- Test error states

#### 2. Service Testing
- Mock HTTP requests
- Test error handling
- Test data transformation
- Test utility methods

#### 3. Integration Testing
- Test component-service integration
- Test API communication
- Test state management

### General Best Practices

#### 1. Test Naming
- Use descriptive test names
- Follow the pattern: "it should [expected behavior] when [condition]"
- Group related tests in describe blocks

#### 2. Test Structure
- Arrange: Set up test data
- Act: Execute the code being tested
- Assert: Verify the results

#### 3. Test Coverage
- Aim for high test coverage
- Focus on critical business logic
- Test edge cases and error conditions

## 🔧 Troubleshooting

### Common Issues

#### 1. Database Issues
```bash
# Clear test database
php artisan migrate:fresh --env=testing

# Run migrations
php artisan migrate --env=testing

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### 2. Authentication Issues
```bash
# Check Sanctum configuration
php artisan config:show sanctum

# Clear token cache
php artisan cache:clear

# Check user tokens
php artisan tinker
>>> User::find(1)->tokens;
```

#### 3. Frontend Test Issues
```bash
# Clear Angular cache
ng cache clean

# Reinstall dependencies
npm install

# Check test configuration
ng test --help
```

#### 4. Memory Issues
```bash
# Increase memory limit
php -d memory_limit=512M artisan test

# Check PHP configuration
php -i | grep memory_limit
```

### Debug Mode

#### Enable Debug Output
```bash
# Run tests with verbose output
php artisan test --verbose

# Run specific test with debug
php artisan test tests/Unit/Reports/AssetReportServiceTest.php --debug
```

#### Check Test Results
```bash
# Generate test report
php artisan test --coverage-html coverage/

# Check test logs
tail -f storage/logs/laravel.log
```

## 🔄 Continuous Integration

### GitHub Actions

#### Backend Tests
```yaml
name: Reports Module Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_sqlite
        
    - name: Install dependencies
      run: composer install --no-interaction --prefer-dist
      
    - name: Setup environment
      run: |
        cp .env.example .env
        php artisan key:generate
        
    - name: Run tests
      run: php artisan test --testsuite=Reports
```

#### Frontend Tests
```yaml
name: Frontend Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup Node.js
      uses: actions/setup-node@v2
      with:
        node-version: '18'
        
    - name: Install dependencies
      run: npm ci
      
    - name: Run tests
      run: ng test --watch=false --browsers=ChromeHeadless
```

### Test Reports

#### Generate Coverage Reports
```bash
# Backend coverage
php artisan test --coverage-html coverage/

# Frontend coverage
ng test --code-coverage
```

#### Test Metrics
- **Coverage**: Aim for >90% code coverage
- **Performance**: Tests should complete within 5 minutes
- **Reliability**: Tests should pass consistently
- **Maintainability**: Tests should be easy to understand and modify

## 📈 Test Metrics

### Success Criteria
- ✅ All unit tests pass
- ✅ All feature tests pass
- ✅ All E2E tests pass
- ✅ Code coverage >90%
- ✅ No memory leaks
- ✅ Performance within acceptable limits

### Monitoring
- Track test execution time
- Monitor test failure rates
- Track code coverage trends
- Monitor test maintenance effort

## 🎯 Conclusion

The Reports Module testing suite provides comprehensive coverage of all functionality, ensuring reliability and maintainability. Regular testing helps catch issues early and provides confidence in the system's stability.

For questions or issues with testing, please refer to the troubleshooting section or contact the development team.
