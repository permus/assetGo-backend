# Inventory Module API

This document describes the Inventory APIs implemented in the backend. The design follows the existing code structure and supports parts, locations, stock, transactions, suppliers, and purchase orders, with analytics and audit trail.

## Authentication

All endpoints are under `/api` and require Sanctum auth unless specified. Company scoping is enforced on every query.

## Parts Catalog

- GET `/api/inventory/parts` — List parts with pagination and search (`search`, `status`, `per_page`).
- POST `/api/inventory/parts` — Create a part. Required: `name`, `part_number`, `uom`. Optional: `unit_cost`, `category_id`, `reorder_point`, `reorder_qty`, `barcode`.
- GET `/api/inventory/parts/{id}` — Show part.
- PUT `/api/inventory/parts/{id}` — Update fields (cannot change `part_number` if in use without admin override; currently enforced via unique check on change only).
- DELETE `/api/inventory/parts/{id}` — Soft-delete part (archive).

## Stock Levels

- GET `/api/inventory/stocks` — List stock per part per location. Filters: `location_id`, `part_id`, `search`, `per_page`.
- POST `/api/inventory/stocks/adjust` — Adjust stock. Body: `part_id`, `location_id`, `type` (`receipt|issue|adjustment|return`), `quantity`, optional `unit_cost`, `reason`, `notes`, `reference`.

## Transactions

- GET `/api/inventory/transactions` — Full movement log. Filters: `type`, `part_id`, `location_id`, `start_date`, `end_date`, `per_page`.

## Suppliers

- GET `/api/inventory/suppliers` — List suppliers with search.
- POST `/api/inventory/suppliers` — Create supplier.
- PUT `/api/inventory/suppliers/{id}` — Update supplier.

## Purchase Orders

- GET `/api/inventory/purchase-orders` — List POs with supplier and items. Filter by `status`.
- POST `/api/inventory/purchase-orders` — Create PO. Body:
  - `supplier_id`, `order_date?`, `expected_date?`,
  - `items`: array of `{ part_id, ordered_qty, unit_cost }`.
- POST `/api/inventory/purchase-orders/{id}/receive` — Receive partial/full quantities. Body: `location_id`, `items: [ { item_id, receive_qty } ]`, optional `reference`, `notes`.

## Analytics

- GET `/api/inventory/analytics/dashboard` — KPIs: `total_value`, `total_parts`, `low_stock_count`, `out_of_stock_count`.

## Permissions

Integrate with the existing permission system by adding `inventory_*` modules as needed in roles (e.g., `inventory_parts`, `inventory_stock`, `inventory_pos`). Route-level middleware can be added similarly to other modules.

