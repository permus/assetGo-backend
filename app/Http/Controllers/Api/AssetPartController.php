<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\InventoryPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetPartController extends Controller
{
    protected function authorizeCompany(Request $request, Asset $asset)
    {
        if ($asset->company_id !== $request->user()->company_id) {
            abort(403, 'Unauthorized access to asset.');
        }
    }

    public function index(Request $request, Asset $asset)
    {
        $this->authorizeCompany($request, $asset);
        
        $parts = $asset->inventoryParts()
            ->with(['stocks.location'])
            ->orderBy('name')
            ->get()
            ->map(function ($part) use ($asset) {
                return [
                    'id' => $part->pivot->id ?? null,
                    'asset_id' => $asset->id,
                    'part_id' => $part->id,
                    'qty' => $part->pivot->qty ?? 1,
                    'part' => [
                        'id' => $part->id,
                        'name' => $part->name,
                        'part_number' => $part->part_number,
                        'uom' => $part->uom,
                        'unit_cost' => $part->unit_cost,
                        'maintenance_category' => $part->maintenance_category,
                        'total_available_stock' => $part->stocks->sum('available'),
                        'locations_with_stock' => $part->stocks->map(function ($stock) {
                            return [
                                'id' => $stock->location->id,
                                'name' => $stock->location->name,
                                'available' => $stock->available,
                            ];
                        })
                    ],
                    'created_at' => $part->pivot->created_at ?? null,
                    'updated_at' => $part->pivot->updated_at ?? null,
                ];
            });
        
        return response()->json(['success' => true, 'data' => $parts]);
    }

    public function store(Request $request, Asset $asset)
    {
        $this->authorizeCompany($request, $asset);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.001',
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
                'message' => 'Cannot add archived parts to asset: ' . $archivedList
            ], 422);
        }

        // Prepare pivot data with quantities
        $pivotData = [];
        foreach ($validated['items'] as $item) {
            $pivotData[$item['part_id']] = ['qty' => $item['qty']];
        }

        // Sync parts with quantities
        $asset->inventoryParts()->syncWithoutDetaching($pivotData);

        // Reload parts with relationships
        $parts = $asset->inventoryParts()
            ->with(['stocks.location'])
            ->whereIn('inventory_parts.id', $partIds)
            ->get()
            ->map(function ($part) use ($asset) {
                return [
                    'id' => $part->pivot->id ?? null,
                    'asset_id' => $asset->id,
                    'part_id' => $part->id,
                    'qty' => $part->pivot->qty ?? 1,
                    'part' => [
                        'id' => $part->id,
                        'name' => $part->name,
                        'part_number' => $part->part_number,
                        'uom' => $part->uom,
                        'unit_cost' => $part->unit_cost,
                        'maintenance_category' => $part->maintenance_category,
                    ],
                    'created_at' => $part->pivot->created_at ?? null,
                    'updated_at' => $part->pivot->updated_at ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $parts,
            'message' => 'Parts added to asset successfully'
        ]);
    }

    public function update(Request $request, Asset $asset, $partId)
    {
        $this->authorizeCompany($request, $asset);

        $validated = $request->validate([
            'qty' => 'required|numeric|min:0.001',
        ]);

        // Check if part exists and is linked to asset
        $part = $asset->inventoryParts()->find($partId);
        if (!$part) {
            return response()->json([
                'success' => false,
                'message' => 'Part not found or not linked to this asset'
            ], 404);
        }

        // Update pivot quantity
        $asset->inventoryParts()->updateExistingPivot($partId, [
            'qty' => $validated['qty']
        ]);

        // Reload the part
        $updatedPart = $asset->inventoryParts()
            ->with(['stocks.location'])
            ->find($partId);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $updatedPart->pivot->id ?? null,
                'asset_id' => $asset->id,
                'part_id' => $updatedPart->id,
                'qty' => $updatedPart->pivot->qty,
                'part' => [
                    'id' => $updatedPart->id,
                    'name' => $updatedPart->name,
                    'part_number' => $updatedPart->part_number,
                    'uom' => $updatedPart->uom,
                    'unit_cost' => $updatedPart->unit_cost,
                ],
            ],
            'message' => 'Part quantity updated successfully'
        ]);
    }

    public function destroy(Request $request, Asset $asset, $partId)
    {
        $this->authorizeCompany($request, $asset);

        // Check if part exists and is linked to asset
        $part = $asset->inventoryParts()->find($partId);
        if (!$part) {
            return response()->json([
                'success' => false,
                'message' => 'Part not found or not linked to this asset'
            ], 404);
        }

        // Detach the part
        $asset->inventoryParts()->detach($partId);

        return response()->json([
            'success' => true,
            'message' => 'Part removed from asset successfully'
        ]);
    }
}
