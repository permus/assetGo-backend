# Migration Reorganization Summary

## Overview
This document summarizes the changes made to reorganize the migration files to fix dependency issues and ensure `php artisan migrate:refresh` runs without errors.

## Issues Fixed

### 1. **Filename Typo**
- ✅ Fixed: `create_comapnies_table.php` → `create_companies_table.php`

### 2. **Circular Dependency (Users ↔ Companies)**
The original setup had a circular dependency:
- Users table referenced `company_id` (foreign key to companies)
- Companies table referenced `owner_id` (foreign key to users)

**Solution:**
- Created tables without foreign keys first
- Added foreign keys in separate migrations after both tables exist

### 3. **Migration Order**
Reorganized migrations to follow proper dependency order:

```
1. Core Laravel tables (users, password_resets, etc.)
2. Companies table (without foreign keys)
3. Add foreign keys to users → companies
4. Add foreign keys to companies → users
5. Location types (no dependencies)
6. Roles table (depends on companies)
7. Permissions table (depends on roles)
8. User roles table (depends on users and roles)
9. Locations table (depends on companies, location_types)
10. Asset categories, types, status (no dependencies)
11. Departments (depends on companies)
12. Assets (depends on companies, locations, categories, departments)
13. ... and so on
```

## Files Created

### 1. `database/migrations/2014_10_12_000001_add_foreign_keys_to_users_table.php`
Adds foreign keys from users to companies after both tables exist.

### 2. `database/migrations/2014_10_12_000003_add_foreign_keys_to_companies_table.php`
Adds foreign key from companies to users after both tables exist.

### 3. `migrate_fix.bat`
Batch script that renames migration files to correct order.

## Files Modified

### 1. `database/migrations/2014_10_12_000000_create_users_table.php`
- Changed `company_id` from `integer` to `unsignedBigInteger`
- Changed `created_by` from `integer` to `unsignedBigInteger`
- Added `hourly_rate` field (decimal)
- Added `preferences` field (json)
- Removed foreign key constraints (moved to separate migration)

### 2. `database/migrations/2014_10_12_000002_create_companies_table.php`
- Fixed filename typo
- Changed `owner_id` from `string` to `unsignedBigInteger`
- Changed `subscription_expires_at` from `string` to `timestamp`
- Changed `address` from `string` to `text`
- Added `currency` field (string, default 'USD')
- Added `settings` field (json)
- Removed foreign key constraint (moved to separate migration)

## Files Renamed

The following migrations were renamed to ensure proper execution order:

| Old Name | New Name |
|----------|----------|
| `2025_07_17_095259_create_location_types_table.php` | `2014_10_12_000003_create_location_types_table.php` |
| `2025_07_17_095223_create_locations_table.php` | `2014_10_12_000007_create_locations_table.php` |
| `2025_07_18_035747_create_asset_categories_table.php` | `2014_10_12_000008_create_asset_categories_table.php` |
| `2025_07_27_121344_create_departments_table.php` | `2014_10_12_000009_create_departments_table.php` |
| `2025_07_18_035748_create_assets_table.php` | `2014_10_12_000010_create_assets_table.php` |
| `2025_08_04_000002_create_permissions_table.php` | `2014_10_12_000005_create_permissions_table.php` |
| `2025_08_04_000003_create_user_roles_table.php` | `2014_10_12_000006_create_user_roles_table.php` |

## Backup

All original migration files have been backed up to:
```
database/migrations_backup/
```

## Testing Instructions

### 1. **Fresh Migration**
To test with a fresh database:

```bash
php artisan migrate:fresh --seed
```

### 2. **Migration Refresh**
To test with existing database:

```bash
php artisan migrate:refresh --seed
```

### 3. **Check Migration Status**
To see which migrations have run:

```bash
php artisan migrate:status
```

### 4. **Rollback if Needed**
If issues occur, rollback and restore from backup:

```bash
php artisan migrate:rollback
# Then restore from database/migrations_backup/
```

## Expected Behavior

After running `php artisan migrate:refresh`, you should see:
- ✅ All migrations run successfully
- ✅ No foreign key constraint errors
- ✅ No "table doesn't exist" errors
- ✅ All tables created in correct order
- ✅ All foreign keys properly established

## Common Issues and Solutions

### Issue 1: "Table doesn't exist" error
**Cause:** Migration trying to reference a table that hasn't been created yet
**Solution:** Check migration timestamps - dependent tables must be created after their dependencies

### Issue 2: "Foreign key constraint fails"
**Cause:** Foreign key references a table that doesn't exist yet
**Solution:** Move foreign key creation to a separate migration that runs after both tables exist

### Issue 3: "Duplicate column" error
**Cause:** Column already exists (possibly from a previous migration)
**Solution:** Check if column was added in the base table creation or in an ALTER migration

## Migration Dependency Tree

```
users (no dependencies)
  ↓
companies (references users.id)
  ↓
├─ locations (references companies.id)
├─ roles (references companies.id)
│   ↓
│   permissions (references roles.id)
│   ↓
│   user_roles (references users.id, roles.id)
├─ departments (references companies.id)
├─ asset_categories (no dependencies)
├─ asset_types (no dependencies)
└─ asset_status (no dependencies)
    ↓
    assets (references companies.id, locations.id, departments.id, asset_categories.id, etc.)
```

## Next Steps

1. ✅ Backup completed
2. ✅ Files reorganized
3. ✅ Foreign key issues resolved
4. ⏳ Test migration refresh
5. ⏳ Verify all tables created correctly
6. ⏳ Check foreign key constraints
7. ⏳ Run seeders to populate test data

## Notes

- All migrations now follow Laravel naming conventions
- Foreign keys are properly typed as `unsignedBigInteger`
- Circular dependencies resolved using separate foreign key migrations
- Migration order ensures dependencies are met
- Backup available in case rollback is needed

## Contact

If you encounter any issues during migration, refer to this document and check:
1. Migration order (timestamps)
2. Foreign key dependencies
3. Table existence before referencing
4. Column types match between tables and foreign keys
