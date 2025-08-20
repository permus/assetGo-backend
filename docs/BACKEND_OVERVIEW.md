## Backend (Laravel) Overview

This document summarizes the Laravel backend structure and key modules.

### Tech stack
- Laravel (Sanctum for auth, Eloquent ORM, Form Requests)
- MySQL
- Queues/Jobs for background tasks (e.g., asset import)

### Project layout
- `app/Http/Controllers/Api/`: REST controllers
  - `WorkOrderController.php`: CRUD, analytics, statistics, status updates
  - Inventory controllers under `Api/Inventory/`
- `app/Http/Requests/`: Validation
  - `WorkOrder/StoreWorkOrderRequest.php`, `WorkOrder/UpdateWorkOrderRequest.php`
  - Meta requests for statuses/priorities/categories
- `app/Models/`: Eloquent models
  - `WorkOrder`, `WorkOrderStatus`, `WorkOrderPriority`, `WorkOrderCategory`
  - `WorkOrderPart`, `WorkOrderTimeLog`, `WorkOrderComment`, `WorkOrderAssignment`
- `app/Http/Resources/`: Resources for metadata
- `database/migrations/`: Schema
- `database/seeders/`: Seed data (statuses, priorities, categories)
- `routes/api.php`: All API routes (protected by `auth:sanctum`, `verified`)

### Work Orders domain
Schema (modernized):
- `work_orders` uses foreign keys: `status_id`, `priority_id`, `category_id`
- Legacy enum columns (`status`, `priority`) are removed by migration `2026_08_20_000001_drop_legacy_status_and_priority_from_work_orders.php`

Model: `app/Models/WorkOrder.php`
- Fillable fields include `status_id`, `priority_id`, `category_id`, `due_date`, `assigned_to`, `estimated_hours`, `notes`, `meta`, ...
- Casts dates and numeric decimals; appends derived attributes: `is_overdue`, `days_until_due`, `days_since_created`, `resolution_time_days`
- Relationships: `status`, `priority`, `category`, `asset`, `location`, `assignedTo`, `assignedBy`, `createdBy`, `comments`, `assignments`
- Boot hooks: sets `created_by`; updates `completed_at` when status changes to `completed`

Controller: `app/Http/Controllers/Api/WorkOrderController.php`
- `index` supports searching, filtering by FK IDs, sorting, pagination; eager-loads `status`, `priority`, `category`
- `store` expects validated input and returns the created work order with relationships
- `updateStatus` changes only `status_id`
- `analytics` and `statistics` aggregate using FK tables and slugs; fully qualifies columns to avoid ambiguity

Validation
- `StoreWorkOrderRequest` requires `title`, `priority_id`, `status_id` and validates optional fields
- `UpdateWorkOrderRequest` supports partial updates using the same FK-based schema

Routes
- `routes/api.php` includes:
  - `Route::apiResource('work-orders', WorkOrderController::class)`
  - `work-orders/{workOrder}/status`, `.../history`, `.../assignments`, `.../parts`, `.../time-logs`
  - Meta routes under `meta/work-orders` for status/priority/category management

### Inventory module
- Controllers under `app/Http/Controllers/Api/Inventory/` implement parts, stock, suppliers, purchase orders, and analytics
- Analytics endpoints (e.g., `inventory/analytics/dashboard`, `inventory/analytics/abc-analysis`) scan inventory data with aggregate queries

### Authentication & Authorization
- `auth:sanctum` middleware protects API routes; `verified` enforces email verification for most endpoints
- Policies (e.g., `WorkOrderStatusPolicy`) protect meta object management

### Testing & Factories
- Feature tests under `tests/Feature/`
- `database/factories/WorkOrderFactory.php` now uses FK IDs and supports state helpers for statuses/priorities

### Conventions & Migration guidance
- Use `*_id` FK fields for status/priority/category
- Include relationships in responses to provide slugs/names to the frontend
- When adding analytics, use joins to metadata tables and qualify columns (`work_orders.company_id`, `work_orders.created_at`, etc.)

### Running locally
Typical commands:
```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Queue worker (if needed):
```bash
php artisan queue:work
```


