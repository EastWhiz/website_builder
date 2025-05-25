<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GrokService
{
    protected $apiKey;
    protected $baseUrl;
    protected $strictInstructions;

    public function __construct()
    {
        $this->apiKey = config('services.grok.api_key');
        $this->baseUrl = config('services.grok.base_url');
        $this->strictInstructions = config('services.grok.strict_instructions');
    }

    public function askGrok(string $prompt, string $model = 'grok-3'): array
    {
        // Combine user prompt with strict instructions
        $fullPrompt = $this->strictInstructions . "\n\nUser Prompt: " . $prompt;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/chat/completions', [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->strictInstructions,
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.7, // Adjust for response creativity (0.0 to 1.0)
            'max_tokens' => 1000, // Adjust based on desired response length
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Grok API request failed: ' . $response->body(), $response->status());
    }
}
