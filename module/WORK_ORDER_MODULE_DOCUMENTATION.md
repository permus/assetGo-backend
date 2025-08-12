# Work Order Module Documentation

## Overview

The Work Order Module is a comprehensive maintenance management system within the AssetGo Enterprise Asset Management platform. It provides end-to-end workflow management for maintenance tasks, repairs, and service requests, enabling organizations to track, assign, and monitor work orders throughout their lifecycle.

## Core Features

### 1. Work Order Lifecycle Management
- **Complete Workflow**: From creation to completion with multiple status stages
- **Status Tracking**: Real-time status updates and progress monitoring
- **Priority Management**: Four-tier priority system for task prioritization
- **Assignment System**: Team member allocation and responsibility tracking

### 2. Asset & Location Integration
- **Asset Association**: Link work orders to specific equipment/assets
- **Location Tracking**: Associate work orders with physical locations
- **Hierarchical Support**: Multi-level location structure support
- **Asset Status Updates**: Automatic asset status changes during maintenance

### 3. Team Management
- **User Assignment**: Assign work orders to technicians and team members
- **Role-Based Access**: Different permissions for different user roles
- **Team Collaboration**: Shared access to work order information
- **Performance Tracking**: Individual and team performance metrics

### 4. Scheduling & Time Management
- **Due Date Management**: Set and track completion deadlines
- **Time Estimation**: Estimated vs. actual hours tracking
- **Scheduling Tools**: Plan and organize maintenance activities
- **Calendar Integration**: Visual scheduling and timeline management

### 5. Financial Tracking
- **Budget Management**: Allocate and track maintenance budgets
- **Cost Estimation**: Pre-work cost estimates
- **Actual Cost Tracking**: Post-completion cost recording
- **Parts Cost Integration**: Track materials and parts consumption

### 6. Analytics & Reporting
- **Performance Metrics**: Completion rates, response times, efficiency
- **Trend Analysis**: Historical performance data and patterns
- **Technician Performance**: Individual productivity and quality metrics
- **Cost Analysis**: Maintenance cost trends and budget utilization

### 7. Communication & Documentation
- **Comment System**: Internal communication and notes
- **Photo Documentation**: Visual evidence and progress tracking
- **Requirements Tracking**: Specific requirements and specifications
- **Completion Notes**: Final documentation and handover notes

## Database Schema

### Main Tables

#### 1. `work_orders` Table
The primary table storing all work order information.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `company_id` | UUID | Company identifier | NOT NULL, REFERENCES companies(id) |
| `title` | TEXT | Work order title | NOT NULL |
| `description` | TEXT | Detailed description | NULLABLE |
| `status` | ENUM | Current status | DEFAULT 'open' |
| `priority` | ENUM | Priority level | DEFAULT 'medium' |
| `asset_id` | UUID | Associated asset | NULLABLE, REFERENCES assets(id) |
| `location_id` | UUID | Associated location | NULLABLE, REFERENCES locations(id) |
| `assigned_to_id` | UUID | Assigned technician | NULLABLE, REFERENCES profiles(id) |
| `created_by_id` | UUID | Creator of work order | NOT NULL, REFERENCES profiles(id) |
| `due_date` | TIMESTAMP | Completion deadline | NULLABLE |
| `estimated_hours` | DECIMAL(5,2) | Estimated time required | NULLABLE |
| `actual_hours` | DECIMAL(5,2) | Actual time spent | NULLABLE |
| `completed_at` | TIMESTAMP | Completion timestamp | NULLABLE |
| `budget_allocated` | DECIMAL(12,2) | Allocated budget | NULLABLE |
| `budget_min` | DECIMAL(12,2) | Minimum budget limit | NULLABLE |
| `budget_max` | DECIMAL(12,2) | Maximum budget limit | NULLABLE |
| `budget_remaining` | DECIMAL(12,2) | Remaining budget | NULLABLE |
| `category` | TEXT | Work order category | NULLABLE |
| `emirate` | TEXT | Geographic region | NULLABLE |
| `requirements` | TEXT[] | Array of requirements | NULLABLE |
| `parts_consumed` | JSONB | Parts and materials used | NULLABLE |
| `total_parts_cost` | DECIMAL(12,2) | Total parts cost | NULLABLE |
| `deadline` | TIMESTAMP | Alternative deadline field | NULLABLE |
| `created_at` | TIMESTAMP | Creation timestamp | NOT NULL, DEFAULT now() |
| `updated_at` | TIMESTAMP | Last update timestamp | NOT NULL, DEFAULT now() |

#### 2. `work_order_comments` Table
Stores communication and notes related to work orders.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `work_order_id` | UUID | Work order reference | NOT NULL, REFERENCES work_orders(id) |
| `user_id` | UUID | Comment author | NOT NULL, REFERENCES profiles(id) |
| `comment` | TEXT | Comment content | NOT NULL |
| `comment_type` | TEXT | Type of comment | NULLABLE |
| `created_at` | TIMESTAMP | Comment timestamp | NOT NULL, DEFAULT now() |
| `updated_at` | TIMESTAMP | Last update timestamp | NOT NULL, DEFAULT now() |

#### 3. `scheduled_maintenance` Table
Links work orders to maintenance plans and schedules.

| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | UUID | Primary key | NOT NULL, DEFAULT gen_random_uuid() |
| `company_id` | UUID | Company identifier | NOT NULL, REFERENCES companies(id) |
| `asset_id` | UUID | Asset reference | NOT NULL, REFERENCES assets(id) |
| `maintenance_plan_id` | UUID | Maintenance plan reference | NOT NULL, REFERENCES maintenance_plans(id) |
| `work_order_id` | UUID | Associated work order | NULLABLE, REFERENCES work_orders(id) |
| `scheduled_date` | DATE | Scheduled maintenance date | NOT NULL |
| `scheduled_time` | TIME | Scheduled time | NULLABLE |
| `due_date` | DATE | Due date | NOT NULL |
| `priority` | TEXT | Priority level | NULLABLE |
| `status` | TEXT | Current status | NULLABLE |
| `assigned_to_id` | UUID | Assigned technician | NULLABLE, REFERENCES profiles(id) |
| `estimated_hours` | DECIMAL(5,2) | Estimated duration | NULLABLE |
| `actual_hours` | DECIMAL(5,2) | Actual duration | NULLABLE |
| `cost_estimate` | DECIMAL(12,2) | Cost estimate | NULLABLE |
| `actual_cost` | DECIMAL(12,2) | Actual cost | NULLABLE |
| `notes` | TEXT | Additional notes | NULLABLE |
| `created_by` | UUID | Creator | NOT NULL, REFERENCES profiles(id) |
| `completed_by_id` | UUID | Completion user | NULLABLE, REFERENCES profiles(id) |
| `completed_date` | DATE | Completion date | NULLABLE |
| `next_maintenance_date` | DATE | Next scheduled date | NULLABLE |
| `created_at` | TIMESTAMP | Creation timestamp | NOT NULL, DEFAULT now() |
| `updated_at` | TIMESTAMP | Last update timestamp | NOT NULL, DEFAULT now() |

### Enums and Constraints

#### Work Order Status Enum
```sql
CREATE TYPE public.work_order_status AS ENUM (
  'draft',      -- Initial draft state
  'open',       -- Ready for assignment
  'in_progress', -- Currently being worked on
  'on_hold',    -- Temporarily suspended
  'completed',  -- Successfully finished
  'cancelled'   -- Cancelled or abandoned
);
```

#### Work Order Priority Enum
```sql
CREATE TYPE public.work_order_priority AS ENUM (
  'low',        -- Low priority, can be delayed
  'medium',     -- Normal priority
  'high',       -- High priority, needs attention
  'critical'    -- Critical, immediate action required
);
```

### Relationships

#### Foreign Key Relationships
- **`work_orders.company_id`** → `companies.id` (Company isolation)
- **`work_orders.asset_id`** → `assets.id` (Asset association)
- **`work_orders.location_id`** → `locations.id` (Location association)
- **`work_orders.assigned_to_id`** → `profiles.id` (Technician assignment)
- **`work_orders.created_by_id`** → `profiles.id` (Creator identification)
- **`work_order_comments.work_order_id`** → `work_orders.id` (Comment association)
- **`work_order_comments.user_id`** → `profiles.id` (Comment author)

#### Row Level Security (RLS)
All tables implement company-based row level security:
```sql
-- Users can only access work orders from their company
CREATE POLICY "Company users can access their work orders" ON work_orders
FOR ALL USING (company_id IN (
  SELECT company_id FROM profiles WHERE user_id = auth.uid()
));
```

## User Interface Components

### 1. Work Orders Main Page (`/work-orders`)
- **Tabbed Interface**: Work Orders and Analytics tabs
- **Statistics Dashboard**: Real-time counts and metrics
- **Action Buttons**: Create new work orders
- **Filtering System**: Search, status, priority, and view mode filters

### 2. Work Order Creation Modal
- **Basic Information Section**:
  - Title (required)
  - Description
  - Priority selection
  - Due date picker
  
- **Assignment & Location Section**:
  - Asset selection (optional)
  - Location selection (optional)
  - Team member assignment (optional)

### 3. Work Order Management
- **Grid View**: Card-based layout for visual organization
- **List View**: Compact and detailed list formats
- **Status Indicators**: Color-coded status badges
- **Priority Markers**: Visual priority indicators
- **Quick Actions**: Edit, view, and status change buttons

### 4. Work Order Analytics
- **KPI Cards**: Key performance indicators
- **Status Distribution**: Pie chart of work order statuses
- **Priority Analysis**: Bar chart of priority distribution
- **Performance Trends**: Line charts showing monthly trends
- **Technician Performance**: Individual productivity metrics

## Business Logic & Workflows

### 1. Work Order Creation Flow
1. **Initiation**: User creates work order with basic information
2. **Assignment**: Optional assignment to technician and location
3. **Scheduling**: Set due dates and estimated completion times
4. **Approval**: Work order moves to 'open' status
5. **Execution**: Technician begins work, status changes to 'in_progress'
6. **Completion**: Work order marked as completed with actual hours and costs

### 2. Status Transition Rules
- **Draft** → **Open**: Initial review and approval
- **Open** → **In Progress**: Work begins
- **In Progress** → **On Hold**: Temporary suspension
- **On Hold** → **In Progress**: Work resumes
- **In Progress** → **Completed**: Work finished
- **Any Status** → **Cancelled**: Work order abandoned

### 3. Priority Management
- **Critical**: Immediate action required (24 hours)
- **High**: Urgent attention needed (3-5 days)
- **Medium**: Normal priority (1-2 weeks)
- **Low**: Can be delayed (flexible timeline)

### 4. Budget Control
- **Budget Allocation**: Set spending limits
- **Cost Tracking**: Monitor actual vs. estimated costs
- **Overspending Prevention**: Alerts when approaching budget limits
- **Cost Analysis**: Historical cost trends and patterns

## Integration Points

### 1. Asset Management
- **Asset Status Updates**: Automatic status changes during maintenance
- **Maintenance History**: Link work orders to asset maintenance records
- **Asset Performance**: Track maintenance impact on asset reliability

### 2. Inventory Management
- **Parts Consumption**: Track materials and parts used
- **Stock Updates**: Automatic inventory adjustments
- **Cost Integration**: Parts costs included in work order totals

### 3. Location Management
- **Geographic Tracking**: Associate work with physical locations
- **Location Hierarchy**: Support for complex location structures
- **Access Control**: Location-based permissions and visibility

### 4. Team Management
- **User Profiles**: Technician information and skills
- **Role-Based Access**: Different permission levels
- **Performance Metrics**: Individual and team productivity tracking

### 5. AI Integration
- **Intelligent Scheduling**: AI-powered work order prioritization
- **Predictive Maintenance**: Suggest preventive maintenance work orders
- **Performance Insights**: AI-generated recommendations and insights

## Performance & Scalability

### 1. Database Optimization
- **Indexing**: Strategic indexes on frequently queried columns
- **Partitioning**: Company-based data partitioning
- **Query Optimization**: Efficient joins and filtering

### 2. Caching Strategy
- **Real-time Updates**: Supabase real-time subscriptions
- **Local State**: React Query for client-side caching
- **Optimistic Updates**: Immediate UI feedback

### 3. Mobile Optimization
- **Responsive Design**: Mobile-first approach
- **Touch-Friendly**: Optimized for mobile devices
- **Offline Support**: Basic offline functionality

## Security & Compliance

### 1. Data Protection
- **Company Isolation**: Multi-tenant architecture
- **User Authentication**: Supabase Auth integration
- **Role-Based Access**: Granular permission system

### 2. Audit Trail
- **Change Tracking**: All modifications logged
- **User Activity**: Complete user action history
- **Data Integrity**: Referential integrity constraints

### 3. Compliance Features
- **Work Order Documentation**: Complete maintenance records
- **Safety Protocols**: Safety notes and requirements
- **Regulatory Reporting**: Compliance-ready data structure

## Future Enhancements

### 1. Advanced Features
- **Mobile App**: Native mobile application
- **Barcode Scanning**: QR code and barcode integration
- **Voice Commands**: Voice-activated work order creation
- **IoT Integration**: Sensor-based work order triggers

### 2. Analytics Improvements
- **Predictive Analytics**: Machine learning insights
- **Advanced Reporting**: Custom report builder
- **Performance Benchmarking**: Industry comparison metrics

### 3. Workflow Automation
- **Automated Scheduling**: AI-powered work scheduling
- **Smart Notifications**: Intelligent alert system
- **Workflow Templates**: Standardized work order templates

## Conclusion

The Work Order Module provides a comprehensive, enterprise-grade solution for maintenance management. With its robust database design, flexible workflow system, and integrated analytics, it enables organizations to efficiently manage maintenance operations, track performance, and optimize resource allocation. The module's scalability, security features, and integration capabilities make it suitable for organizations of all sizes, from small maintenance teams to large enterprise operations.
