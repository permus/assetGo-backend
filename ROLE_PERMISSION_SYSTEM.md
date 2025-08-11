# Role and Permission System Documentation

## Overview

This document describes the complete role and permission system implemented for the AssetGo backend. The system allows for granular permission control across different modules with JSON-based permission storage.

## Database Structure

### Tables

1. **roles** - Stores role information
   - `id` (Primary Key)
   - `name` (String) - Role name (unique per company)
   - `description` (Text) - Role description
   - `company_id` (Foreign Key) - Links to companies table
   - `created_at`, `updated_at` (Timestamps)

2. **permissions** - Stores permission data for each role
   - `id` (Primary Key)
   - `role_id` (Foreign Key) - Links to roles table
   - `permissions` (JSON) - Permission configuration
   - `created_at`, `updated_at` (Timestamps)

3. **user_roles** - Pivot table for many-to-many relationship between users and roles
   - `id` (Primary Key)
   - `user_id` (Foreign Key) - Links to users table
   - `role_id` (Foreign Key) - Links to roles table
   - `created_at`, `updated_at` (Timestamps)

### Relationships

- **Company** has many **Roles**
- **Role** belongs to **Company**
- **Role** has one **Permission**
- **User** belongs to many **Roles** (through user_roles table)
- **Role** belongs to many **Users** (through user_roles table)

## Permission Structure

Permissions are stored as JSON with the following structure:

```json
{
  "location": {
    "can_view": true,
    "can_create": false,
    "can_edit": false,
    "can_delete": false,
    "can_export": true
  },
  "assets": {
    "can_view": true,
    "can_create": true,
    "can_edit": true,
    "can_delete": false,
    "can_export": true
  },
  "users": {
    "can_view": false,
    "can_create": false,
    "can_edit": false,
    "can_delete": false,
    "can_export": false
  },
  "roles": {
    "can_view": false,
    "can_create": false,
    "can_edit": false,
    "can_delete": false,
    "can_export": false
  },
  "reports": {
    "can_view": true,
    "can_create": false,
    "can_edit": false,
    "can_delete": false,
    "can_export": false
  }
}
```

### Available Modules

- **location** - Location management
- **assets** - Asset management
- **users** - User management
- **roles** - Role and permission management
  - **reports** - Report generation and viewing
  - **inventory_parts** - Parts catalog
  - **inventory_stock** - Stock levels, adjustments, transfers
  - **inventory_transactions** - Movement log exports
  - **inventory_pos** - Purchase orders and receiving

### Available Actions

- **can_view** - View/read access
- **can_create** - Create new records
- **can_edit** - Edit existing records
- **can_delete** - Delete records
- **can_export** - Export data

## Models

### Role Model

```php
App\Models\Role
```

**Key Methods:**
- `company()` - Get the company that owns the role
- `users()` - Get users that have this role
- `permissions()` - Get the permissions for this role
- `hasPermission($module, $action)` - Check if role has specific permission
- `getAllPermissions()` - Get all permissions for the role

### Permission Model

```php
App\Models\Permission
```

**Key Methods:**
- `role()` - Get the role that owns the permission
- `setModulePermissions($module, $permissions)` - Set permissions for a module
- `getModulePermissions($module)` - Get permissions for a module
- `hasPermission($module, $action)` - Check if permission exists

### User Model (Updated)

**New Methods:**
- `roles()` - Get the roles that the user has
- `hasRole($roleName)` - Check if user has a specific role
- `hasPermission($module, $action)` - Check if user has permission through any role
- `getAllPermissions()` - Get all permissions for the user through their roles

## API Endpoints

### Role Management

#### Get All Roles
```
GET /api/roles
```
Returns all roles for the authenticated user's company.

#### Create Role
```
POST /api/roles
```
**Body:**
```json
{
  "name": "Technician",
  "description": "Can view and edit assets",
  "permissions": {
    "location": {
      "can_view": true,
      "can_create": false,
      "can_edit": false,
      "can_delete": false,
      "can_export": false
    },
    "assets": {
      "can_view": true,
      "can_create": false,
      "can_edit": true,
      "can_delete": false,
      "can_export": false
    }
  }
}
```

#### Get Role Details
```
GET /api/roles/{id}
```

#### Update Role
```
PUT /api/roles/{id}
```

#### Delete Role
```
DELETE /api/roles/{id}
```

#### Get Available Permissions
```
GET /api/roles/available-permissions
```
Returns the structure of available permissions.

### Role Assignment

#### Assign Role to User
```
POST /api/roles/assign-to-user
```
**Body:**
```json
{
  "user_id": 1,
  "role_id": 2
}
```

#### Remove Role from User
```
POST /api/roles/remove-from-user
```
**Body:**
```json
{
  "user_id": 1,
  "role_id": 2
}
```

## Middleware

### CheckPermission Middleware

```php
App\Http\Middleware\CheckPermission
```

**Usage in routes:**
```php
Route::middleware('permission:assets,can_create')->post('/assets', [AssetController::class, 'store']);
// Inventory examples
Route::middleware('permission:inventory_parts,can_create')->post('/inventory/parts', [InventoryPartController::class, 'store']);
Route::middleware('permission:inventory_stock,can_edit')->post('/inventory/stocks/adjust', [InventoryStockController::class, 'adjust']);
Route::middleware('permission:inventory_pos,can_edit')->post('/inventory/purchase-orders', [InventoryPOController::class, 'store']);
```

**Parameters:**
- `$module` - The module to check (e.g., 'assets', 'location')
- `$action` - The action to check (e.g., 'can_view', 'can_create')

## Helper Trait

### HasPermissions Trait

```php
App\Traits\HasPermissions
```

**Methods:**
- `checkPermission($module, $action)` - Check if user has permission
- `permissionDenied($message)` - Return permission denied response
- `requirePermission($module, $action)` - Check permission and return response if denied
- `getUserPermissions()` - Get all permissions for the user
- `hasAnyPermission($permissions)` - Check if user has any of the specified permissions
- `hasAllPermissions($permissions)` - Check if user has all of the specified permissions

**Usage in Controllers:**
```php
use App\Traits\HasPermissions;

class AssetController extends Controller
{
    use HasPermissions;

    public function store(Request $request)
    {
        if ($denied = $this->requirePermission('assets', 'can_create')) {
            return $denied;
        }
        
        // Continue with asset creation
    }
}
```

## Default Roles

The system comes with four default roles:

### 1. Administrator
- **Description:** Full access to all features and settings
- **Permissions:** All permissions set to `true`

### 2. Manager
- **Description:** Can manage assets and locations, limited user management
- **Permissions:**
  - Location: view, create, edit, export (no delete)
  - Assets: view, create, edit, export (no delete)
  - Users: view only
  - Roles: no access
  - Reports: view and export

### 3. Technician
- **Description:** Can view and edit assets, limited location access
- **Permissions:**
  - Location: view only
  - Assets: view and edit
  - Users: no access
  - Roles: no access
  - Reports: view only

### 4. Viewer
- **Description:** Read-only access to assets and locations
- **Permissions:**
  - Location: view only
  - Assets: view only
  - Users: no access
  - Roles: no access
  - Reports: view only

## Seeding

### RoleSeeder

The `RoleSeeder` creates default roles for each company in the system. To run:

```bash
php artisan db:seed --class=RoleSeeder
```

Or run all seeders:

```bash
php artisan db:seed
```

## Factories

### RoleFactory

```php
// Create a basic role
$role = Role::factory()->create();

// Create an administrator role
$role = Role::factory()->administrator()->create();

// Create a role with specific permissions
$role = Role::factory()->withPermissions([
    'assets' => [
        'can_view' => true,
        'can_create' => false,
        'can_edit' => true,
        'can_delete' => false,
        'can_export' => false,
    ]
])->create();
```

### PermissionFactory

```php
// Create permissions for a role
$permissions = Permission::factory()->create();

// Create administrator permissions
$permissions = Permission::factory()->administrator()->create();

// Create technician permissions
$permissions = Permission::factory()->technician()->create();
```

## Usage Examples

### Checking Permissions in Controllers

```php
public function index(Request $request)
{
    if (!$this->checkPermission('assets', 'can_view')) {
        return $this->permissionDenied('You cannot view assets');
    }
    
    // Continue with asset listing
}

public function store(Request $request)
{
    if ($denied = $this->requirePermission('assets', 'can_create')) {
        return $denied;
    }
    
    // Continue with asset creation
}
```

### Using Middleware in Routes

```php
// Require specific permission
Route::middleware('permission:assets,can_create')->post('/assets', [AssetController::class, 'store']);

// Require multiple permissions (use middleware multiple times)
Route::middleware(['permission:assets,can_view', 'permission:assets,can_edit'])
    ->put('/assets/{id}', [AssetController::class, 'update']);
```

### Checking User Roles

```php
// Check if user has a specific role
if ($user->hasRole('Administrator')) {
    // User is an administrator
}

// Check if user has permission
if ($user->hasPermission('assets', 'can_create')) {
    // User can create assets
}

// Get all user permissions
$permissions = $user->getAllPermissions();
```

## Migration Commands

To create the database tables:

```bash
php artisan migrate
```

To rollback the role and permission tables:

```bash
php artisan migrate:rollback --step=3
```

## Testing

The system includes factories for testing:

```php
// Create a user with a role
$user = User::factory()->create();
$role = Role::factory()->technician()->create();
$user->roles()->attach($role);

// Test permission checking
$this->assertTrue($user->hasPermission('assets', 'can_view'));
$this->assertFalse($user->hasPermission('assets', 'can_delete'));
```

## Security Considerations

1. **Company Isolation:** Roles are scoped to companies, ensuring users can only access roles within their company
2. **Permission Validation:** All permission checks are validated at the database level
3. **Middleware Protection:** Routes can be protected with permission middleware
4. **JSON Validation:** Permission JSON structure is validated before storage
5. **Cascade Deletion:** When a role is deleted, its permissions are also deleted
6. **Unique Constraints:** Role names are unique per company

## Best Practices

1. **Always check permissions** before performing actions
2. **Use middleware** for route-level protection
3. **Validate permissions** in controllers for additional security
4. **Use the HasPermissions trait** for consistent permission checking
5. **Test permission scenarios** thoroughly
6. **Document custom permissions** when adding new modules
7. **Use descriptive role names** and descriptions
8. **Regularly audit permissions** to ensure they match business requirements 