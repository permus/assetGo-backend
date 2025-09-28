# AssetGo Project Architecture Documentation

## Table of Contents
1. [Project Overview](#project-overview)
2. [Backend Architecture (Laravel)](#backend-architecture-laravel)
3. [Frontend Architecture (Angular)](#frontend-architecture-angular)
4. [Database Schema](#database-schema)
5. [API Endpoints](#api-endpoints)
6. [AI Features Module](#ai-features-module)
7. [Code Structure & Organization](#code-structure--organization)
8. [Development Workflow](#development-workflow)

## Project Overview

AssetGo is a comprehensive asset management system built with Laravel (backend) and Angular (frontend). The system provides AI-powered features for asset recognition, predictive maintenance, analytics, and recommendations.

### Technology Stack
- **Backend**: Laravel 10.x, PHP 8.1+, MySQL
- **Frontend**: Angular 17+, TypeScript, SCSS
- **AI Integration**: OpenAI API
- **Authentication**: Laravel Sanctum
- **Database**: MySQL with migrations

## Backend Architecture (Laravel)

### Project Structure
```
assetGo-backend/
├── app/
│   ├── Http/Controllers/Api/     # API Controllers
│   ├── Models/                   # Eloquent Models
│   ├── Services/                 # Business Logic Services
│   ├── Jobs/                     # Background Jobs
│   └── Exceptions/               # Custom Exceptions
├── database/
│   ├── migrations/               # Database Migrations
│   └── seeders/                  # Database Seeders
├── routes/
│   └── api.php                   # API Routes
└── config/                       # Configuration Files
```

### Key Backend Components

#### 1. API Controllers
Located in `app/Http/Controllers/Api/`:
- **AIAnalyticsController**: Handles AI analytics generation and management
- **AIRecommendationsController**: Manages AI-powered recommendations
- **PredictiveMaintenanceController**: Handles predictive maintenance features
- **AIImageRecognitionController**: Manages image recognition functionality

#### 2. Services Layer
Located in `app/Services/`:
- **AIAnalyticsService**: Core business logic for analytics generation
- **AIRecommendationsService**: Recommendation generation and management
- **OpenAIService**: OpenAI API integration
- **PredictiveMaintenanceService**: Maintenance prediction logic

#### 3. Eloquent Models
Located in `app/Models/`:
- **AIAnalyticsRun**: Stores analytics generation results
- **AIAnalyticsSchedule**: Manages automated analytics scheduling
- **AIRecommendation**: Stores AI-generated recommendations
- **Asset**: Core asset management model
- **WorkOrder**: Work order management
- **Location**: Asset location management

### Authentication & Security
- **Laravel Sanctum** for API authentication
- **CORS** configuration for cross-origin requests
- **Rate limiting** on API endpoints
- **Middleware** for request validation and authorization

## Frontend Architecture (Angular)

### Project Structure
```
assetGo-frontend/
├── src/
│   ├── app/
│   │   ├── ai-features/          # AI Features Module
│   │   │   ├── components/       # Feature Components
│   │   │   ├── shared/           # Shared Services & Interfaces
│   │   │   └── ai-features.component.ts
│   │   ├── assets/               # Static Assets
│   │   ├── environments/         # Environment Configuration
│   │   └── styles/               # Global Styles
│   └── index.html
├── angular.json                  # Angular Configuration
└── package.json                  # Dependencies
```

### Key Frontend Components

#### 1. AI Features Module
Main module containing all AI-powered features:

**Components:**
- **AIAnalyticsComponent**: Main analytics dashboard
- **AIRecommendationsComponent**: Recommendations management
- **PredictiveMaintenanceComponent**: Maintenance predictions
- **NaturalLanguageComponent**: Natural language queries

**Sub-components:**
- **AnalyticsHeaderComponent**: Analytics action bar
- **HealthScoreCardComponent**: Asset health visualization
- **RiskAssetsComponent**: High-risk assets display
- **PerformanceInsightsComponent**: Performance recommendations
- **CostOptimizationsComponent**: Cost optimization opportunities

#### 2. Shared Services
Located in `shared/` directory:
- **AIAnalyticsService**: API communication for analytics
- **AIRecommendationsService**: Recommendations API integration
- **AIImageUploadService**: Image upload and processing

#### 3. TypeScript Interfaces
Type definitions for data structures:
- **ai-analytics.interface.ts**: Analytics data types
- **ai-recommendations.interface.ts**: Recommendations data types
- **ai-recognition-result.interface.ts**: Image recognition results

### UI/UX Design System

#### Design Principles
- **Modern SaaS Aesthetic**: Clean, professional interface
- **Mobile-First**: Responsive design for all screen sizes
- **Accessibility**: WCAG compliant with keyboard navigation
- **Micro-interactions**: Subtle animations and hover effects

#### Visual Design
- **Color Palette**: Calm, professional colors with excellent contrast
- **Typography**: Clear hierarchy with readable fonts
- **Cards**: Rounded-2xl design with soft shadows
- **Spacing**: Consistent 8px grid system
- **Icons**: Lucide React icon set for consistency

#### Component Architecture
- **Standalone Components**: Modern Angular architecture
- **Reusable Components**: Shared UI components
- **Service Injection**: Dependency injection for API services
- **State Management**: Reactive programming with RxJS

## Database Schema

### Core Tables

#### 1. AI Analytics Tables
```sql
-- Analytics generation runs
ai_analytics_runs (
    id BIGINT PRIMARY KEY,
    company_id BIGINT,
    payload JSON,                    -- Full analytics data
    health_score DECIMAL(5,2),      -- 0-100 health score
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Analytics scheduling
ai_analytics_schedule (
    company_id BIGINT PRIMARY KEY,
    enabled BOOLEAN DEFAULT FALSE,
    frequency ENUM('daily','weekly','monthly'),
    hour_utc INT DEFAULT 3,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 2. AI Recommendations Tables
```sql
-- AI-generated recommendations
ai_recommendations (
    id BIGINT PRIMARY KEY,
    company_id BIGINT,
    rec_type ENUM('cost_optimization','maintenance','efficiency','compliance'),
    title VARCHAR(255),
    description TEXT,
    impact ENUM('low','medium','high'),
    priority ENUM('low','medium','high'),
    estimated_savings DECIMAL(15,2),
    implementation_cost DECIMAL(15,2),
    roi DECIMAL(8,2),
    payback_period VARCHAR(255),
    timeline VARCHAR(255),
    actions JSON,
    confidence DECIMAL(5,2),
    implemented BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 3. Core Asset Management Tables
```sql
-- Companies
companies (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    currency VARCHAR(3) DEFAULT 'AED',
    settings JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Assets
assets (
    id BIGINT PRIMARY KEY,
    company_id BIGINT,
    name VARCHAR(255),
    asset_type VARCHAR(255),
    status ENUM('active','inactive','maintenance'),
    purchase_date DATE,
    purchase_price DECIMAL(15,2),
    location_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Work Orders
work_orders (
    id BIGINT PRIMARY KEY,
    company_id BIGINT,
    title VARCHAR(255),
    description TEXT,
    status_id BIGINT,
    priority_id BIGINT,
    assigned_to BIGINT,
    due_date DATETIME,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Database Views
```sql
-- Latest analytics snapshot
CREATE VIEW ai_analytics_latest AS
SELECT DISTINCT ON (company_id)
    company_id, id, payload, health_score, created_at
FROM ai_analytics_runs
ORDER BY company_id, created_at DESC;

-- Recommendations summary
CREATE VIEW ai_recommendations_summary AS
SELECT
    company_id,
    COUNT(*) as total_recommendations,
    COUNT(*) FILTER (WHERE priority='high') as high_priority_count,
    COALESCE(SUM(estimated_savings),0) as total_savings,
    COALESCE(SUM(implementation_cost),0) as total_cost,
    CASE
        WHEN COALESCE(SUM(implementation_cost),0) = 0 THEN 0
        ELSE ((COALESCE(SUM(estimated_savings),0) - COALESCE(SUM(implementation_cost),0))
            / NULLIF(SUM(implementation_cost),0)) * 100
    END as roi
FROM ai_recommendations
GROUP BY company_id;
```

## API Endpoints

### Authentication
All API endpoints require authentication via Laravel Sanctum:
```http
Authorization: Bearer {token}
```

### AI Analytics Endpoints
```http
GET    /api/ai/analytics              # Get latest analytics and history
POST   /api/ai/analytics/generate     # Generate new analytics
GET    /api/ai/analytics/export       # Export analytics as CSV
GET    /api/ai/analytics/schedule     # Get schedule settings
POST   /api/ai/analytics/schedule     # Update schedule settings
```

### AI Recommendations Endpoints
```http
GET    /api/ai/recommendations        # Get recommendations with filters
POST   /api/ai/recommendations/generate # Generate new recommendations
POST   /api/ai/recommendations/{id}/toggle # Toggle implementation status
GET    /api/ai/recommendations/export # Export recommendations as CSV
GET    /api/ai/recommendations/summary # Get recommendations summary
```

### Core Asset Management Endpoints
```http
GET    /api/assets                    # List assets
POST   /api/assets                    # Create asset
PUT    /api/assets/{id}               # Update asset
DELETE /api/assets/{id}               # Delete asset

GET    /api/work-orders               # List work orders
POST   /api/work-orders               # Create work order
PUT    /api/work-orders/{id}          # Update work order
DELETE /api/work-orders/{id}          # Delete work order
```

### API Response Format
```json
{
  "success": true,
  "data": {
    // Response data
  },
  "error": "Error message if any"
}
```

## AI Features Module

### 1. AI Analytics
**Purpose**: Generate comprehensive analytics about asset portfolio health and performance.

**Key Features**:
- Overall asset health score (0-100)
- High-risk asset identification
- Performance insights and recommendations
- Cost optimization opportunities
- Historical trend analysis
- Automated scheduling

**Data Flow**:
1. User clicks "Generate Analytics"
2. Frontend calls `POST /api/ai/analytics/generate`
3. Backend gathers asset context (counts, work orders, etc.)
4. OpenAI API generates structured analytics
5. Results stored in `ai_analytics_runs` table
6. Frontend displays analytics dashboard

### 2. AI Recommendations
**Purpose**: Provide intelligent recommendations for asset optimization.

**Key Features**:
- Cost optimization suggestions
- Maintenance recommendations
- Efficiency improvements
- Compliance alerts
- ROI calculations
- Implementation tracking

**Data Flow**:
1. User clicks "Generate Recommendations"
2. Frontend calls `POST /api/ai/recommendations/generate`
3. Backend analyzes asset data and context
4. OpenAI generates structured recommendations
5. Results stored in `ai_recommendations` table
6. Frontend displays recommendation cards

### 3. Predictive Maintenance
**Purpose**: Predict when assets will need maintenance.

**Key Features**:
- Maintenance predictions
- Risk assessment
- Cost estimation
- Timeline recommendations
- Historical analysis

### 4. Natural Language Queries
**Purpose**: Allow users to ask questions about their assets in natural language.

**Key Features**:
- Natural language processing
- Context-aware responses
- Asset data integration
- Query history

## Code Structure & Organization

### Backend Code Organization

#### Controllers
- **Single Responsibility**: Each controller handles one resource
- **API Resources**: Consistent response formatting
- **Error Handling**: Centralized error management
- **Validation**: Request validation using Form Requests

#### Services
- **Business Logic**: Complex business rules in services
- **Reusability**: Services can be used across controllers
- **Testing**: Easy to unit test business logic
- **Dependency Injection**: Services injected into controllers

#### Models
- **Eloquent ORM**: Database interactions through models
- **Relationships**: Defined relationships between models
- **Scopes**: Reusable query scopes
- **Accessors/Mutators**: Data transformation

### Frontend Code Organization

#### Components
- **Standalone Components**: Modern Angular architecture
- **Single Responsibility**: Each component has one purpose
- **Reusability**: Components can be reused across features
- **Props/Events**: Clear input/output interfaces

#### Services
- **API Communication**: Centralized API calls
- **State Management**: Reactive state management
- **Error Handling**: Consistent error handling
- **Caching**: Response caching where appropriate

#### Interfaces
- **Type Safety**: Strong typing with TypeScript
- **Documentation**: Self-documenting code
- **Consistency**: Consistent data structures
- **Validation**: Runtime type checking

### File Naming Conventions

#### Backend (Laravel)
- **Controllers**: `PascalCase` (e.g., `AIAnalyticsController.php`)
- **Models**: `PascalCase` (e.g., `AIAnalyticsRun.php`)
- **Services**: `PascalCase` (e.g., `AIAnalyticsService.php`)
- **Migrations**: `snake_case` with timestamp

#### Frontend (Angular)
- **Components**: `kebab-case` (e.g., `ai-analytics.component.ts`)
- **Services**: `kebab-case` (e.g., `ai-analytics.service.ts`)
- **Interfaces**: `kebab-case` (e.g., `ai-analytics.interface.ts`)
- **Styles**: `kebab-case` (e.g., `ai-analytics.component.scss`)

## Development Workflow

### Backend Development
1. **Database Changes**: Create migrations for schema changes
2. **Models**: Update or create Eloquent models
3. **Services**: Implement business logic in services
4. **Controllers**: Create API endpoints in controllers
5. **Routes**: Define routes in `api.php`
6. **Testing**: Write feature and unit tests

### Frontend Development
1. **Interfaces**: Define TypeScript interfaces
2. **Services**: Create API service methods
3. **Components**: Build UI components
4. **Styling**: Add SCSS styles
5. **Integration**: Connect components to services
6. **Testing**: Write component and service tests

### AI Integration
1. **OpenAI API**: Configure API key and endpoints
2. **Prompt Engineering**: Design effective prompts
3. **Data Validation**: Validate AI responses
4. **Error Handling**: Handle API failures gracefully
5. **Rate Limiting**: Implement appropriate rate limits

### Deployment Considerations
- **Environment Variables**: Secure configuration management
- **Database Migrations**: Run migrations in production
- **Asset Compilation**: Build frontend assets
- **Caching**: Configure appropriate caching
- **Monitoring**: Set up error tracking and monitoring

## Security Considerations

### Backend Security
- **Authentication**: Laravel Sanctum for API auth
- **Authorization**: Role-based access control
- **Input Validation**: Validate all user inputs
- **SQL Injection**: Eloquent ORM prevents SQL injection
- **XSS Protection**: Sanitize user inputs
- **CSRF Protection**: CSRF tokens for forms

### Frontend Security
- **HTTPS**: Always use HTTPS in production
- **Content Security Policy**: Implement CSP headers
- **Input Sanitization**: Sanitize user inputs
- **API Security**: Secure API communication
- **Dependency Management**: Keep dependencies updated

## Performance Optimization

### Backend Optimization
- **Database Indexing**: Proper database indexes
- **Query Optimization**: Efficient database queries
- **Caching**: Redis for caching
- **API Rate Limiting**: Prevent abuse
- **Background Jobs**: Queue heavy operations

### Frontend Optimization
- **Lazy Loading**: Load components on demand
- **Bundle Optimization**: Minimize bundle size
- **Image Optimization**: Compress and optimize images
- **Caching**: Browser caching strategies
- **CDN**: Use CDN for static assets

## Monitoring & Maintenance

### Logging
- **Application Logs**: Laravel logging system
- **Error Tracking**: Sentry or similar service
- **Performance Monitoring**: APM tools
- **API Monitoring**: Track API usage and performance

### Maintenance
- **Database Maintenance**: Regular cleanup and optimization
- **Dependency Updates**: Keep dependencies current
- **Security Updates**: Apply security patches
- **Backup Strategy**: Regular database backups
- **Documentation**: Keep documentation updated

---

This documentation provides a comprehensive overview of the AssetGo project architecture. For specific implementation details, refer to the individual component files and API documentation.
