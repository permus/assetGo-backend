<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIImageRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AIImageRecognitionController extends Controller {
    public function __construct(private AIImageRecognitionService $svc) {}

    public function analyze(Request $req) {
        // Check request size early
        $contentLength = $req->header('Content-Length');
        if ($contentLength && $contentLength > 20 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'Request too large. Please use smaller images.'
            ], 413);
        }

        $data = $req->validate([
            'images'   => ['required','array','min:1','max:5'],
            'images.*' => ['required','string','max:10485760','regex:/^[A-Za-z0-9+\/=]+$/'], // Clean base64 only
        ]);

        try {
            $dto = $this->svc->processImages($data['images']);

            // Log successful analysis (without sensitive data)
            \Log::info('AI Image Recognition completed', [
                'user_id' => $req->user()->id,
                'company_id' => $req->user()->company_id,
                'image_count' => count($data['images']),
                'confidence' => $dto->confidence,
                'asset_type' => $dto->assetType,
                'fields_found' => $dto->evidence['fieldsFound'] ?? []
            ]);

            return response()->json(['success' => true, 'data' => $dto->toArray()]);

        } catch (\Exception $e) {
            // Log error without sensitive data
            \Log::error('AI Image Recognition failed', [
                'user_id' => $req->user()->id,
                'company_id' => $req->user()->company_id,
                'error' => $e->getMessage(),
                'image_count' => count($data['images'] ?? [])
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function feedback(Request $req) {
        $data = $req->validate([
            'recognition_id' => ['required','exists:ai_recognition_history,id'],
            'feedback_type'  => ['required', Rule::in(['positive','negative','correction'])],
            'corrections'    => ['array'],
        ]);
        // Minimal stub — store feedback on history
        $rec = \App\Models\AIRecognitionHistory::findOrFail($data['recognition_id']);
        $rec->feedback_type = $data['feedback_type'];
        $rec->feedback_data = $data['corrections'] ?? null;
        $rec->save();

        return response()->json(['success' => true]);
    }

    public function history(Request $req) {
        $items = \App\Models\AIRecognitionHistory::query()
            ->where('user_id', $req->user()->id)
            ->latest()->paginate(20);
        return response()->json(['success' => true, 'data' => $items]);
    }
}
