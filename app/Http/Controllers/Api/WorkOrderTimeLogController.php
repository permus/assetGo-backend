<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderTimeLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderTimeLogController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeCompany($request, $workOrder);

        $logs = WorkOrderTimeLog::where('work_order_id', $workOrder->id)
            ->orderByDesc('start_time')
            ->with('user:id,first_name,last_name,email')
            ->get();

        $totalMinutes = $logs->whereNotNull('duration_minutes')->sum('duration_minutes');
        $totalCost = $logs->whereNotNull('total_cost')->sum('total_cost');

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'totals' => [
                    'total_minutes' => (int)$totalMinutes,
                    'total_cost' => (float)$totalCost,
                ],
            ],
        ]);
    }

    public function start(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeCompany($request, $workOrder);

        $validated = $request->validate([
            'description' => 'nullable|string|max:1000',
            'hourly_rate' => 'nullable|numeric|min:0|max:999999.99',
        ]);

        $log = WorkOrderTimeLog::create([
            'work_order_id' => $workOrder->id,
            'user_id' => $request->user()->id,
            'company_id' => $request->user()->company_id,
            'start_time' => now(),
            'description' => $validated['description'] ?? null,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'activity_type' => 'labor',
        ]);

        // Optional: Set status to in-progress if not already
        if (method_exists($workOrder, 'status') && !$workOrder->status || optional($workOrder->status)->slug !== 'in-progress') {
            // no-op, status domain may be managed elsewhere
        }

        return response()->json(['success' => true, 'data' => $log], 201);
    }

    public function stop(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeCompany($request, $workOrder);

        $log = WorkOrderTimeLog::where('work_order_id', $workOrder->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('end_time')
            ->orderByDesc('start_time')
            ->first();

        if (!$log) {
            return response()->json(['success' => false, 'message' => 'No active session'], 404);
        }

        $log->end_time = now();
        $log->duration_minutes = max(1, $log->start_time->diffInMinutes($log->end_time));
        if ($log->hourly_rate) {
            $log->total_cost = round(($log->duration_minutes / 60) * (float)$log->hourly_rate, 2);
        }
        $log->save();

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'work_order',
                    'action' => 'time_logged',
                    'title' => 'Time Logged for Work Order',
                    'message' => $this->notificationService->formatWorkOrderMessage('time_logged', $workOrder->title),
                    'data' => [
                        'workOrderId' => $workOrder->id,
                        'workOrderTitle' => $workOrder->title,
                        'durationMinutes' => $log->duration_minutes,
                        'totalCost' => $log->total_cost,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send work order time log notification', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['success' => true, 'data' => $log]);
    }

    protected function authorizeCompany(Request $request, WorkOrder $workOrder): void
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            abort(404);
        }
    }
}


