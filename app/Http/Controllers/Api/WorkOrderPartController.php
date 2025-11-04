<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryPart;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderPartController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeCompany($request, $workOrder);
        $items = WorkOrderPart::with('part')
            ->where('work_order_id', $workOrder->id)
            ->orderByDesc('id')
            ->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeCompany($request, $workOrder);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.location_id' => 'nullable|integer',
        ]);

        // Validate that parts are not archived
        $partIds = collect($validated['items'])->pluck('part_id')->unique();
        $archivedParts = InventoryPart::whereIn('id', $partIds)
            ->where('is_archived', true)
            ->where('company_id', $request->user()->company_id)
            ->get(['id', 'part_number', 'name']);

        if ($archivedParts->isNotEmpty()) {
            $archivedList = $archivedParts->map(function ($part) {
                return $part->part_number . ' - ' . $part->name;
            })->implode(', ');

            return response()->json([
                'success' => false,
                'message' => 'Cannot add archived parts to work order: ' . $archivedList
            ], 422);
        }

        $created = [];
        DB::transaction(function () use ($validated, $workOrder, &$created) {
            foreach ($validated['items'] as $row) {
                $created[] = WorkOrderPart::create([
                    'work_order_id' => $workOrder->id,
                    'part_id' => $row['part_id'],
                    'qty' => $row['qty'],
                    'unit_cost' => $row['unit_cost'] ?? null,
                    'location_id' => $row['location_id'] ?? null,
                    'status' => 'reserved',
                ]);
            }
        });

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'work_order',
                    'action' => 'parts_added',
                    'title' => 'Parts Added to Work Order',
                    'message' => $this->notificationService->formatWorkOrderMessage('parts_added', $workOrder->title),
                    'data' => [
                        'workOrderId' => $workOrder->id,
                        'workOrderTitle' => $workOrder->title,
                        'partCount' => count($created),
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

            // Notify work order assignees
            $assignedUserIds = $this->notificationService->getWorkOrderAssignees($workOrder->id);
            if (!empty($assignedUserIds)) {
                // Exclude the creator from notifications
                $assignedUserIds = array_filter($assignedUserIds, fn($id) => $id !== $creator->id);
                if (!empty($assignedUserIds)) {
                    $this->notificationService->createForUsers(
                        array_values($assignedUserIds),
                        [
                            'company_id' => $creator->company_id,
                            'type' => 'work_order',
                            'action' => 'parts_added',
                            'title' => 'Parts Added to Your Work Order',
                            'message' => "Parts were added to work order '{$workOrder->title}'",
                            'data' => [
                                'workOrderId' => $workOrder->id,
                                'workOrderTitle' => $workOrder->title,
                                'partCount' => count($created),
                                'createdBy' => [
                                    'id' => $creator->id,
                                    'name' => $creator->first_name . ' ' . $creator->last_name,
                                ],
                            ],
                            'created_by' => $creator->id,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send work order parts notification', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['success' => true, 'data' => $created], 201);
    }

    public function update(Request $request, WorkOrder $workOrder, WorkOrderPart $part)
    {
        $this->authorizeCompany($request, $workOrder);
        if ($part->work_order_id !== $workOrder->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $data = $request->validate([
            'qty' => 'nullable|numeric|min:0.001',
            'unit_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:reserved,consumed',
        ]);
        $part->update($data);
        return response()->json(['success' => true, 'data' => $part]);
    }

    public function destroy(Request $request, WorkOrder $workOrder, WorkOrderPart $part)
    {
        $this->authorizeCompany($request, $workOrder);
        if ($part->work_order_id !== $workOrder->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $part->delete();
        return response()->json(['success' => true]);
    }

    private function authorizeCompany(Request $request, WorkOrder $workOrder): void
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            abort(404, 'Work order not found');
        }
    }
}


