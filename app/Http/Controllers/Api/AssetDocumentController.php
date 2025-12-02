<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetDocumentController extends Controller
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
        
        $documents = $asset->documents()
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json(['success' => true, 'data' => $documents]);
    }

    public function store(Request $request, Asset $asset)
    {
        $this->authorizeCompany($request, $asset);

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png|max:10240', // 10MB max
            'document_name' => 'required|string|max:255',
            'document_type' => 'required|in:manual,certificate,warranty,other',
        ]);

        $file = $request->file('document');
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $filePath = 'assets/documents/' . $asset->id . '/' . $fileName;
        
        $storedPath = Storage::disk('public')->putFileAs(
            'assets/documents/' . $asset->id,
            $file,
            $fileName
        );

        $document = AssetDocument::create([
            'asset_id' => $asset->id,
            'document_path' => $storedPath,
            'document_name' => $validated['document_name'],
            'document_type' => $validated['document_type'],
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $document,
            'message' => 'Document uploaded successfully'
        ], 201);
    }

    public function update(Request $request, Asset $asset, AssetDocument $document)
    {
        $this->authorizeCompany($request, $asset);
        
        if ($document->asset_id !== $asset->id) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        $validated = $request->validate([
            'document_name' => 'sometimes|required|string|max:255',
            'document_type' => 'sometimes|required|in:manual,certificate,warranty,other',
        ]);

        $document->update($validated);

        return response()->json([
            'success' => true,
            'data' => $document,
            'message' => 'Document updated successfully'
        ]);
    }

    public function destroy(Request $request, Asset $asset, AssetDocument $document)
    {
        $this->authorizeCompany($request, $asset);
        
        if ($document->asset_id !== $asset->id) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully'
        ]);
    }
}
