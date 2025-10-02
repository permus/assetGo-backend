<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\{Asset, WorkOrder};
use Illuminate\Support\Facades\DB;

class ReportsService
{
    public function assetSummary(int $companyId, Request $request): array
    {
        $q = Asset::query()->where('company_id', $companyId);
        $this->applyCommonFiltersToAssets($q, $request);
        $total = (clone $q)->count();
        $active = (clone $q)->where(function($x){ $x->where('is_active',1)->orWhereNull('is_active'); })->count();
        $byStatus = (clone $q)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')->pluck('cnt','status');
        return [ 'total' => $total, 'active' => $active, 'by_status' => $byStatus ];
    }

    public function maintenanceSummary(int $companyId, Request $request): array
    {
        $q = WorkOrder::query()->where('company_id', $companyId);
        $this->applyCommonFiltersToWorkOrders($q, $request);
        $total = (clone $q)->count();
        $completed = (clone $q)->whereHas('status', fn($s)=>$s->where('slug','completed'))->count();
        $open = $total - $completed;
        return [ 'total' => $total, 'completed' => $completed, 'open' => $open ];
    }

    private function applyCommonFiltersToAssets($q, Request $request): void
    {
        if ($request->filled('location_ids')) $q->whereIn('location_id', (array) $request->location_ids);
        if ($request->filled('asset_ids')) $q->whereIn('id', (array) $request->asset_ids);
        if ($request->filled('category_id')) $q->whereIn('category_id', (array) $request->category_id);
        if ($request->filled('status_id')) $q->whereIn('status', (array) $request->status_id);
        if ($request->filled('date_from')) $q->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('created_at', '<=', $request->date_to);
    }

    private function applyCommonFiltersToWorkOrders($q, Request $request): void
    {
        if ($request->filled('location_ids')) $q->whereIn('location_id', (array) $request->location_ids);
        if ($request->filled('asset_ids')) $q->whereIn('asset_id', (array) $request->asset_ids);
        if ($request->filled('assigned_to')) $q->whereIn('assigned_to', (array) $request->assigned_to);
        if ($request->filled('category_id')) $q->whereIn('category_id', (array) $request->category_id);
        if ($request->filled('priority_id')) $q->whereIn('priority_id', (array) $request->priority_id);
        if ($request->filled('status_id')) $q->whereIn('status_id', (array) $request->status_id);
        if ($request->filled('date_from')) $q->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('created_at', '<=', $request->date_to);
    }
}


