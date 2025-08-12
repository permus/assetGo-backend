# Inventory Module Documentation

## Overview

The Inventory Module is a comprehensive stock management system within the AssetGo Enterprise Asset Management platform. It provides end-to-end inventory control, from parts catalog management to stock tracking, procurement, and advanced analytics. The system enables organizations to optimize stock levels, reduce carrying costs, and ensure parts availability for maintenance operations.

## Core Features

### 1. Parts Catalog Management
- **Comprehensive Parts Database**: Complete parts catalog with detailed specifications
- **Part Classification**: Categorized by type, manufacturer, and maintenance category
- **Specifications Tracking**: Detailed technical specifications and compatibility data
- **Image Management**: Part photos and visual identification
- **Barcode Integration**: QR code and barcode support for quick identification

### 2. Stock Level Management
- **Multi-Location Inventory**: Track stock across multiple locations and facilities
- **Real-time Stock Levels**: Live inventory counts with reserved quantities
- **Bin Location Tracking**: Specific storage location identification
- **Stock Counts**: Physical inventory verification and reconciliation
- **Available vs. Reserved**: Distinguish between on-hand and allocated stock

### 3. Procurement & Purchase Orders
- **Purchase Order Management**: Complete PO lifecycle from creation to receipt
- **Vendor Management**: Supplier information and relationships
- **Approval Workflows**: Multi-level approval processes with thresholds
- **Delivery Tracking**: Expected vs. actual delivery date monitoring
- **Cost Management**: Subtotal, tax, shipping, and total cost tracking

### 4. Inventory Transactions
- **Complete Movement Tracking**: All stock movements recorded and audited
- **Transaction Types**: Receipts, issues, transfers, adjustments, returns
- **Reference Linking**: Connect transactions to work orders, purchase orders, assets
- **Cost Tracking**: Unit cost and total cost for each transaction
- **Audit Trail**: Complete history of all inventory changes

### 5. Smart Reordering
- **Reorder Point Management**: Automatic alerts when stock reaches minimum levels
- **Economic Order Quantities**: Optimized reorder quantities based on usage patterns
- **Supplier Integration**: Preferred supplier management and ordering
- **Automated Alerts**: Proactive notifications for low stock situations
- **Reorder History**: Track all reorder activities and patterns

### 6. Advanced Analytics
- **ABC Analysis**: Inventory classification by value contribution
- **Stock Aging**: Identify slow-moving and obsolete inventory
- **Turnover Metrics**: Analyze inventory movement and efficiency
- **Cost Analysis**: Track inventory value and cost trends
- **Performance Dashboards**: Real-time inventory insights and KPIs

### 7. Stock Alert System
- **Low Stock Alerts**: Proactive notifications for reorder points
- **Overstock Warnings**: Identify excessive inventory levels
- **Expiry Notifications**: Track parts with expiration dates
- **Custom Alert Rules**: Configurable alert thresholds and conditions
- **Escalation Workflows**: Automated escalation for critical alerts

## Database Schema

### Main Tables

#### 1. `parts` Table
The primary table storing all parts catalog information.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `company_id` | UUID | Company identifier | NOT NULL, REFERENCES companies(id) |
| `part_number` | TEXT | Unique part identifier | NOT NULL |
| `name` | TEXT | Part name/description | NOT NULL |
| `description` | TEXT | Detailed description | NULLABLE |
| `manufacturer` | TEXT | Part manufacturer | NULLABLE |
| `category` | TEXT | Part category/type | NULLABLE |
| `maintenance_category` | TEXT | Maintenance classification | NULLABLE |
| `unit_of_measure` | TEXT | Measurement unit | DEFAULT 'each' |
| `unit_cost` | DECIMAL(10,2) | Cost per unit | NULLABLE |
| `specifications` | JSONB | Technical specifications | DEFAULT '{}' |
| `compatible_assets` | TEXT[] | Compatible asset types | NULLABLE |
| `minimum_stock` | INTEGER | Minimum stock level | DEFAULT 0 |
| `maximum_stock` | INTEGER | Maximum stock level | NULLABLE |
| `reorder_point` | INTEGER | Reorder trigger level | DEFAULT 0 |
| `reorder_quantity` | INTEGER | Standard reorder amount | DEFAULT 1 |
| `barcode` | TEXT | Barcode/QR code | NULLABLE |
| `image_url` | TEXT | Part image URL | NULLABLE |
| `notes` | TEXT | Additional notes | NULLABLE |
| `is_active` | BOOLEAN | Active status | DEFAULT true |
| `is_consumable` | BOOLEAN | Consumable flag | NULLABLE |
| `usage_tracking` | BOOLEAN | Track usage patterns | NULLABLE |
| `preferred_supplier_id` | UUID | Preferred supplier | NULLABLE, REFERENCES suppliers(id) |
| `created_at` | TIMESTAMP | Creation timestamp | NOT NULL, DEFAULT now() |
| `updated_at` | TIMESTAMP | Last update timestamp | NOT NULL, DEFAULT now() |

#### 2. `inventory_stock` Table
Tracks current stock levels across locations.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `part_id` | UUID | Part reference | NOT NULL, REFERENCES parts(id) |
| `location_id` | UUID | Location reference | NULLABLE, REFERENCES locations(id) |
| `company_id` | UUID | Company identifier | NOT NULL, REFERENCES companies(id) |
| `quantity_on_hand` | INTEGER | Physical stock count | NOT NULL, DEFAULT 0 |
| `quantity_reserved` | INTEGER | Reserved for work orders | NOT NULL, DEFAULT 0 |
| `quantity_available` | INTEGER | Available quantity | GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED |
| `last_counted_at` | TIMESTAMP | Last physical count | NULLABLE |
| `last_counted_by` | UUID | User who performed count | NULLABLE, REFERENCES profiles(id) |
| `bin_location` | TEXT | Specific storage location | NULLABLE |
| `created_at` | TIMESTAMP | Creation timestamp | NOT NULL, DEFAULT now() |
| `updated_at` | TIMESTAMP | Last update timestamp | NOT NULL, DEFAULT now() |

#### 3. `inventory_transactions` Table
Records all inventory movements and changes.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `part_id` | UUID | Part reference | NOT NULL, REFERENCES parts(id) |
| `location_id` | UUID | Primary location | NULLABLE, REFERENCES locations(id) |
| `company_id` | UUID | Company identifier | NOT NULL, REFERENCES companies(id) |
| `transaction_type` | TEXT | Movement type | NOT NULL |
| `quantity` | INTEGER | Movement quantity | NOT NULL |
| `unit_cost` | DECIMAL(10,2) | Cost per unit | NULLABLE |
| `total_cost` | DECIMAL(10,2) | Total transaction cost | NULLABLE |
| `reference_type` | TEXT | Related document type | NULLABLE |
| `reference_id` | UUID | Related document ID | NULLABLE |
| `from_location_id` | UUID | Source location | NULLABLE, REFERENCES locations(id) |
| `to_location_id` | UUID | Destination location | NULLABLE, REFERENCES locations(id) |
| `reason_code` | TEXT | Reason for movement | NULLABLE |
| `notes` | TEXT | Additional notes | NULLABLE |
| `created_by` | UUID | User who created | NOT NULL, REFERENCES profiles(id) |
| `created_at` | TIMESTAMP | Creation timestamp | NOT NULL, DEFAULT now() |

#### 4. `purchase_orders` Table
Manages procurement and purchasing processes.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `company_id` | UUID | Company identifier | NOT NULL, REFERENCES companies(id) |
| `po_number` | TEXT | Purchase order number | NOT NULL |
| `vendor_id` | UUID | Supplier reference | NULLABLE, REFERENCES suppliers(id) |
| `vendor_name` | TEXT | Supplier name | NOT NULL |
| `vendor_contact` | TEXT | Supplier contact info | NULLABLE |
| `status` | TEXT | PO status | DEFAULT 'draft' |
| `order_date` | DATE | Order creation date | NULLABLE |
| `expected_delivery_date` | DATE | Expected delivery | NULLABLE |
| `actual_delivery_date` | DATE | Actual delivery | NULLABLE |
| `subtotal` | DECIMAL(12,2) | Items subtotal | DEFAULT 0 |
| `tax_amount` | DECIMAL(12,2) | Tax amount | DEFAULT 0 |
| `shipping_cost` | DECIMAL(12,2) | Shipping cost | DEFAULT 0 |
| `total_amount` | DECIMAL(12,2) | Total amount | DEFAULT 0 |
| `terms` | TEXT | Payment terms | NULLABLE |
| `notes` | TEXT | Additional notes | NULLABLE |
| `created_by` | UUID | Creator | NOT NULL, REFERENCES profiles(id) |
| `approved_by` | UUID | Approver | NULLABLE, REFERENCES profiles(id) |
| `approved_at` | TIMESTAMP | Approval timestamp | NULLABLE |
| `approval_threshold` | NUMERIC | Approval amount threshold | DEFAULT 0 |
| `requires_approval` | BOOLEAN | Approval required | DEFAULT false |
| `approval_level` | INTEGER | Current approval level | DEFAULT 0 |
| `approval_history` | JSONB | Approval workflow history | DEFAULT '[]' |
| `email_status` | TEXT | Email notification status | DEFAULT 'not_sent' |
| `last_email_sent_at` | TIMESTAMP | Last email sent | NULLABLE |
| `template_id` | UUID | PO template reference | NULLABLE, REFERENCES purchase_order_templates(id) |
| `created_at` | TIMESTAMP | Creation timestamp | NOT NULL, DEFAULT now() |
| `updated_at` | TIMESTAMP | Last update timestamp | NOT NULL, DEFAULT now() |

#### 5. `purchase_order_items` Table
Line items for purchase orders.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `purchase_order_id` | UUID | PO reference | NOT NULL, REFERENCES purchase_orders(id) |
| `part_id` | UUID | Part reference | NULLABLE, REFERENCES parts(id) |
| `part_number` | TEXT | Part number | NOT NULL |
| `description` | TEXT | Item description | NOT NULL |
| `quantity_ordered` | INTEGER | Quantity ordered | NOT NULL |
| `quantity_received` | INTEGER | Quantity received | DEFAULT 0 |
| `unit_cost` | DECIMAL(10,2) | Cost per unit | NOT NULL |
| `total_cost` | DECIMAL(10,2) | Line total | GENERATED ALWAYS AS (quantity_ordered * unit_cost) STORED |
| `notes` | TEXT | Additional notes | NULLABLE |
| `created_at` | TIMESTAMP | Creation timestamp | NOT NULL, DEFAULT now() |

#### 6. `suppliers` Table
Vendor and supplier information.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `company_id` | UUID | Company identifier | NOT NULL, REFERENCES companies(id) |
| `business_name` | TEXT | Company name | NOT NULL |
| `supplier_code` | TEXT | Supplier identifier | NOT NULL |
| `email` | TEXT | Contact email | NULLABLE |
| `phone` | TEXT | Contact phone | NULLABLE |
| `address` | TEXT | Business address | NULLABLE |
| `city` | TEXT | City | NULLABLE |
| `country` | TEXT | Country | NULLABLE |
| `currency` | TEXT | Preferred currency | NULLABLE |
| `payment_terms` | TEXT | Payment terms | NULLABLE |
| `is_active` | BOOLEAN | Active status | DEFAULT true |
| `is_approved` | BOOLEAN | Approval status | DEFAULT true |
| `created_at` | TIMESTAMP | Creation timestamp | NOT NULL, DEFAULT now() |

### Enums and Constraints

#### Transaction Types
```sql
-- Common transaction types
'receipt'      -- Stock received from supplier
'issue'        -- Stock issued for work orders
'transfer'     -- Stock moved between locations
'adjustment'   -- Inventory adjustments/corrections
'return'       -- Stock returned to supplier
```

#### Purchase Order Status
```sql
-- Purchase order lifecycle
'draft'        -- Initial creation
'pending'      -- Awaiting approval
'approved'     -- Approved and ready
'ordered'      -- Sent to supplier
'received'     -- Items received
'closed'       -- Completed
```

### Relationships

#### Foreign Key Relationships
- **`parts.company_id`** → `companies.id` (Company isolation)
- **`parts.preferred_supplier_id`** → `suppliers.id` (Supplier preference)
- **`inventory_stock.part_id`** → `parts.id` (Stock tracking)
- **`inventory_stock.location_id`** → `locations.id` (Location association)
- **`inventory_transactions.part_id`** → `parts.id` (Transaction tracking)
- **`inventory_transactions.location_id`** → `locations.id` (Location tracking)
- **`purchase_orders.company_id`** → `companies.id` (Company isolation)
- **`purchase_orders.vendor_id`** → `suppliers.id` (Vendor association)
- **`purchase_order_items.purchase_order_id`** → `purchase_orders.id` (PO line items)

#### Row Level Security (RLS)
All tables implement company-based row level security:
```sql
-- Users can only access inventory from their company
CREATE POLICY "Company users can access their inventory" ON parts
FOR ALL USING (company_id IN (
  SELECT company_id FROM profiles WHERE user_id = auth.uid()
));
```

## User Interface Components

### 1. Inventory Main Page (`/inventory`)
- **Tabbed Interface**: 7 main tabs for different inventory functions
- **Dashboard Overview**: Real-time inventory insights and KPIs
- **Parts Catalog**: Complete parts management
- **Stock Levels**: Current stock tracking
- **Transactions**: Movement history and audit trail
- **Purchase Orders**: Procurement management
- **ABC Analysis**: Value-based classification
- **Analytics**: Advanced reporting and insights

### 2. Inventory Dashboard
- **Overview Tab**: Key metrics and performance indicators
- **Smart Automation Tab**: Automated reordering and alerts
- **Alert System Tab**: Stock alerts and notifications
- **Real-time Updates**: Live data with auto-refresh capability

### 3. Parts Management
- **Multiple View Modes**: Cards, list, relaxed, and compact views
- **Advanced Filtering**: Search, category, location, manufacturer filters
- **Bulk Operations**: Select, edit, export, and delete multiple parts
- **Part Creation**: Comprehensive part information entry
- **Image Management**: Part photos and visual identification

### 4. Stock Management
- **Multi-location View**: Stock levels across all locations
- **Stock Counts**: Physical inventory verification
- **Reservation Tracking**: Work order allocations
- **Bin Location Management**: Specific storage location tracking

### 5. Transaction Management
- **Movement History**: Complete audit trail of all stock changes
- **Transaction Types**: Receipts, issues, transfers, adjustments
- **Reference Linking**: Connect to work orders, purchase orders
- **Cost Tracking**: Unit and total cost for each movement

### 6. Purchase Order Management
- **PO Creation**: Comprehensive purchase order setup
- **Approval Workflows**: Multi-level approval processes
- **Vendor Management**: Supplier information and relationships
- **Delivery Tracking**: Monitor expected vs. actual delivery
- **Email Integration**: Automated supplier communications

### 7. ABC Analysis
- **Value Classification**: A, B, C categorization by value contribution
- **Performance Metrics**: Count, value, and percentage analysis
- **Filtering Options**: Search and class-based filtering
- **Export Capabilities**: CSV export for external analysis

## Business Logic & Workflows

### 1. Inventory Lifecycle Management
1. **Part Creation**: Define part specifications and requirements
2. **Initial Stock**: Set up initial inventory levels
3. **Stock Monitoring**: Track usage and stock levels
4. **Reorder Management**: Automatic alerts and reordering
5. **Stock Receipt**: Process incoming inventory
6. **Stock Issues**: Track consumption and usage
7. **Stock Transfers**: Move inventory between locations
8. **Stock Adjustments**: Correct discrepancies and counts

### 2. Purchase Order Workflow
1. **PO Creation**: Define requirements and quantities
2. **Approval Process**: Multi-level approval based on amounts
3. **Vendor Selection**: Choose suppliers and negotiate terms
4. **Order Placement**: Send orders to suppliers
5. **Delivery Tracking**: Monitor expected delivery dates
6. **Receipt Processing**: Receive and verify items
7. **Stock Updates**: Update inventory levels
8. **Payment Processing**: Complete financial transactions

### 3. Stock Reordering Logic
- **Reorder Point**: Trigger when stock reaches minimum level
- **Economic Order Quantity**: Optimize order size based on usage
- **Lead Time Management**: Account for supplier delivery times
- **Safety Stock**: Maintain buffer for unexpected demand
- **Seasonal Adjustments**: Modify levels based on usage patterns

### 4. ABC Analysis Classification
- **Class A**: High-value items (80% of value, 20% of items)
- **Class B**: Medium-value items (15% of value, 30% of items)
- **Class C**: Low-value items (5% of value, 50% of items)
- **Management Focus**: Concentrate resources on Class A items
- **Policy Development**: Different strategies for each class

## Integration Points

### 1. Asset Management
- **Parts Compatibility**: Link parts to compatible assets
- **Maintenance Integration**: Track parts used in maintenance
- **Asset Status**: Update asset status based on parts availability
- **Work Order Linking**: Connect inventory transactions to work orders

### 2. Location Management
- **Multi-location Support**: Track inventory across facilities
- **Location Hierarchy**: Support complex location structures
- **Bin Location Tracking**: Specific storage location identification
- **Location-based Permissions**: Control access by location

### 3. Work Order System
- **Parts Consumption**: Track materials used in maintenance
- **Stock Reservations**: Reserve parts for scheduled work
- **Cost Integration**: Include parts costs in work order totals
- **Availability Checking**: Verify parts availability before scheduling

### 4. Financial Management
- **Cost Tracking**: Monitor inventory carrying costs
- **Budget Integration**: Link to maintenance budgets
- **Purchase Order Costs**: Track procurement expenses
- **Valuation Methods**: FIFO, LIFO, or weighted average

### 5. Supplier Management
- **Vendor Database**: Comprehensive supplier information
- **Performance Tracking**: Monitor supplier delivery and quality
- **Contract Management**: Track terms and conditions
- **Communication History**: Record all supplier interactions

## Performance & Scalability

### 1. Database Optimization
- **Strategic Indexing**: Indexes on frequently queried columns
- **Partitioning**: Company-based data partitioning
- **Query Optimization**: Efficient joins and filtering
- **Materialized Views**: Pre-calculated analytics data

### 2. Caching Strategy
- **Real-time Updates**: Supabase real-time subscriptions
- **Local State**: React Query for client-side caching
- **Optimistic Updates**: Immediate UI feedback
- **Background Sync**: Periodic data synchronization

### 3. Mobile Optimization
- **Responsive Design**: Mobile-first approach
- **Touch-Friendly**: Optimized for mobile devices
- **Offline Support**: Basic offline functionality
- **Progressive Web App**: Enhanced mobile experience

## Security & Compliance

### 1. Data Protection
- **Company Isolation**: Multi-tenant architecture
- **User Authentication**: Supabase Auth integration
- **Role-Based Access**: Granular permission system
- **Data Encryption**: Encrypted storage for sensitive data

### 2. Audit Trail
- **Complete Tracking**: All inventory changes logged
- **User Activity**: Complete user action history
- **Data Integrity**: Referential integrity constraints
- **Change History**: Track all modifications with timestamps

### 3. Compliance Features
- **Inventory Records**: Complete audit trail for compliance
- **Cost Tracking**: Accurate cost allocation and tracking
- **Supplier Management**: Vendor qualification and monitoring
- **Regulatory Reporting**: Compliance-ready data structure

## Advanced Features

### 1. Smart Automation
- **Automated Reordering**: AI-powered reorder suggestions
- **Stock Optimization**: Intelligent stock level management
- **Predictive Analytics**: Forecast demand and usage patterns
- **Smart Alerts**: Contextual and intelligent notifications

### 2. Analytics & Reporting
- **Real-time Dashboards**: Live inventory insights
- **Performance Metrics**: KPIs and performance indicators
- **Trend Analysis**: Historical data and pattern recognition
- **Custom Reports**: Flexible reporting capabilities

### 3. Integration Capabilities
- **API Access**: RESTful API for external integrations
- **Webhook Support**: Real-time notifications to external systems
- **Data Export**: Multiple export formats (CSV, Excel, PDF)
- **Third-party Integrations**: Connect with ERP and accounting systems

## Future Enhancements

### 1. Advanced Features
- **Mobile App**: Native mobile application
- **Barcode Scanning**: QR code and barcode integration
- **IoT Integration**: Sensor-based inventory monitoring
- **Voice Commands**: Voice-activated inventory operations

### 2. AI & Machine Learning
- **Predictive Analytics**: Advanced demand forecasting
- **Smart Recommendations**: AI-powered inventory suggestions
- **Anomaly Detection**: Identify unusual patterns and issues
- **Optimization Algorithms**: Automated inventory optimization

### 3. Enhanced Automation
- **Robotic Process Automation**: Automate repetitive tasks
- **Smart Workflows**: Intelligent process automation
- **Integration Hub**: Centralized system integration
- **Advanced Scheduling**: AI-powered procurement scheduling

## Conclusion

The Inventory Module provides a comprehensive, enterprise-grade solution for inventory management. With its robust database design, flexible workflow system, and integrated analytics, it enables organizations to efficiently manage stock levels, optimize procurement processes, and reduce carrying costs. The module's scalability, security features, and integration capabilities make it suitable for organizations of all sizes, from small maintenance teams to large enterprise operations with complex inventory requirements.

The system's focus on automation, real-time monitoring, and intelligent insights helps organizations maintain optimal stock levels while ensuring parts availability for critical maintenance operations. The comprehensive audit trail and compliance features make it suitable for regulated industries and organizations requiring strict inventory control.
