<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = InventoryTransaction::with(['part','location'])->forCompany($companyId);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('part_id')) {
            $query->where('part_id', $request->part_id);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date.' 00:00:00');
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date.' 23:59:59');
        }

        if ($search = $request->get('keyword')) {
            // URL decode the search term in case it's encoded
            $search = urldecode($search);
            
            // Debug: Log the search term
            \Log::info('Transaction search keyword: ' . $search);
            
            $query->where(function($q) use ($search) {
                // Search in part name, part number, and description
                $q->whereHas('part', function($partQuery) use ($search) {
                    $partQuery->where('name', 'like', "%$search%")
                             ->orWhere('part_number', 'like', "%$search%")
                             ->orWhere('description', 'like', "%$search%");
                })
                // Search in location name
                ->orWhereHas('location', function($locationQuery) use ($search) {
                    $locationQuery->where('name', 'like', "%$search%");
                })
                // Search in transaction fields
                ->orWhere('notes', 'like', "%$search%")
                ->orWhere('reason', 'like', "%$search%")
                ->orWhere('reference', 'like', "%$search%");
            });
        }

        $perPage = min($request->get('per_page', 15), 100);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('id')->paginate($perPage)]);
    }
}


