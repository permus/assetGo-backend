<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderPartController extends Controller
{
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


