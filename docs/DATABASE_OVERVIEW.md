## Database Overview

This document describes the database design used by the application (MySQL). It focuses on the core domains, relationships, conventions, and operational guidance.

### Engine and charset
- MySQL/InnoDB
- Charset/collation typically `utf8mb4`/`utf8mb4_unicode_ci`
- Most business tables support soft deletes

### Naming conventions
- Tables and columns use `snake_case`
- Surrogate primary keys are `id` (`BIGINT UNSIGNED AUTO_INCREMENT`)
- Foreign key columns end in `_id` (e.g., `status_id`)
- Metadata tables carry a `slug` for stable, code-friendly identifiers

### Core domains

#### Work Orders
Tables:
- `work_orders`
  - Keys: `id`, `company_id`, `created_by`, `assigned_by?`, `assigned_to?`
  - Foreign keys (by convention): `status_id`, `priority_id`, `category_id`
  - Scheduling: `due_date`, `completed_at`
  - Estimation: `estimated_hours`, `actual_hours`
  - Context: `asset_id?`, `location_id?`, `notes`, `meta (JSON)`
  - Soft deletes enabled
- `work_order_status` (metadata)
  - `id`, `name`, `slug`, `is_management` (boolean), `company_id?`, `sort`
  - Seeded defaults include: `draft`, `open`, `in-progress`, `completed`, `on-hold`, `cancelled`
- `work_order_priority` (metadata)
  - `id`, `name`, `slug`, `is_management` (boolean), `company_id?`, `sort`
  - Seeded defaults include: `low`, `medium`, `high`, `critical`, `ppm`
- `work_order_categories` (metadata)
  - `id`, `name`, `slug`, `company_id?`, `sort`
- `work_order_comments` (activity/comments)
- `work_order_time_logs` (time tracking)
- `work_order_assignments` (assigned users list)
- `work_order_parts` (parts reserved/consumed)

Recent change:
- Legacy enum columns `status` and `priority` on `work_orders` were removed in favor of `status_id` and `priority_id`. See migration `2026_08_20_000001_drop_legacy_status_and_priority_from_work_orders.php`.

ERD (core work orders):

```mermaid
erDiagram
  work_orders }o--|| work_order_status : "status_id"
  work_orders }o--|| work_order_priority : "priority_id"
  work_orders }o--o| work_order_categories : "category_id"
  work_orders }o--o| assets : "asset_id"
  work_orders }o--o| locations : "location_id"
  work_orders ||--o{ work_order_comments : "id -> work_order_id"
  work_orders ||--o{ work_order_time_logs : "id -> work_order_id"
  work_orders ||--o{ work_order_assignments : "id -> work_order_id"
  work_orders ||--o{ work_order_parts : "id -> work_order_id"

  work_order_status {
    BIGINT id PK
    VARCHAR name
    VARCHAR slug
    BOOLEAN is_management
    BIGINT company_id NULL
    INT sort
  }
  work_order_priority {
    BIGINT id PK
    VARCHAR name
    VARCHAR slug
    BOOLEAN is_management
    BIGINT company_id NULL
    INT sort
  }
  work_orders {
    BIGINT id PK
    BIGINT company_id
    BIGINT created_by
    BIGINT assigned_by NULL
    BIGINT assigned_to NULL
    BIGINT status_id
    BIGINT priority_id
    BIGINT category_id NULL
    BIGINT asset_id NULL
    BIGINT location_id NULL
    DATETIME due_date NULL
    DATETIME completed_at NULL
    DECIMAL(8,2) estimated_hours NULL
    DECIMAL(8,2) actual_hours NULL
    TEXT notes NULL
    JSON meta NULL
    DATETIME created_at
    DATETIME updated_at
    DATETIME deleted_at NULL
  }
```

Notes:
- Some relationships (e.g., `status_id`, `priority_id`) are enforced at the application layer; foreign key constraints may be added in future migrations.
- Derived attributes like `is_overdue` are computed in the `WorkOrder` model.

#### Inventory
Key tables (high level):
- `inventory_parts`
  - Master data for spare parts; fields include `sku/barcode`, `status`, `abc_class`, `average_cost`, etc.
- `inventory_stocks`
  - On-hand quantities per location/part; used in analytics (e.g., ABC, valuation)
- `inventory_transactions`
  - Movements (receive, issue, transfer, adjust)
- `purchase_orders`, `purchase_order_items`
  - Ordering lifecycle with statuses such as `draft`, `pending`, `approved`, `ordered`, `received`, `closed`, `rejected`

Typical relations:
- `purchase_orders` 1—N `purchase_order_items`
- `inventory_parts` 1—N `inventory_transactions` and `inventory_stocks`

#### Assets & Locations
- `assets` with categories, statuses, parent-child hierarchy
- `locations` with hierarchy; assets and work orders can reference locations

#### Users & Companies
- `users` belong to a `company`; many domain tables carry `company_id`
- Team members are users with specific roles; see `teams`, `roles`, and role/user pivot tables

### Indexing and performance
- Primary keys on `id`
- Recommended secondary indexes (check migrations):
  - `work_orders`: `company_id`, `status_id`, `priority_id`, `category_id`, `assigned_to`, `asset_id`, `location_id`, `due_date`, composite indexes as needed for analytics
  - `work_order_status`: `slug`, `company_id`
  - `work_order_priority`: `slug`, `company_id`
  - Inventory: indexes on `part_id`, `location_id`, `status`, `po_number` where applicable

### Migrations & seeders
- Migrations define the schema; notable ones:
  - `2025_08_07_000001_create_work_orders_table.php` (initial work orders)
  - `2026_01_15_000004_add_metadata_columns_to_work_orders_table.php` (adds FK columns)
  - `2026_08_20_000001_drop_legacy_status_and_priority_from_work_orders.php` (removes legacy enums)
- Seeders:
  - `WorkOrderStatusSeeder` (adds default statuses including `draft`)
  - `WorkOrderPrioritySeeder` (adds priorities)
  - Additional seeders for categories, assets, etc.

Run locally:
```bash
php artisan migrate --seed
```

### Data integrity rules (application-level)
- Work order completion sets `completed_at` when `status` changes to `completed`
- Overdue logic excludes `completed` and `cancelled` statuses
- Company scoping: API queries always filter by `work_orders.company_id`

### Extensibility
- New statuses/priorities/categories can be added per-company via `meta/work-orders/*` API endpoints; `slug` provides stability for analytics filters
- Prefer joining metadata tables by FK and grouping by `slug` for consistent analytics


