<?php

namespace App\Services;

use GuzzleHttp\Client;

class OpenAIService {
    public function analyzeImages(array $dataUrls, string $prompt): array {
        $apiKey = config('openai.api_key');
        abort_if(empty($apiKey), 500, 'OPENAI_API_KEY missing. Please configure your OpenAI API key in the environment variables.');

        // Enforce request size limits (base64 is ~33% larger)
        $totalSize = array_sum(array_map('strlen', $dataUrls));
        abort_if($totalSize > 20 * 1024 * 1024, 413, 'Request too large. Please use smaller images.');

        // Mock response disabled - use real OpenAI API
        // if (config('app.env') === 'local' && config('openai.use_mock', false)) {
        //     return $this->getMockResponse($dataUrls, $prompt);
        // }

        // Log API request for debugging
        \Log::info('OpenAI API Request', [
            'image_count' => count($dataUrls),
            'prompt_length' => strlen($prompt),
            'total_size' => $totalSize
        ]);


        // Prepare messages for chat completions API
        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => array_merge(
                    [['type' => 'text', 'text' => $prompt]],
                    array_map(fn($url) => [
                        'type' => 'image_url',
                        'image_url' => ['url' => $url, 'detail' => 'high']
                    ], $dataUrls)
                ),
            ],
        ];

        $client = new Client(['timeout' => config('openai.timeout')]);
        
        // Retry logic with exponential backoff
        $maxRetries = 2;
        $baseDelay = 1;
        
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $resp = $client->post(config('openai.base_url'), [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => config('openai.model'),
                        'messages' => $messages,
                        'temperature' => (float) config('openai.temperature'),
                        'max_tokens' => (int) config('openai.max_tokens'),
                    ],
                ]);

                $json = json_decode((string) $resp->getBody(), true);
                $content = $json['choices'][0]['message']['content'] ?? '';
                
                // Validate JSON structure
                $result = $this->validateAndParseJson($content);
                if ($result) {
                    return $result;
                }
                
                // If validation fails and we have retries left, try again
                if ($attempt < $maxRetries) {
                    $delay = $baseDelay * pow(2, $attempt) + rand(0, 1000) / 1000; // Jitter
                    sleep($delay);
                    continue;
                }
                
                abort(502, 'Unable to parse AI response. Please try with clearer images.');
                
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = $e->getResponse();
                $responseBody = $response ? $response->getBody()->getContents() : '';
                $responseData = json_decode($responseBody, true);
                
                // Handle specific API key errors
                if ($e->getCode() === 401) {
                    $errorMessage = $responseData['error']['message'] ?? 'Invalid API key';
                    abort(401, "OpenAI API Error: {$errorMessage}. Please check your API key configuration.");
                }
                
                // Handle rate limiting
                if ($e->getCode() === 429) {
                    if ($attempt < $maxRetries) {
                        $delay = $baseDelay * pow(2, $attempt) + rand(0, 1000) / 1000;
                        sleep($delay);
                        continue;
                    }
                    // If we've exhausted retries, return a user-friendly message
                    abort(429, 'OpenAI rate limit exceeded. Please wait a moment and try again. To increase your rate limit, add a payment method at https://platform.openai.com/settings/organization/billing');
                }
                
                // Handle other client errors
                if ($e->getCode() >= 400 && $e->getCode() < 500) {
                    $errorMessage = $responseData['error']['message'] ?? 'API request failed';
                    abort(400, "OpenAI API Error: {$errorMessage}");
                }
                
                throw $e;
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                // Handle server errors (5xx)
                if ($attempt < $maxRetries) {
                    $delay = $baseDelay * pow(2, $attempt) + rand(0, 1000) / 1000;
                    sleep($delay);
                    continue;
                }
                abort(502, 'OpenAI service is temporarily unavailable. Please try again later.');
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                // Handle network/connection errors
                if ($attempt < $maxRetries) {
                    $delay = $baseDelay * pow(2, $attempt) + rand(0, 1000) / 1000;
                    sleep($delay);
                    continue;
                }
                abort(503, 'Unable to connect to OpenAI service. Please check your internet connection.');
            } catch (\Exception $e) {
                // Handle any other errors
                \Log::error('OpenAI Service Error: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                abort(500, 'An unexpected error occurred while processing your request: ' . $e->getMessage());
            }
        }
    }

    private function systemPrompt(): string {
        return <<<TXT
You are an image recognition assistant for an asset management system.
You MUST respond with ONLY a valid JSON object. Do not include any text before or after the JSON.

Return ONLY a single JSON object with EXACT keys:
{
  "assetType": string,
  "confidence": number,
  "manufacturer": string | null,
  "model": string | null,
  "serialNumber": string | null,
  "assetTag": string | null,
  "condition": "Excellent" | "Good" | "Fair" | "Poor",
  "recommendations": string[],
  "evidence": {
    "fieldsFound": string[],
    "imagesUsed": number,
    "notes": string | null
  }
}

Use all images jointly; prefer nameplates/labels. If unsure, put null and lower confidence. Provide 3–6 practical recommendations. 
CRITICAL: Return ONLY the JSON object, no markdown formatting, no code blocks, no additional text.
TXT;
    }

    private function validateAndParseJson(string $content): ?array {
        try {
            // Clean up content - remove markdown code blocks if present
            $content = trim($content);
            if (str_starts_with($content, '```json')) {
                $content = substr($content, 7);
            }
            if (str_starts_with($content, '```')) {
                $content = substr($content, 3);
            }
            if (str_ends_with($content, '```')) {
                $content = substr($content, 0, -3);
            }
            $content = trim($content);
            
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            // Validate required fields and structure
            $requiredFields = ['assetType', 'confidence', 'condition', 'recommendations', 'evidence'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return null;
                }
            }
            
            // Validate and sanitize data
            $data['assetType'] = trim($data['assetType'] ?? 'Unknown');
            $data['confidence'] = max(0, min(100, (int) ($data['confidence'] ?? 0)));
            $data['manufacturer'] = $data['manufacturer'] ? trim($data['manufacturer']) : null;
            $data['model'] = $data['model'] ? trim($data['model']) : null;
            $data['serialNumber'] = $data['serialNumber'] ? trim($data['serialNumber']) : null;
            $data['assetTag'] = $data['assetTag'] ? trim($data['assetTag']) : null;
            
            // Validate condition enum
            $validConditions = ['Excellent', 'Good', 'Fair', 'Poor'];
            $data['condition'] = in_array($data['condition'], $validConditions) ? $data['condition'] : 'Good';
            
            // Validate recommendations array
            $data['recommendations'] = is_array($data['recommendations']) ? array_slice($data['recommendations'], 0, 6) : [];
            
            // Validate evidence structure
            $data['evidence'] = [
                'fieldsFound' => is_array($data['evidence']['fieldsFound'] ?? []) ? $data['evidence']['fieldsFound'] : [],
                'imagesUsed' => max(0, (int) ($data['evidence']['imagesUsed'] ?? 0)),
                'notes' => $data['evidence']['notes'] ? trim($data['evidence']['notes']) : null,
            ];
            
            // Truncate long strings
            $data['manufacturer'] = $data['manufacturer'] ? substr($data['manufacturer'], 0, 128) : null;
            $data['model'] = $data['model'] ? substr($data['model'], 0, 128) : null;
            $data['serialNumber'] = $data['serialNumber'] ? substr($data['serialNumber'], 0, 128) : null;
            $data['assetTag'] = $data['assetTag'] ? substr($data['assetTag'], 0, 128) : null;
            
            return $data;
            
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Temporary mock response for development when quota is exceeded
     */
    private function getMockResponse(array $dataUrls, string $prompt): array {
        // Generate different responses based on prompt content
        $isAnalytics = str_contains($prompt, 'Asset Analytics') || str_contains($prompt, 'portfolio');
        
        if ($isAnalytics) {
            return [
                'healthScore' => rand(70, 95),
                'riskAssets' => [
                    [
                        'name' => 'HVAC Unit A-001',
                        'riskLevel' => 'high',
                        'reason' => 'Visible corrosion and wear detected',
                        'confidence' => 92
                    ],
                    [
                        'name' => 'Generator B-002',
                        'riskLevel' => 'medium',
                        'reason' => 'Scheduled maintenance overdue',
                        'confidence' => 78
                    ]
                ],
                'insights' => [
                    [
                        'title' => 'Preventive Maintenance Optimization',
                        'description' => 'Implement regular maintenance schedule to reduce downtime',
                        'impact' => 'High',
                        'action' => 'Schedule monthly inspections'
                    ],
                    [
                        'title' => 'Energy Efficiency Improvement',
                        'description' => 'Upgrade older equipment to improve energy efficiency',
                        'impact' => 'Medium',
                        'action' => 'Plan equipment replacement strategy'
                    ]
                ],
                'optimizations' => [
                    [
                        'title' => 'Equipment Replacement Program',
                        'description' => 'Replace aging equipment with energy-efficient models',
                        'estimatedSavings' => 45000,
                        'paybackPeriod' => '18 months',
                        'confidence' => 85
                    ],
                    [
                        'title' => 'Maintenance Contract Optimization',
                        'description' => 'Negotiate better maintenance contracts with suppliers',
                        'estimatedSavings' => 12000,
                        'paybackPeriod' => '6 months',
                        'confidence' => 90
                    ]
                ]
            ];
        } else {
            // Image recognition response
            return [
                'assetType' => 'Industrial Equipment',
                'confidence' => rand(75, 95),
                'manufacturer' => 'Sample Manufacturer',
                'model' => 'Model ' . rand(100, 999),
                'serialNumber' => 'SN' . rand(100000, 999999),
                'assetTag' => 'TAG-' . rand(1000, 9999),
                'condition' => ['Excellent', 'Good', 'Fair', 'Poor'][rand(0, 3)],
                'recommendations' => [
                    'Schedule regular maintenance',
                    'Monitor performance metrics',
                    'Check for wear and tear',
                    'Update maintenance records'
                ],
                'evidence' => [
                    'fieldsFound' => ['manufacturer', 'model', 'serialNumber'],
                    'imagesUsed' => count($dataUrls),
                    'notes' => 'Based on visual inspection, the asset appears to be in good working condition with no visible signs of damage or excessive wear.'
                ]
            ];
        }
    }
}
