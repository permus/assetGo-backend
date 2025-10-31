<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NaturalLanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class NaturalLanguageController extends Controller
{
    public function __construct(private NaturalLanguageService $nlService) {}

    /**
     * Get asset context for natural language queries.
     */
    public function getContext(Request $request): JsonResponse
    {
        $companyId = Auth::user()->company_id;

        try {
            $context = $this->nlService->getAssetContext($companyId);
            return response()->json(['success' => true, 'data' => $context]);
        } catch (Exception $e) {
            Log::error('Failed to fetch asset context for NLQ', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to fetch context: ' . $e->getMessage()
                    : 'Failed to fetch context. Please try again later.'
            ], 500);
        }
    }

    /**
     * Process natural language chat query.
     */
    public function chat(Request $request): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        
        $request->validate([
            'messages' => 'required|array|max:20',
            'messages.*.role' => 'required|in:system,user,assistant',
            'messages.*.content' => 'required|string|max:5000',
            'assetContext' => 'required|array',
            'companyContext' => 'sometimes|array'
        ]);

        try {
            $response = $this->nlService->processChatQuery(
                $request->input('messages'),
                $request->input('assetContext'),
                $request->input('companyContext', []),
                $companyId
            );

            return response()->json($response);
        } catch (Exception $e) {
            Log::error('Failed to process NLQ chat', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to process query: ' . $e->getMessage()
                    : 'Failed to process query. Please try again later.'
            ], 500);
        }
    }

    /**
     * Check if OpenAI API key is configured.
     */
    public function checkApiKey(Request $request): JsonResponse
    {
        try {
            $hasApiKey = $this->nlService->hasOpenAIApiKey();
            return response()->json(['success' => true, 'hasApiKey' => $hasApiKey]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'hasApiKey' => false]);
        }
    }
}
