# Inventory Module – Backend Overview

## Scope
The Inventory module manages parts, stock across locations, inventory movements, suppliers, purchase orders, analytics, and alerts. Multi-tenant via `company_id`. No DB foreign key constraints (unsigned integers used); integrity enforced at application level.

## Data Model (Key Tables)
- `inventory_parts`: part catalog (part_number, name, manufacturer, specs, unit_cost, reorder_point/qty, min/max stock, barcode, status, abc_class, preferred_supplier_id, ...)
- `inventory_stocks`: per-location stock (on_hand, reserved, available, average_cost, last_counted_at/by, bin_location)
- `inventory_transactions`: movements (receipt, issue, adjustment, transfer_in/out, return) with quantity, costs, reference metadata
- `purchase_orders`: procurement header (supplier, dates, status, amounts, approvals/email/template fields)
- `purchase_order_items`: line items (part, ordered_qty, received_qty, unit_cost)
- `suppliers`: vendor master (contact, codes, status)
- `inventory_categories`: hierarchical part categories
- `purchase_order_templates`: reusable PO templates
- `inventory_alerts`: alert records (type, level, message, resolved)

## Models
- `InventoryPart`, `InventoryStock`, `InventoryTransaction`
- `Supplier`, `PurchaseOrder`, `PurchaseOrderItem`
- `InventoryCategory`, `PurchaseOrderTemplate`, `InventoryAlert`

## Core Services
- `InventoryService`
  - adjustStock(receipt/issue/adjustment/transfer_in/out/return) with moving average cost
  - transfer (creates paired out/in)
  - reserveStock / releaseReservedStock (reserved/available adjustment)
  - performStockCount (auto adjustment + last_counted updates)

## API Endpoints (routes/api.php)
- Parts: `GET/POST/PUT/DELETE /api/inventory/parts`
- Stocks:
  - `GET /api/inventory/stocks` (filters by part/location)
  - `POST /api/inventory/stocks/adjust`
  - `POST /api/inventory/stocks/transfer`
  - `POST /api/inventory/stocks/reserve`
  - `POST /api/inventory/stocks/release`
  - `POST /api/inventory/stocks/count`
- Transactions: `GET /api/inventory/transactions`
- Suppliers: `GET/POST/PUT /api/inventory/suppliers`
- Purchase Orders:
  - `GET /api/inventory/purchase-orders`
  - `POST /api/inventory/purchase-orders`
  - `POST /api/inventory/purchase-orders/{purchaseOrder}/receive`
  - `POST /api/inventory/purchase-orders/approve`
- Categories: `GET/POST/PUT/DELETE /api/inventory/categories`
- PO Templates: `GET/POST/PUT/DELETE /api/inventory/purchase-order-templates`
- Alerts: `GET/POST /api/inventory/alerts`, `POST /api/inventory/alerts/{alert}/resolve`
- Analytics:
  - `GET /api/inventory/analytics/dashboard`
  - `GET /api/inventory/analytics/abc-analysis`
  - `GET /api/inventory/dashboard/overview`

## Validation & Rules
- Application-level integrity: ensure IDs belong to `company_id` where applicable.
- Quantity/cost constraints on adjustments; transfer requires available >= quantity; count auto-adjusts delta.
- PO approval requires ownership and status checks.

## Current Status
- Implemented: tables/fields (per above), services for adjust/transfer/reserve/release/count, core endpoints, categories/templates/alerts, analytics (dashboard, ABC), PO approve/receive.
- Not yet implemented: reordering engine, valuation engine, automated alerts (generation/escalation), aged stock and turnover analytics, supplier approvals/performance, PO email/template workflows, background jobs, notifications, fine-grained permissions, lot/expiry tracking, stock count audit history.

## Next Steps (Recommended)
1) Alerts: generate low/over/expiry on stock/part changes; list low/over; basics of escalation.
2) Reordering: compute reorder needs; optional job to propose POs.
3) Valuation: select method (FIFO/LIFO/AVG); implement layers; reporting.
4) Analytics: stock aging, turnover, supplier performance.
5) Approvals & Emails: pending approvals, send PO email, create from template.
6) Hardening: permission checks, tests, request validators.
