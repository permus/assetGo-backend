<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Models\WorkOrder;
use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WorkOrderController extends Controller
{
    /**
     * List work orders with pagination and filtering
     * Route: GET /api/work-orders
     */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = WorkOrder::with([
            'asset', 
            'location', 
            'assignedTo', 
            'assignedBy', 
            'createdBy', 
            'company',
            'status',
            'priority',
            'category',
        ])->where('company_id', $companyId);

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                  ->orWhere('notes', 'like', "%$search%")
                  ->orWhereHas('asset', function ($assetQ) use ($search) {
                      $assetQ->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('location', function ($locationQ) use ($search) {
                      $locationQ->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('assignedTo', function ($userQ) use ($search) {
                      $userQ->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%");
                  });
            });
        }

        // Filters
        // Prefer *_id filters; fall back to legacy string fields for backward compatibility
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('priority_id')) {
            $query->where('priority_id', $request->priority_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }
        if ($request->filled('is_overdue')) {
            if ($request->boolean('is_overdue')) {
                $query->overdue();
            }
        }
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }
        if ($request->filled('due_start_date')) {
            $query->where('due_date', '>=', $request->due_start_date);
        }
        if ($request->filled('due_end_date')) {
            $query->where('due_date', '<=', $request->due_end_date . ' 23:59:59');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $workOrders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $workOrders,
            'message' => 'Work orders retrieved successfully'
        ]);
    }

    /**
     * Get work order count
     * Route: GET /api/work-orders/count
     */
    public function count(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = WorkOrder::where('company_id', $companyId);

        // Apply same filters as index method
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }
        if ($request->filled('priority_id')) {
            $query->where('priority_id', $request->priority_id);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('is_overdue')) {
            if ($request->boolean('is_overdue')) {
                $query->overdue();
            }
        }

        $count = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count
            ],
            'message' => 'Work order count retrieved successfully'
        ]);
    }

    /**
     * Show a specific work order
     * Route: GET /api/work-orders/{workOrder}
     */
    public function show(WorkOrder $workOrder)
    {
        // Check if user has access to this work order
        if ($workOrder->company_id !== request()->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        $workOrder->load([
            'asset',
            'location',
            'assignedTo',
            'assignedBy',
            'createdBy',
            'company',
            'status',
            'priority',
            'category',
        ]);

        return response()->json([
            'success' => true,
            'data' => $workOrder,
            'message' => 'Work order retrieved successfully'
        ]);
    }

    /**
     * Store a new work order
     * Route: POST /api/work-orders
     */
    public function store(StoreWorkOrderRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $request->user()->company_id;
        $data['created_by'] = $request->user()->id;

        // If assigned_to is set, set assigned_by to current user
        if (isset($data['assigned_to'])) {
            $data['assigned_by'] = $request->user()->id;
        }

        $workOrder = WorkOrder::create($data);

        $workOrder->load([
            'asset', 
            'location', 
            'assignedTo', 
            'assignedBy', 
            'createdBy', 
            'company',
            'status',
            'priority',
            'category',
        ]);

        return response()->json([
            'success' => true,
            'data' => $workOrder,
            'message' => 'Work order created successfully'
        ], 201);
    }

    /**
     * Update a work order
     * Route: PUT /api/work-orders/{workOrder}
     */
    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder)
    {
        // Check if user has access to this work order
        if ($workOrder->company_id !== request()->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        $data = $request->validated();

        // If assigned_to is being changed, set assigned_by to current user
        if (isset($data['assigned_to']) && $data['assigned_to'] !== $workOrder->assigned_to) {
            $data['assigned_by'] = $request->user()->id;
        }

        $workOrder->update($data);

        $workOrder->load([
            'asset', 
            'location', 
            'assignedTo', 
            'assignedBy', 
            'createdBy', 
            'company'
        ]);

        return response()->json([
            'success' => true,
            'data' => $workOrder,
            'message' => 'Work order updated successfully'
        ]);
    }

    /**
     * Update only the status of a work order
     * Route: POST /api/work-orders/{workOrder}/status
     */
    public function updateStatus(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        $validated = $request->validate([
            'status_id' => 'required|exists:work_order_status,id',
        ]);

        $workOrder->status_id = $validated['status_id'];
        $workOrder->save();

        $workOrder->load(['status']);

        return response()->json([
            'success' => true,
            'data' => $workOrder,
            'message' => 'Work order status updated successfully'
        ]);
    }

    /**
     * Delete a work order
     * Route: DELETE /api/work-orders/{workOrder}
     */
    public function destroy(WorkOrder $workOrder)
    {
        // Check if user has access to this work order
        if ($workOrder->company_id !== request()->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        $workOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Work order deleted successfully'
        ]);
    }

    /**
     * Get work order analytics
     * Route: GET /api/work-orders/analytics
     */
    public function analytics(Request $request)
    {
        $companyId = $request->user()->company_id;
        $dateRange = $request->get('date_range', 30); // Default to last 30 days

        // Calculate date range
        $endDate = now();
        $startDate = $endDate->copy()->subDays($dateRange);

        // Basic counts
        $totalWorkOrders = WorkOrder::where('company_id', $companyId)->count();
        $openWorkOrders = WorkOrder::where('company_id', $companyId)
            ->whereHas('status', function ($q) { $q->where('slug', 'open'); })
            ->count();
        $inProgressWorkOrders = WorkOrder::where('company_id', $companyId)
            ->whereHas('status', function ($q) { $q->where('slug', 'in-progress'); })
            ->count();
        $completedWorkOrders = WorkOrder::where('company_id', $companyId)
            ->whereHas('status', function ($q) { $q->where('slug', 'completed'); })
            ->count();
        $overdueWorkOrders = WorkOrder::where('company_id', $companyId)->overdue()->count();

        // Average resolution time (for completed work orders)
        $avgResolutionTime = WorkOrder::where('company_id', $companyId)
            ->whereHas('status', function ($q) { $q->where('slug', 'completed'); })
            ->whereNotNull('completed_at')
            ->whereNotNull('created_at')
            ->avg(DB::raw('DATEDIFF(completed_at, created_at)'));

        // Completion rate
        $completionRate = $totalWorkOrders > 0 ? round(($completedWorkOrders / $totalWorkOrders) * 100, 1) : 0;

        // Active technicians (users with assigned work orders)
        $activeTechnicians = WorkOrder::where('company_id', $companyId)
            ->whereHas('status', function ($q) { $q->whereIn('slug', ['open', 'in-progress']); })
            ->whereNotNull('assigned_to')
            ->distinct('assigned_to')
            ->count('assigned_to');

        // Status distribution
        $statusDistribution = WorkOrder::where('company_id', $companyId)
            ->join('work_order_status as s', 's.id', '=', 'work_orders.status_id')
            ->selectRaw('s.slug as status_slug, COUNT(*) as count')
            ->groupBy('status_slug')
            ->pluck('count', 'status_slug')
            ->toArray();

        // Priority distribution
        $priorityDistribution = WorkOrder::where('company_id', $companyId)
            ->join('work_order_priority as p', 'p.id', '=', 'work_orders.priority_id')
            ->selectRaw('p.slug as priority_slug, COUNT(*) as count')
            ->groupBy('priority_slug')
            ->pluck('count', 'priority_slug')
            ->toArray();

        // Monthly performance trend (created vs completed)
        $monthlyTrend = WorkOrder::where('company_id', $companyId)
            ->join('work_order_status as s', 's.id', '=', 'work_orders.status_id')
            ->whereBetween('work_orders.created_at', [$startDate, $endDate])
            ->selectRaw('
                YEAR(work_orders.created_at) as year,
                MONTH(work_orders.created_at) as month,
                COUNT(*) as created_count,
                SUM(CASE WHEN s.slug = "completed" THEN 1 ELSE 0 END) as completed_count
            ')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Top technician performance
        $topTechnicians = WorkOrder::where('company_id', $companyId)
            ->join('work_order_status as s', 's.id', '=', 'work_orders.status_id')
            ->where('s.slug', 'completed')
            ->whereNotNull('assigned_to')
            ->whereBetween('work_orders.completed_at', [$startDate, $endDate])
            ->selectRaw('
                work_orders.assigned_to,
                COUNT(*) as completed_count,
                AVG(DATEDIFF(work_orders.completed_at, work_orders.created_at)) as avg_resolution_days
            ')
            ->with('assignedTo:id,first_name,last_name')
            ->groupBy('work_orders.assigned_to')
            ->orderByDesc('completed_count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                // KPIs
                'total_work_orders' => $totalWorkOrders,
                'open_work_orders' => $openWorkOrders,
                'in_progress_work_orders' => $inProgressWorkOrders,
                'completed_work_orders' => $completedWorkOrders,
                'overdue_work_orders' => $overdueWorkOrders,
                'average_resolution_time_days' => round($avgResolutionTime ?? 0, 1),
                'completion_rate_percentage' => $completionRate,
                'active_technicians' => $activeTechnicians,

                // Distributions
                'status_distribution' => $statusDistribution,
                'priority_distribution' => $priorityDistribution,

                // Trends
                'monthly_performance_trend' => $monthlyTrend,
                'top_technician_performance' => $topTechnicians,
            ],
            'message' => 'Work order analytics retrieved successfully'
        ]);
    }

    /**
     * Get work order statistics
     * Route: GET /api/work-orders/statistics
     */
    public function statistics(Request $request)
    {
        $companyId = $request->user()->company_id;

        // Basic counts by status
        $statusCounts = WorkOrder::where('company_id', $companyId)
            ->join('work_order_status as s', 's.id', '=', 'work_orders.status_id')
            ->selectRaw('s.slug as status_slug, COUNT(*) as count')
            ->groupBy('status_slug')
            ->pluck('count', 'status_slug')
            ->toArray();

        // Priority counts
        $priorityCounts = WorkOrder::where('company_id', $companyId)
            ->join('work_order_priority as p', 'p.id', '=', 'work_orders.priority_id')
            ->selectRaw('p.slug as priority_slug, COUNT(*) as count')
            ->groupBy('priority_slug')
            ->pluck('count', 'priority_slug')
            ->toArray();

        // Overdue count
        $overdueCount = WorkOrder::where('company_id', $companyId)->overdue()->count();

        // Recent activity (last 7 days)
        $recentCreated = WorkOrder::where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $recentCompleted = WorkOrder::where('company_id', $companyId)
            ->whereHas('status', function ($q) { $q->where('slug', 'completed'); })
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'status_counts' => $statusCounts,
                'priority_counts' => $priorityCounts,
                'overdue_count' => $overdueCount,
                'recent_created' => $recentCreated,
                'recent_completed' => $recentCompleted,
            ],
            'message' => 'Work order statistics retrieved successfully'
        ]);
    }

    /**
     * Get work order history (created, updates, comments)
     * Route: GET /api/work-orders/{workOrder}/history
     */
    public function history(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Work order not found'], 404);
        }

        $workOrder->load(['createdBy:id,first_name,last_name,email', 'comments.user:id,first_name,last_name,email']);

        $events = [];

        // Created event
        $events[] = [
            'type' => 'created',
            'title' => 'Created',
            'timestamp' => optional($workOrder->created_at)->toISOString(),
            'user' => $workOrder->createdBy ? [
                'id' => $workOrder->createdBy->id,
                'first_name' => $workOrder->createdBy->first_name,
                'last_name' => $workOrder->createdBy->last_name,
                'email' => $workOrder->createdBy->email,
            ] : null,
            'details' => null,
        ];

        // Last updated event (if different from created)
        if ($workOrder->updated_at && !$workOrder->updated_at->equalTo($workOrder->created_at)) {
            $events[] = [
                'type' => 'updated',
                'title' => 'Last Updated',
                'timestamp' => $workOrder->updated_at->toISOString(),
                'user' => null,
                'details' => null,
            ];
        }

        // Comments as events
        foreach ($workOrder->comments as $comment) {
            $events[] = [
                'type' => 'comment',
                'title' => 'Comment',
                'timestamp' => optional($comment->created_at)->toISOString(),
                'user' => $comment->user ? [
                    'id' => $comment->user->id,
                    'first_name' => $comment->user->first_name,
                    'last_name' => $comment->user->last_name,
                    'email' => $comment->user->email,
                ] : null,
                'details' => [
                    'comment' => $comment->comment,
                ],
            ];
        }

        // Sort by timestamp desc
        usort($events, function ($a, $b) {
            return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
        });

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Get available filters for work orders
     * Route: GET /api/work-orders/filters
     */
    public function filters(Request $request)
    {
        $companyId = $request->user()->company_id;

        // Get available assets
        $assets = Asset::where('company_id', $companyId)
            ->select('id', 'name', 'asset_id')
            ->orderBy('name')
            ->get();

        // Get available locations
        $locations = Location::where('company_id', $companyId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get available users (technicians)
        $users = User::where('company_id', $companyId)
            ->select('id', 'first_name', 'last_name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // Status options
        $statusOptions = [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled'
        ];

        // Priority options
        $priorityOptions = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical'
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'assets' => $assets,
                'locations' => $locations,
                'users' => $users,
                'status_options' => $statusOptions,
                'priority_options' => $priorityOptions,
            ],
            'message' => 'Work order filters retrieved successfully'
        ]);
    }
}
