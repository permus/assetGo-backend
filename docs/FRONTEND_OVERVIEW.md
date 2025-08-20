## Frontend (Angular) Overview

This document provides a high-level overview of the Angular frontend located at `assetGo-frontend/`.

### Tech stack
- Angular
- RxJS
- SCSS for styling
- HttpClient with an auth interceptor for attaching tokens

### Project layout
Key paths under `assetGo-frontend/src/app/`:
- `core/`
  - `services/auth.service.ts`: Authentication and token management
  - `interceptors/auth.interceptor.ts`: Injects Authorization header
  - `types/work-order.types.ts`: Shared TypeScript interfaces (e.g., `WorkOrder`, `CreateWorkOrderRequest`)
  - `guards/auth.guard.ts`: Route guard for protected routes
- `auth/`: Login, registration, activation, password flows
- `assets/`: Asset CRUD and views
- `inventory/`: Inventory analytics, parts, stock, transactions
- `locations/`: Location management
- `roles/` and `teams/`: Role and team management
- `work-orders/`: Work order features (creation, list, analytics)
  - `services/work-order.service.ts`: API client for work orders
  - `components/`: List, create modal, analytics and related UI
- `shared/`: Shared components, services, directives

Global configuration:
- `environments/environment.ts` and `environments/environment.prod.ts`

### Work Orders module highlights
- Data model types in `core/types/work-order.types.ts`:
  - `CreateWorkOrderRequest` requires `title`, `priority_id`, `status_id`. Optional `category_id`, `due_date`, `asset_id`, `location_id`, `assigned_to`, `estimated_hours`, `notes`.
  - `WorkOrder` includes foreign keys and related objects (`priority`, `status`, `category`) when eager-loaded by the backend.
- Service `work-orders/services/work-order.service.ts` provides:
  - `createWorkOrder(payload: CreateWorkOrderRequest)`
  - `updateWorkOrderStatus(id: number, status_id: number)`
  - `getWorkOrderAnalytics()` and `getWorkOrderStatistics()`
  - `getWorkOrderCount()` and list/query methods
- Creation flow in `work-orders.component.ts` builds a `CreateWorkOrderRequest` and posts it via `WorkOrderService`.
  - Uses `status_id`/`priority_id` only (no legacy string fields)

### Authentication
- Implemented via token-based auth; the `auth.interceptor.ts` reads the token from `AuthService` and appends `Authorization: Bearer <token>`.
- `auth.guard.ts` protects routes and redirects unauthenticated users to the login.

### Running locally
From `assetGo-frontend/`:

```bash
npm install
npm start
# or
ng serve
```

Build for production:

```bash
ng build --configuration production
```

### API usage patterns
- All API calls use the base URL from `environment.apiUrl`.
- Responses typically follow `{ success: boolean, data: any, message?: string }`.

Example create work order request shape used by the frontend:

```ts
const payload: CreateWorkOrderRequest = {
  title: 'Replace filter',
  description: 'Change filter on AHU-01',
  status_id: 1,           // e.g., draft/open per backend metadata
  priority_id: 2,         // e.g., medium/high per backend metadata
  category_id: 3,
  due_date: '2025-09-01T00:00:00Z',
  asset_id: 10,
  location_id: 5,
  assigned_to: 42,
  estimated_hours: 2.5,
  notes: 'Bring PPE',
};
```

### Conventions
- Always use foreign key fields (`status_id`, `priority_id`, `category_id`) and rely on backend to include related objects (`status`, `priority`, `category`) in responses.
- Use RxJS `map` to unwrap `ApiResponse` structures where applicable.


