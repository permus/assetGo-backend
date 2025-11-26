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
        $startTime = microtime(true);
        $companyId = Auth::user()->company_id;
        
        // Only validate messages - assetContext and companyContext are fetched automatically by backend
        $request->validate([
            'messages' => 'required|array|max:20',
            'messages.*.role' => 'required|in:system,user,assistant',
            'messages.*.content' => 'required|string|max:5000',
        ]);

        try {
            Log::info('NLQ chat endpoint called', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'messages_count' => count($request->input('messages', [])),
            ]);

            // Backend automatically fetches assetContext and companyContext
            // Get from request if provided (for backward compatibility), otherwise null (will be auto-fetched)
            $assetContext = $request->has('assetContext') ? $request->input('assetContext') : null;
            $companyContext = $request->has('companyContext') ? $request->input('companyContext') : null;
            
            $response = $this->nlService->processChatQuery(
                $request->input('messages'),
                $assetContext, // Will be auto-fetched by backend if null/empty
                $companyContext, // Will be auto-fetched by backend if null/empty
                $companyId
            );

            $elapsedTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('NLQ chat endpoint completed', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'success' => $response['success'] ?? false,
                'elapsed_time_ms' => $elapsedTime,
            ]);

            return response()->json($response);
        } catch (Exception $e) {
            $elapsedTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('Failed to process NLQ chat', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'elapsed_time_ms' => $elapsedTime,
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
