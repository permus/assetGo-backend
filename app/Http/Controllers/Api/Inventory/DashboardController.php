<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{InventoryPart, InventoryStock, InventoryTransaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function overview(Request $request)
    {
        $companyId = $request->user()->company_id;
        $totalValue = InventoryStock::forCompany($companyId)->select(DB::raw('SUM(on_hand * average_cost) as v'))->value('v') ?? 0;
        $totalParts = InventoryPart::forCompany($companyId)->count();
        $lowStockParts = InventoryPart::forCompany($companyId)->where('reorder_point', '>', 0)
            ->whereHas('stocks', function($q){ $q->whereColumn('available', '<=', 'reorder_point'); })->count();
        $recent = InventoryTransaction::forCompany($companyId)->with('part')->orderByDesc('id')->limit(10)->get();
        return response()->json(['success' => true, 'data' => compact('totalValue','totalParts','lowStockParts','recent')]);
    }
}


