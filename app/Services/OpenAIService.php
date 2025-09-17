<?php

namespace App\Services;

use GuzzleHttp\Client;

class OpenAIService {
    public function analyzeImages(array $dataUrls, string $prompt): array {
        $apiKey = config('openai.api_key');
        abort_if(empty($apiKey), 500, 'OPENAI_API_KEY missing');

        // Enforce request size limits (base64 is ~33% larger)
        $totalSize = array_sum(array_map('strlen', $dataUrls));
        abort_if($totalSize > 20 * 1024 * 1024, 413, 'Request too large. Please use smaller images.');


        $messages = [[
            'role' => 'system',
            'content' => $this->systemPrompt(),
        ],[
            'role' => 'user',
            'content' => array_merge(
                [['type' => 'text', 'text' => $prompt]],
                array_map(fn($url) => [
                    'type' => 'image_url',
                    'image_url' => ['url' => $url, 'detail' => 'high']
                ], $dataUrls)
            ),
        ]];

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
                        'response_format' => ['type' => 'json_object'], // Force JSON mode
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
                if ($e->getCode() === 429 && $attempt < $maxRetries) {
                    $delay = $baseDelay * pow(2, $attempt) + rand(0, 1000) / 1000;
                    sleep($delay);
                    continue;
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
                abort(500, 'An unexpected error occurred while processing your request.');
            }
        }
    }

    private function systemPrompt(): string {
        return <<<TXT
You are an image recognition assistant for an asset management system.
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
Use all images jointly; prefer nameplates/labels. If unsure, put null and lower confidence. Provide 3–6 practical recommendations. No text outside JSON.
TXT;
    }

    private function validateAndParseJson(string $content): ?array {
        try {
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


}
