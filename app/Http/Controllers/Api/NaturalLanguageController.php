<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NaturalLanguageService;
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
    public function getContext(Request $request)
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
            return response()->json(['success' => false, 'error' => 'Failed to fetch context: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Process natural language chat query.
     */
    public function chat(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|in:system,user,assistant',
            'messages.*.content' => 'required|string',
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
                'error' => 'Failed to process query: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if OpenAI API key is configured.
     */
    public function checkApiKey(Request $request)
    {
        try {
            $hasApiKey = $this->nlService->hasOpenAIApiKey();
            return response()->json(['success' => true, 'hasApiKey' => $hasApiKey]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'hasApiKey' => false]);
        }
    }
}
