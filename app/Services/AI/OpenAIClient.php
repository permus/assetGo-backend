<?php

namespace App\Services\AI;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Centralized OpenAI HTTP client wrapper.
 * Handles retries, timeouts, rate limits, and logging.
 */
class OpenAIClient
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private float $temperature;
    private int $maxTokens;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key', '');
        $this->baseUrl = config('openai.base_url', 'https://api.openai.com/v1/chat/completions');
        $this->model = config('openai.model', 'gpt-4');
        $this->temperature = (float) config('openai.temperature', 0.7);
        $this->maxTokens = (int) config('openai.max_tokens', 2000);
        $this->timeout = (int) config('openai.timeout', 60);

        $this->client = new Client(['timeout' => $this->timeout]);
    }

    /**
     * Send chat completion request with function calling support.
     *
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $tools Optional array of tool definitions for function calling
     * @param array $options Optional configuration (temperature, max_tokens, etc.)
     * @return array Array with 'content', 'tool_calls', and 'usage' keys
     */
    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY missing. Please configure your OpenAI API key.');
        }

        $maxRetries = 2;
        $baseDelay = 1;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $requestBody = [
                    'model' => $options['model'] ?? $this->model,
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? $this->temperature,
                    'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
                ];

                // Add tools if provided (function calling)
                if (!empty($tools)) {
                    $requestBody['tools'] = $tools;
                    $requestBody['tool_choice'] = $options['tool_choice'] ?? 'auto';
                }

                // Add response_format if provided
                if (isset($options['response_format'])) {
                    $requestBody['response_format'] = $options['response_format'];
                }

                // Log request metadata (without sensitive data)
                $requestId = uniqid('openai_', true);
                Log::info('OpenAI API Request', [
                    'request_id' => $requestId,
                    'model' => $requestBody['model'],
                    'messages_count' => count($messages),
                    'has_tools' => !empty($tools),
                    'tools_count' => count($tools),
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                ]);

                $resp = $this->client->post($this->baseUrl, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $requestBody,
                ]);

                $json = json_decode((string) $resp->getBody(), true);
                $message = $json['choices'][0]['message'] ?? [];
                $content = $message['content'] ?? '';
                $toolCalls = $message['tool_calls'] ?? [];
                $usage = $json['usage'] ?? [];

                // Log response metadata
                Log::info('OpenAI API Response', [
                    'request_id' => $requestId ?? null,
                    'has_content' => !empty($content),
                    'tool_calls_count' => count($toolCalls),
                    'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                ]);

                return [
                    'content' => $content,
                    'tool_calls' => $toolCalls,
                    'usage' => [
                        'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
                        'completion_tokens' => $usage['completion_tokens'] ?? 0,
                        'total_tokens' => $usage['total_tokens'] ?? 0,
                    ],
                ];

            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = $e->getResponse();
                $responseBody = $response ? $response->getBody()->getContents() : '';
                $responseData = json_decode($responseBody, true);
                $errorMessage = $responseData['error']['message'] ?? 'API request failed';

                if ($e->getCode() === 401) {
                    throw new \RuntimeException("OpenAI API Error: {$errorMessage}");
                }

                if ($e->getCode() === 429) {
                    if ($attempt < $maxRetries) {
                        $delay = $baseDelay * pow(2, $attempt) + rand(0, 1000) / 1000;
                        sleep($delay);
                        continue;
                    }
                    throw new \RuntimeException('OpenAI rate limit exceeded. Please wait a moment and try again.');
                }

                if ($e->getCode() >= 400 && $e->getCode() < 500) {
                    throw new \RuntimeException("OpenAI API Error: {$errorMessage}");
                }

                throw $e;
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                if ($attempt < $maxRetries) {
                    $delay = $baseDelay * pow(2, $attempt) + rand(0, 1000) / 1000;
                    sleep($delay);
                    continue;
                }
                throw new \RuntimeException('OpenAI service is temporarily unavailable. Please try again later.');
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                if ($attempt < $maxRetries) {
                    $delay = $baseDelay * pow(2, $attempt) + rand(0, 1000) / 1000;
                    sleep($delay);
                    continue;
                }
                throw new \RuntimeException('Unable to connect to OpenAI service. Please check your internet connection.');
            } catch (\Exception $e) {
                Log::error('OpenAI Service Error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                throw new \RuntimeException('An unexpected error occurred: ' . $e->getMessage());
            }
        }

        throw new \RuntimeException('Failed to generate response after retries');
    }

    /**
     * Check if API key is configured.
     */
    public function hasApiKey(): bool
    {
        return !empty($this->apiKey);
    }
}

