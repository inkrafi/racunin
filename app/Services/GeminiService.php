<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = Config::get('gemini.api_key');
        $this->model = Config::get('gemini.model', 'gemini-2.5-flash');
        $this->baseUrl = Config::get('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->timeout = (int) Config::get('gemini.timeout', 20);
    }

    /**
     * Check if Gemini API is configured with an API key.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate plain text content using Gemini.
     */
    public function generateText(string $prompt, ?string $systemInstruction = null): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google Gemini API Key belum dikonfigurasi di file .env (GEMINI_API_KEY).');
        }

        $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048,
            ]
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json', 'x-goog-api-key' => $this->apiKey])
                ->post($endpoint, $payload);

            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? $response->body();
                Log::error('Gemini API Error', ['status' => $response->status(), 'error' => $errorMessage]);
                throw new \RuntimeException("Gemini API Error: {$errorMessage}");
            }

            $result = $response->json();
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } catch (\Exception $e) {
            Log::error('Gemini Request Exception', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate structured JSON output from Gemini.
     */
    public function generateJson(string $prompt, ?string $systemInstruction = null): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google Gemini API Key belum dikonfigurasi di file .env (GEMINI_API_KEY).');
        }

        $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'responseMimeType' => 'application/json',
            ]
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json', 'x-goog-api-key' => $this->apiKey])
                ->post($endpoint, $payload);

            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? $response->body();
                Log::error('Gemini JSON API Error', ['status' => $response->status(), 'error' => $errorMessage]);
                throw new \RuntimeException("Gemini API Error: {$errorMessage}");
            }

            $result = $response->json();
            $rawText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

            // Clean markdown json fences if any
            $cleanJson = preg_replace('/^```json\s*|\s*```$/m', '', trim($rawText));
            $decoded = json_decode($cleanJson, true);

            if (!is_array($decoded)) {
                Log::warning('Gemini JSON decode failed, raw text:', ['raw' => $rawText]);
                return [];
            }

            return $decoded;
        } catch (\Exception $e) {
            Log::error('Gemini JSON Request Exception', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

    public const TASK_DOCUMENT = 'RETRIEVAL_DOCUMENT';
    public const TASK_QUERY = 'RETRIEVAL_QUERY';

    /**
     * Generate vector embedding for semantic search.
     *
     * Use TASK_DOCUMENT when embedding stored products and TASK_QUERY when
     * embedding a user's search query, so both land in the same retrieval space.
     */
    public function generateEmbedding(string $text, string $taskType = self::TASK_DOCUMENT): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google Gemini API Key belum dikonfigurasi di file .env (GEMINI_API_KEY).');
        }

        $model = Config::get('gemini.embedding_model', 'gemini-embedding-001');
        $endpoint = "{$this->baseUrl}/models/{$model}:embedContent";

        $payload = [
            'model' => "models/{$model}",
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ],
            'taskType' => $taskType,
            'outputDimensionality' => (int) Config::get('gemini.embedding_dimensions', 768),
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json', 'x-goog-api-key' => $this->apiKey])
                ->post($endpoint, $payload);

            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? $response->body();
                Log::error('Gemini Embedding API Error', ['status' => $response->status(), 'error' => $errorMessage]);
                throw new \RuntimeException("Gemini Embedding API Error: {$errorMessage}");
            }

            $result = $response->json();
            return $result['embedding']['values'] ?? [];
        } catch (\Exception $e) {
            Log::error('Gemini Embedding Request Exception', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }
}
