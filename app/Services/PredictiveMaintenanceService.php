<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PredictiveMaintenance;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PredictiveMaintenanceService
{
    public function __construct(private OpenAIService $openAI) {}

    /**
     * Generate predictions for assets using AI.
     * 
     * @param string|null $companyId Company ID (optional, will use Auth::user() if not provided)
     */
    public function generatePredictions(array $assetIds = [], bool $forceRefresh = false, ?string $companyId = null): array
    {
        if ($companyId === null) {
            $companyId = Auth::user()->company_id;
        }
        
        // If no specific assets provided, get all active assets for the company
        if (empty($assetIds)) {
            $assets = Asset::where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['maintenanceSchedules', 'activities'])
                ->get();
        } else {
            $assets = Asset::where('company_id', $companyId)
                ->whereIn('id', $assetIds)
                ->where('is_active', true)
                ->with(['maintenanceSchedules', 'activities'])
                ->get();
        }

        if ($assets->isEmpty()) {
            throw new \Exception('No assets found for analysis');
        }

        // Filter out assets with invalid purchase dates (future dates or null) - ignore them silently
        $validAssets = $assets->filter(function ($asset) {
            if (!$asset->purchase_date) {
                return false;
            }
            // Ignore assets with future purchase dates
            return $asset->purchase_date <= now();
        });

        // Only throw error if ALL assets are invalid
        if ($validAssets->isEmpty()) {
            throw new \Exception('No assets with valid purchase dates found. All assets must have purchase dates in the past for predictive maintenance analysis.');
        }

        // Log if some assets were filtered out (but continue processing)
        if ($validAssets->count() < $assets->count()) {
            $filteredCount = $assets->count() - $validAssets->count();
            Log::info('Ignored assets with future or invalid purchase dates', [
                'total_assets' => $assets->count(),
                'valid_assets' => $validAssets->count(),
                'ignored_count' => $filteredCount
            ]);
        }

        // Prepare asset data for AI analysis
        $assetData = $this->prepareAssetDataForAI($validAssets);

        // Call OpenAI to generate predictions
        $predictions = $this->callOpenAIForPredictions($assetData);

        // Clear existing predictions if force refresh
        if ($forceRefresh) {
            PredictiveMaintenance::where('company_id', $companyId)->delete();
        }

        // Store predictions in database
        $storedPredictions = $this->storePredictions($predictions, $companyId);

        // Calculate summary
        $summary = $this->calculateSummary($companyId);

        return [
            'predictions' => $storedPredictions,
            'summary' => $summary,
            'generatedAt' => now()->toISOString(),
        ];
    }

    /**
     * Get predictions with optional filtering.
     */
    public function getPredictions(array $filters = []): array
    {
        $companyId = Auth::user()->company_id;
        
        $query = PredictiveMaintenance::with('asset')
            ->where('company_id', $companyId);

        // Apply filters
        if (isset($filters['risk_level'])) {
            $query->where('risk_level', $filters['risk_level']);
        }

        if (isset($filters['min_confidence'])) {
            $query->where('confidence', '>=', $filters['min_confidence']);
        }

        if (isset($filters['date_from'])) {
            $query->where('predicted_failure_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('predicted_failure_date', '<=', $filters['date_to']);
        }

        $predictions = $query->orderBy('risk_level', 'desc')
            ->orderBy('confidence', 'desc')
            ->get();

        $summary = $this->calculateSummary($companyId);

        return [
            'predictions' => $predictions, // Will be transformed by Resource in Controller
            'summary' => $summary,
        ];
    }

    /**
     * Export predictions to CSV.
     */
    public function exportToCsv(array $filters = []): string
    {
        $data = $this->getPredictions($filters);
        $predictions = $data['predictions'];

        $csv = "Asset Name,Asset Type,Risk Level,Confidence,Predicted Failure Date,Recommended Action,Estimated Cost,Preventive Cost,Savings,ROI,Factors\n";

        foreach ($predictions as $prediction) {
            $assetName = $prediction->asset ? $prediction->asset->name : 'Unknown Asset';
            $assetType = $prediction->asset ? $prediction->asset->asset_type : 'Unknown';
            $factors = is_array($prediction->factors) ? implode('; ', $prediction->factors) : '';
            
            $csv .= sprintf(
                '"%s","%s","%s",%s,"%s","%s",%s,%s,%s,%s,"%s"' . "\n",
                $assetName,
                $assetType,
                ucfirst($prediction->risk_level),
                $prediction->confidence,
                $prediction->predicted_failure_date?->format('Y-m-d') ?? '',
                $prediction->recommended_action,
                $prediction->estimated_cost,
                $prediction->preventive_cost,
                $prediction->savings,
                $prediction->roi,
                $factors
            );
        }

        return $csv;
    }

    /**
     * Prepare asset data for AI analysis.
     */
    public function prepareAssetDataForAI($assets): array
    {
        return $assets->map(function ($asset) {
            // Get maintenance history
            $maintenanceHistory = $asset->activities()
                ->where('action', 'like', '%maintenance%')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['action', 'comment', 'created_at']);

            // Skip assets with invalid purchase dates (should already be filtered, but double-check)
            if (!$asset->purchase_date || $asset->purchase_date > now()) {
                // Silently ignore - already filtered out in generatePredictions
                return null;
            }

            // Calculate asset age (in years)
            $age = now()->diffInYears($asset->purchase_date);

            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'type' => $asset->asset_type,
                'manufacturer' => $asset->manufacturer,
                'model' => $asset->model,
                'age' => $age,
                'condition' => $asset->condition ?? 'Unknown',
                'purchase_date' => $asset->purchase_date->format('Y-m-d'),
                'last_maintenance' => $maintenanceHistory->first()?->created_at?->format('Y-m-d'),
                'maintenance_count' => $maintenanceHistory->count(),
                'purchase_price' => $asset->purchase_price ?? 0,
                'current_value' => $asset->current_value ?? $asset->purchase_price ?? 0,
            ];
        })->filter(function ($item) {
            // Remove any null items (from invalid assets)
            return $item !== null;
        })->values()->toArray();
    }

    /**
     * Call OpenAI to generate predictions.
     */
    public function callOpenAIForPredictions(array $assetData): array
    {
        $prompt = $this->buildPredictionPrompt($assetData);
        
        Log::info('Calling OpenAI for predictions', [
            'asset_count' => count($assetData),
            'prompt_length' => strlen($prompt)
        ]);
        
        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ], [
                'response_format' => ['type' => 'json_object'] // Helps ensure JSON response
            ]);
            
            Log::debug('OpenAI response received', [
                'response_type' => gettype($response),
                'is_array' => is_array($response),
                'response_keys' => is_array($response) ? array_keys($response) : null
            ]);
            
            // Extract content from response array
            $content = is_array($response) ? ($response['content'] ?? $response) : $response;
            
            if (empty($content)) {
                Log::error('Empty response from OpenAI', [
                    'response' => $response
                ]);
                throw new \Exception('OpenAI returned an empty response');
            }
            
            // Extract JSON from response (handle markdown code blocks)
            $predictions = $this->extractJsonFromResponse($content);
            
            // Validate predictions structure
            $this->validateAIResponse($predictions);
            
            Log::info('Successfully extracted and validated predictions', [
                'prediction_count' => count($predictions)
            ]);
            
            return $predictions;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $errorContext = [
                'error' => $errorMessage,
                'error_type' => get_class($e),
                'asset_count' => count($assetData),
                'trace' => $e->getTraceAsString()
            ];
            
            // Add more context if it's a validation error
            if (strpos($errorMessage, 'Prediction at index') !== false || 
                strpos($errorMessage, 'not an array') !== false ||
                strpos($errorMessage, 'not a valid array') !== false) {
                $errorContext['likely_cause'] = 'AI returned JSON object instead of array, or response structure mismatch';
                $errorContext['suggestion'] = 'Check OpenAI response format. Consider updating prompt or response_format parameter.';
            }
            
            Log::error('OpenAI prediction generation failed', $errorContext);
            
            // Re-throw with a more user-friendly message if it's a known issue
            if (strpos($errorMessage, 'AI returned an error') !== false) {
                throw new \Exception($errorMessage);
            }
            
            throw new \Exception('Failed to generate predictions: ' . $errorMessage);
        }
    }

    /**
     * Extract JSON from AI response, handling markdown code blocks and JSON objects.
     */
    private function extractJsonFromResponse(string $response): array
    {
        Log::debug('Extracting JSON from AI response', [
            'response_length' => strlen($response),
            'response_preview' => substr($response, 0, 500),
            'has_markdown' => preg_match('/```/', $response) > 0
        ]);

        // Try to decode as-is first
        $decoded = json_decode($response, true);
        
        // Handle JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('JSON decode error', [
                'error' => json_last_error_msg(),
                'error_code' => json_last_error(),
                'response_preview' => substr($response, 0, 500)
            ]);
        } else {
            Log::debug('JSON decoded successfully', [
                'is_array' => is_array($decoded),
                'is_null' => is_null($decoded),
                'type' => gettype($decoded),
                'is_numeric_array' => is_array($decoded) ? $this->isNumericArray($decoded) : false,
                'keys' => is_array($decoded) ? array_keys($decoded) : null
            ]);
        }

        if ($decoded !== null) {
            Log::debug('Processing decoded JSON', [
                'is_array' => is_array($decoded),
                'is_numeric_array' => is_array($decoded) ? $this->isNumericArray($decoded) : false,
                'has_predictions_key' => is_array($decoded) && isset($decoded['predictions']),
                'has_error_key' => is_array($decoded) && isset($decoded['error']),
                'keys' => is_array($decoded) ? array_keys($decoded) : null
            ]);
            
            // Check if it's a JSON object with a "predictions" key
            if (is_array($decoded) && isset($decoded['predictions']) && is_array($decoded['predictions'])) {
                Log::info('Found predictions array in JSON object', [
                    'predictions_count' => count($decoded['predictions'])
                ]);
                return $decoded['predictions'];
            }
            
            // Check if it's a JSON object with an "error" key
            if (is_array($decoded) && isset($decoded['error'])) {
                $errorMessage = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
                Log::error('AI returned error in response', [
                    'error' => $errorMessage,
                    'full_response' => $decoded
                ]);
                throw new \Exception('AI returned an error: ' . $errorMessage);
            }
            
            // Check if it's already a numeric array (list of predictions)
            if (is_array($decoded) && $this->isNumericArray($decoded)) {
                Log::info('Found numeric array (direct predictions list)', [
                    'count' => count($decoded)
                ]);
            return $decoded;
            }
            
            // If it's an associative array but not what we expect, check if it's a single prediction object
            if (is_array($decoded) && !empty($decoded) && !$this->isNumericArray($decoded)) {
                $keys = array_keys($decoded);
                
                // FIRST: Check for assetId or assetName (most reliable indicators) - check this FIRST
                // This is the most common case and should be caught immediately
                if (isset($decoded['assetId']) || isset($decoded['assetName'])) {
                    // Check for wrapper keys that would indicate it's NOT a single prediction
                    $wrapperKeys = ['predictions', 'error', 'data', 'results', 'status', 'message'];
                    $hasWrapperKeys = !empty(array_intersect($keys, $wrapperKeys));
                    
                    // If no wrapper keys, definitely a single prediction
                    if (!$hasWrapperKeys) {
                        Log::info('Found assetId/assetName in object (no wrapper keys), treating as single prediction', [
                            'keys' => $keys,
                            'has_assetId' => isset($decoded['assetId']),
                            'has_assetName' => isset($decoded['assetName'])
                        ]);
                        return [$decoded];
                    }
                }
                
                // SECOND: Check if it looks like a single prediction object (has required prediction fields)
                // This handles cases where OpenAI returns a single object instead of an array
                $predictionFields = ['assetId', 'assetName', 'riskLevel', 'confidence', 'recommendedAction', 'predictedFailureDate'];
                $matchingFields = array_intersect($keys, $predictionFields);
                
                // Check for common wrapper keys that would indicate it's NOT a single prediction
                $wrapperKeys = ['predictions', 'error', 'data', 'results', 'status', 'message'];
                $hasWrapperKeys = !empty(array_intersect($keys, $wrapperKeys));
                
                // If it has at least one prediction field and no wrapper keys, treat as single prediction
                if (count($matchingFields) >= 1 && !$hasWrapperKeys) {
                    Log::info('Found single prediction object (matching fields), wrapping in array', [
                        'keys' => $keys,
                        'matching_fields' => $matchingFields,
                        'has_wrapper_keys' => $hasWrapperKeys
                    ]);
                    return [$decoded];
                }
                
                // THIRD: Even if wrapper keys exist, if it has multiple prediction fields, it might still be a single prediction
                // (Some AI responses might include extra metadata)
                if (count($matchingFields) >= 3) {
                    Log::info('Found object with multiple prediction fields, treating as single prediction', [
                        'keys' => $keys,
                        'matching_fields' => $matchingFields
                    ]);
                    return [$decoded];
                }
                
                Log::warning('Unexpected JSON structure from AI', [
                    'keys' => $keys,
                    'has_wrapper_keys' => $hasWrapperKeys,
                    'matching_fields' => $matchingFields,
                    'structure_preview' => array_slice($decoded, 0, 3, true)
                ]);
                
                // Try to find a predictions key with different casing
                foreach (['predictions', 'Predictions', 'PREDICTIONS', 'data', 'Data', 'results', 'Results'] as $key) {
                    if (isset($decoded[$key]) && is_array($decoded[$key])) {
                        Log::debug("Found predictions array under key: {$key}");
                        return $decoded[$key];
                    }
                }
            }
        }

        // Try to extract JSON from markdown code blocks
        // Pattern: ```json [...] ``` or ``` [...] ```
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $jsonContent = trim($matches[1]);
            $decoded = json_decode($jsonContent, true);
            if ($decoded !== null && is_array($decoded)) {
                // Handle same cases as above
                if (isset($decoded['predictions']) && is_array($decoded['predictions'])) {
                    return $decoded['predictions'];
                }
                if (isset($decoded['error'])) {
                    $errorMessage = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
                    throw new \Exception('AI returned an error: ' . $errorMessage);
                }
                if ($this->isNumericArray($decoded)) {
                return $decoded;
                }
                
                // Check if it's a single prediction object (apply same logic as main extraction)
                $keys = array_keys($decoded);
                
                // FIRST: Check for assetId or assetName (most reliable)
                if (isset($decoded['assetId']) || isset($decoded['assetName'])) {
                    $wrapperKeys = ['predictions', 'error', 'data', 'results', 'status', 'message'];
                    $hasWrapperKeys = !empty(array_intersect($keys, $wrapperKeys));
                    
                    if (!$hasWrapperKeys) {
                        Log::info('Found assetId/assetName in markdown object, treating as single prediction', [
                            'keys' => $keys
                        ]);
                        return [$decoded];
                    }
                }
                
                // SECOND: Check for prediction fields
                $predictionFields = ['assetId', 'assetName', 'riskLevel', 'confidence', 'recommendedAction', 'predictedFailureDate'];
                $matchingFields = array_intersect($keys, $predictionFields);
                $wrapperKeys = ['predictions', 'error', 'data', 'results', 'status', 'message'];
                $hasWrapperKeys = !empty(array_intersect($keys, $wrapperKeys));
                
                if (count($matchingFields) >= 1 && !$hasWrapperKeys) {
                    Log::info('Found single prediction object in markdown (matching fields), wrapping in array', [
                        'keys' => $keys,
                        'matching_fields' => $matchingFields
                    ]);
                    return [$decoded];
                }
                
                // THIRD: Even with wrapper keys, if multiple prediction fields exist, treat as single prediction
                if (count($matchingFields) >= 3) {
                    Log::info('Found object with multiple prediction fields in markdown, treating as single prediction', [
                        'keys' => $keys,
                        'matching_fields' => $matchingFields
                    ]);
                    return [$decoded];
                }
            }
        }

        // Try to find JSON array in the response
        if (preg_match('/\[\s*\{[\s\S]*\}\s*\]/', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && $this->isNumericArray($decoded)) {
                return $decoded;
            }
        }

        // FINAL SAFETY CHECK: Before throwing error, check if we have a decoded result that might be a single prediction
        // This catches any edge cases where single objects weren't detected earlier
        if (isset($decoded) && $decoded !== null && is_array($decoded) && !$this->isNumericArray($decoded)) {
            $keys = array_keys($decoded);
            $predictionFields = ['assetId', 'assetName', 'riskLevel', 'confidence', 'recommendedAction', 'predictedFailureDate'];
            $hasPredictionFields = !empty(array_intersect($keys, $predictionFields));
            
            // If it has any prediction-like fields, wrap it as a single prediction
            if ($hasPredictionFields || isset($decoded['assetId']) || isset($decoded['assetName'])) {
                Log::warning('Final safety check: Found single prediction object, wrapping in array', [
                    'keys' => $keys,
                    'has_prediction_fields' => $hasPredictionFields
                ]);
                return [$decoded];
            }
        }

        Log::error('Failed to extract valid JSON from AI response', [
            'response' => $response,
            'json_error' => json_last_error_msg(),
            'decoded_type' => isset($decoded) ? gettype($decoded) : 'null',
            'decoded_keys' => (isset($decoded) && is_array($decoded)) ? array_keys($decoded) : null
        ]);
        throw new \Exception('Invalid response format from AI: Could not extract valid JSON array. Response: ' . substr($response, 0, 200));
    }

    /**
     * Check if array is numeric (list) vs associative (object).
     */
    private function isNumericArray(array $array): bool
    {
        if (empty($array)) {
            return true; // Empty array is considered numeric
        }
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Validate AI response structure and fields.
     */
    private function validateAIResponse(array $predictions): void
    {
        if (empty($predictions)) {
            throw new \Exception('AI returned empty predictions array');
        }

        // Ensure predictions is a numeric array (list), not an associative array (object)
        if (!$this->isNumericArray($predictions)) {
            Log::error('Predictions is not a numeric array', [
                'keys' => array_keys($predictions),
                'sample' => array_slice($predictions, 0, 2, true)
            ]);
            throw new \Exception('AI response is not a valid array of predictions. Expected a JSON array, but got an object with keys: ' . implode(', ', array_keys($predictions)));
        }

        Log::debug('Validating predictions', [
            'count' => count($predictions)
        ]);

        $requiredFields = ['assetId', 'assetName', 'riskLevel', 'confidence'];
        $validRiskLevels = ['high', 'medium', 'low'];

        foreach ($predictions as $index => $prediction) {
            // Validate that each prediction is an array
            if (!is_array($prediction)) {
                Log::error('Prediction is not an array', [
                    'index' => $index,
                    'type' => gettype($prediction),
                    'value' => is_string($prediction) ? substr($prediction, 0, 100) : $prediction
                ]);
                throw new \Exception("Prediction at index {$index} is not an array. Got type: " . gettype($prediction) . (is_string($prediction) ? " with value: " . substr($prediction, 0, 100) : ""));
            }

            // Validate required fields
            foreach ($requiredFields as $field) {
                if (!isset($prediction[$field])) {
                    Log::warning('Missing required field in prediction', [
                        'index' => $index,
                        'field' => $field,
                        'available_fields' => array_keys($prediction)
                    ]);
                    throw new \Exception("Missing required field '{$field}' in prediction at index {$index}. Available fields: " . implode(', ', array_keys($prediction)));
                }
            }

            // Validate risk level
            $riskLevel = strtolower($prediction['riskLevel'] ?? '');
            if (!in_array($riskLevel, $validRiskLevels)) {
                throw new \Exception("Invalid risk level '{$prediction['riskLevel']}' in prediction at index {$index}. Must be one of: " . implode(', ', $validRiskLevels));
            }

            // Validate confidence (0-100)
            $confidence = $prediction['confidence'];
            if (!is_numeric($confidence)) {
                throw new \Exception("Invalid confidence value '{$confidence}' in prediction at index {$index}. Must be a number between 0 and 100");
            }
            $confidence = (float) $confidence;
            if ($confidence < 0 || $confidence > 100) {
                throw new \Exception("Invalid confidence value '{$confidence}' in prediction at index {$index}. Must be between 0 and 100");
            }

            // Validate predicted failure date format (if provided)
            if (isset($prediction['predictedFailureDate']) && !empty($prediction['predictedFailureDate'])) {
                $dateStr = $prediction['predictedFailureDate'];
                $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
                if (!$date || $date->format('Y-m-d') !== $dateStr) {
                    throw new \Exception("Invalid date format '{$dateStr}' in prediction at index {$index}. Expected YYYY-MM-DD format");
                }
            }

            // Validate numeric fields
            $numericFields = ['estimatedCost', 'preventiveCost', 'savings'];
            foreach ($numericFields as $field) {
                if (isset($prediction[$field]) && !is_numeric($prediction[$field])) {
                    throw new \Exception("Invalid numeric value for '{$field}' in prediction at index {$index}. Got: " . gettype($prediction[$field]) . " with value: " . $prediction[$field]);
                }
            }
        }

        Log::debug('All predictions validated successfully', [
            'count' => count($predictions)
        ]);
    }

    /**
     * Build the prompt for AI prediction generation.
     */
    public function buildPredictionPrompt(array $assetData): string
    {
        return "You are an expert predictive maintenance AI. Analyze the following assets and predict potential failures.

Asset Data:
" . json_encode($assetData, JSON_PRETTY_PRINT) . "

IMPORTANT: Return ONLY valid JSON, no markdown, no explanations, no code blocks. Return a JSON array directly.

Return a JSON array of prediction objects with this exact structure:
[
  {
    \"assetId\": \"string\",
    \"assetName\": \"string\",
    \"riskLevel\": \"high|medium|low\",
    \"predictedFailureDate\": \"YYYY-MM-DD\",
    \"confidence\": 0-100,
    \"recommendedAction\": \"string\",
    \"estimatedCost\": 0,
    \"preventiveCost\": 0,
    \"savings\": 0,
    \"factors\": [\"string1\", \"string2\"],
    \"timeline\": {
      \"immediate\": [\"action1\"],
      \"shortTerm\": [\"action2\"],
      \"longTerm\": [\"action3\"]
    }
  }
]

Consider factors like:
- Asset age and usage patterns
- Maintenance history and frequency
- Asset type and criticality
- Environmental conditions
- Manufacturer recommendations

Be realistic with cost estimates and failure dates. Focus on actionable insights.

Remember: Return ONLY the JSON array, nothing else.";
    }

    /**
     * Store predictions in the database.
     */
    public function storePredictions(array $predictions, string $companyId): array
    {
        $storedPredictions = [];
        
        foreach ($predictions as $index => $prediction) {
            // Validate required fields (should already be validated, but double-check)
            if (!isset($prediction['assetId']) || !isset($prediction['assetName'])) {
                Log::warning('Skipping prediction with missing required fields', [
                    'index' => $index,
                    'prediction' => $prediction
                ]);
                continue;
            }

            try {
                $storedPrediction = PredictiveMaintenance::create([
                    'asset_id' => $prediction['assetId'],
                    'risk_level' => strtolower($prediction['riskLevel'] ?? 'medium'),
                    'predicted_failure_date' => !empty($prediction['predictedFailureDate']) 
                        ? $prediction['predictedFailureDate'] 
                        : null,
                    'confidence' => max(0, min(100, (float) ($prediction['confidence'] ?? 50))),
                    'recommended_action' => $prediction['recommendedAction'] ?? 'Schedule maintenance inspection',
                    'estimated_cost' => max(0, (float) ($prediction['estimatedCost'] ?? 0)),
                    'preventive_cost' => max(0, (float) ($prediction['preventiveCost'] ?? 0)),
                    'savings' => (float) ($prediction['savings'] ?? 0),
                    'factors' => is_array($prediction['factors'] ?? null) ? $prediction['factors'] : [],
                    'timeline' => is_array($prediction['timeline'] ?? null) ? $prediction['timeline'] : [],
                    'company_id' => $companyId,
                ]);

                $storedPrediction->load('asset');
                $storedPredictions[] = $storedPrediction;
            } catch (\Exception $e) {
                Log::error('Failed to store prediction', [
                    'index' => $index,
                    'asset_id' => $prediction['assetId'] ?? null,
                    'error' => $e->getMessage()
                ]);
                // Continue processing other predictions
                continue;
            }
        }

        return $storedPredictions;
    }

    /**
     * Calculate summary statistics.
     */
    public function calculateSummary(string $companyId): array
    {
        $summary = DB::table('predictive_maintenance')
            ->where('company_id', $companyId)
            ->selectRaw('
                COUNT(DISTINCT asset_id) as total_assets,
                COUNT(*) as total_predictions,
                COUNT(CASE WHEN risk_level = "high" THEN 1 END) as high_risk_count,
                COALESCE(SUM(savings), 0) as total_savings,
                COALESCE(AVG(confidence), 0) as avg_confidence,
                MAX(created_at) as last_updated
            ')
            ->first();

        return [
            'totalAssets' => $summary->total_assets ?? 0,
            'highRiskCount' => $summary->high_risk_count ?? 0,
            'totalSavings' => (float) ($summary->total_savings ?? 0),
            'averageConfidence' => round((float) ($summary->avg_confidence ?? 0), 2),
            'lastUpdated' => $summary->last_updated,
        ];
    }
}
