<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderTemplate;
use Illuminate\Http\Request;

class PurchaseOrderTemplateController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $templates = PurchaseOrderTemplate::forCompany($companyId)->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_data' => 'required|array',
            'is_active' => 'nullable|boolean'
        ]);
        $data['company_id'] = $request->user()->company_id;
        $data['created_by'] = $request->user()->id;
        $template = PurchaseOrderTemplate::create($data);
        return response()->json(['success' => true, 'data' => $template], 201);
    }

    public function update(Request $request, PurchaseOrderTemplate $purchaseOrderTemplate)
    {
        if ($purchaseOrderTemplate->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'template_data' => 'sometimes|required|array',
            'is_active' => 'nullable|boolean'
        ]);
        $purchaseOrderTemplate->update($data);
        return response()->json(['success' => true, 'data' => $purchaseOrderTemplate]);
    }

    public function destroy(Request $request, PurchaseOrderTemplate $purchaseOrderTemplate)
    {
        if ($purchaseOrderTemplate->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $purchaseOrderTemplate->delete();
        return response()->json(['success' => true]);
    }
}


