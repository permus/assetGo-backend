# AssetGo API Postman Collection

This comprehensive Postman collection contains all the API endpoints for the AssetGo backend system. The collection is organized into logical groups and includes authentication, request examples, and environment variables.

## 📋 Collection Overview

### **Authentication**
- User registration and login
- Password management (forgot/reset)
- Profile management
- Logout functionality

### **Company Management**
- Company information CRUD
- Company settings (currency, logo upload)
- Module enablement/disablement
- User preferences

### **Asset Management**
- Complete asset CRUD operations
- Bulk operations (import/export/delete/archive)
- Asset analytics and reporting
- QR code and barcode generation
- Maintenance scheduling
- Asset hierarchy management

### **Location Management**
- Location CRUD operations
- Bulk location creation
- Location hierarchy
- QR code generation and export
- Location types management

### **Work Orders**
- Work order CRUD operations
- Status management
- Analytics and statistics
- Assignment management

### **Inventory Management**
- Parts catalog management
- Stock level management
- Purchase orders
- Suppliers management
- Analytics and reporting

### **Supporting Resources**
- Asset categories, types, and statuses
- Departments and roles
- Team management
- Public asset endpoints

## 🚀 Getting Started

### 1. Import the Collection
1. Open Postman
2. Click "Import" button
3. Select the `AssetGo-API-Collection.postman_collection.json` file
4. The collection will be imported with all endpoints organized

### 2. Set Up Environment Variables
The collection includes these variables that you can customize:

| Variable | Default Value | Description |
|----------|---------------|-------------|
| `base_url` | `http://assetgo-backend.test/api` | API base URL |
| `auth_token` | (empty) | Authentication token (auto-set on login) |
| `asset_id` | `1` | Sample asset ID for testing |
| `location_id` | `1` | Sample location ID for testing |
| `work_order_id` | `1` | Sample work order ID for testing |
| `part_id` | `1` | Sample part ID for testing |
| `supplier_id` | `1` | Sample supplier ID for testing |

### 3. Authentication Flow
1. **Register a new user** (optional):
   - Use the `Authentication > register` endpoint
   - Provide user details and company information

2. **Login**:
   - Use the `Authentication > login` endpoint
   - The auth token will be automatically set in the collection variable
   - All subsequent requests will use this token for authentication

3. **Start testing**:
   - All other endpoints will automatically use the stored auth token
   - You can now test any endpoint in the collection

## 📁 Collection Structure

```
AssetGo API Collection/
├── Authentication/
│   ├── register
│   ├── login (auto-sets auth_token)
│   ├── profile
│   ├── updateProfile
│   ├── changePassword
│   ├── forgotPassword
│   ├── resetPassword
│   ├── logout
│   └── logoutAll
├── Company/
│   ├── show
│   ├── update
│   └── users
├── Company Settings/
│   ├── updateCurrency
│   └── uploadLogo
├── Module Settings/
│   ├── index
│   ├── enableModule
│   └── disableModule
├── Preferences/
│   ├── show
│   └── update
├── Assets/
│   ├── Asset CRUD/
│   ├── Asset Import/Export/
│   ├── Asset Operations/
│   ├── Asset Bulk Operations/
│   ├── Asset Analytics & Reports/
│   ├── Asset Activities/
│   ├── Asset QR & Barcodes/
│   ├── Asset Maintenance/
│   └── Asset Hierarchy & Relations/
├── Locations/
│   ├── Location CRUD/
│   ├── Location Operations/
│   └── Location Helpers/
├── Asset Categories/
├── Asset Types/
├── Asset Statuses/
├── Departments/
├── Roles & Permissions/
├── Teams/
├── Work Orders/
│   ├── Work Order CRUD/
│   └── Work Order Operations/
├── Inventory/
│   ├── Parts/
│   ├── Stock Management/
│   └── Purchase Orders/
└── Public Assets/
```

## 🔧 Key Features

### **Auto-Authentication**
- Login endpoint automatically captures and stores the auth token
- All requests use the stored token for authentication
- No manual token management required

### **Environment Variables**
- Pre-configured with sensible defaults
- Easy to modify for different environments
- Supports both local development and production URLs

### **Comprehensive Coverage**
- All API endpoints included
- Request examples with realistic data
- Proper HTTP methods and headers
- Query parameters for filtering and pagination

### **Organized Structure**
- Logical grouping by functionality
- Clear naming conventions
- Easy to navigate and find specific endpoints

## 📝 Usage Examples

### Testing Asset Management
1. Login to get authentication token
2. Create a location: `Locations > Location CRUD > store`
3. Create an asset category: `Asset Categories > store`
4. Create an asset: `Assets > Asset CRUD > store`
5. View asset analytics: `Assets > Asset Analytics & Reports > analytics`

### Testing Bulk Operations
1. Login first
2. Use bulk location creation: `Locations > Location Operations > bulkCreate`
3. Import assets from Excel: `Assets > Asset Import/Export > bulkImportAssetsFromExcel`
4. Check import progress: `Assets > Asset Import/Export > importProgress`

### Testing Work Orders
1. Login first
2. Create a work order: `Work Orders > Work Order CRUD > store`
3. Update work order status: `Work Orders > Work Order Operations > updateStatus`
4. View work order analytics: `Work Orders > Work Order Operations > analytics`

## 🛠️ Customization

### Changing Base URL
Update the `base_url` variable to point to your server:
- Local development: `http://localhost:8000/api`
- Staging: `https://staging.assetgo.com/api`
- Production: `https://api.assetgo.com/api`

### Adding New Endpoints
1. Right-click on the appropriate folder
2. Select "Add Request"
3. Configure the request details
4. Add to the collection

### Modifying Request Data
All request bodies include example data that you can modify:
- Update IDs to match your data
- Change field values as needed
- Add or remove optional parameters

## 🔍 Troubleshooting

### Common Issues

**401 Unauthorized**
- Ensure you've logged in first
- Check that the auth token is set
- Verify the token hasn't expired

**404 Not Found**
- Check the base URL is correct
- Verify the endpoint path
- Ensure the resource ID exists

**422 Validation Error**
- Check the request body format
- Verify required fields are provided
- Check field validation rules

**500 Server Error**
- Check server logs
- Verify database connection
- Contact development team

### Getting Help
- Check the API documentation in the `/docs` folder
- Review the Laravel routes in `routes/api.php`
- Check the controller methods for expected parameters

## 📊 Testing Workflow

### Recommended Testing Order
1. **Authentication** - Login and verify token
2. **Company Setup** - Create/update company details
3. **Location Setup** - Create locations and hierarchy
4. **Asset Categories** - Set up asset categories and types
5. **Asset Management** - Create and manage assets
6. **Work Orders** - Test work order functionality
7. **Inventory** - Test inventory management
8. **Analytics** - Verify reporting endpoints

### Bulk Testing
- Use the bulk creation endpoints for setting up test data
- Test import/export functionality
- Verify pagination and filtering

## 🎯 Best Practices

1. **Always login first** before testing other endpoints
2. **Use realistic test data** that matches your business requirements
3. **Test error scenarios** by sending invalid data
4. **Verify responses** match expected format
5. **Clean up test data** after testing
6. **Use environment variables** for different testing environments

---

This collection provides a complete testing environment for the AssetGo API. All endpoints are pre-configured with appropriate headers, authentication, and example data to get you started quickly.
