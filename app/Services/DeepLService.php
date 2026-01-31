<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepLService
{
    protected $apiKey;
    protected $endpoint;

    public function __construct()
    {
        $this->apiKey = env('DEEPL_API_KEY');
        $this->endpoint = 'https://api.deepl.com/v2/translate';
    }

    /**
     * Translate a single text (backward compatible)
     */
    public function translate(
        $text,
        $targetLang = 'EN',
        $sourceLang = null,
        $splitSentences = null,
        $preserveFormatting = null
    ) {
        $result = $this->translateBatch(
            [$text],
            $targetLang,
            $sourceLang,
            $splitSentences,
            $preserveFormatting
        );

        return $result[0] ?? $text;
    }

    /**
     * Translate multiple texts in ONE API request (FAST)
     */
    public function translateBatch(
        array $texts,
        $targetLang = 'EN',
        $sourceLang = null,
        $splitSentences = null,
        $preserveFormatting = null
    ): array {
        if (empty($texts)) {
            return [];
        }

        $data = [
            'auth_key'    => $this->apiKey,
            'text'        => array_values($texts),
            'target_lang' => strtoupper($targetLang),
        ];

        if ($sourceLang) {
            $data['source_lang'] = $sourceLang;
        }

        if ($splitSentences !== null) {
            $data['split_sentences'] = $splitSentences;
        }

        if ($preserveFormatting !== null) {
            $data['preserve_formatting'] = (int) $preserveFormatting;
        }

        // Configure HTTP client (same logic as your original)
        $httpClient = Http::asForm()->timeout(30);

        $appUrl = env('APP_URL', 'http://localhost');
        $isLocalhost = (
            strpos($appUrl, 'localhost') !== false ||
            strpos($appUrl, '127.0.0.1') !== false ||
            strpos($appUrl, '::1') !== false ||
            (isset($_SERVER['HTTP_HOST']) && (
                strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
                $_SERVER['HTTP_HOST'] === '::1'
            ))
        );

        if ($isLocalhost) {
            $httpClient = $httpClient->withoutVerifying();
        }

        $response = $httpClient->post($this->endpoint, $data);

        if (!$response->successful()) {
            throw new \Exception('DeepL API Error: ' . $response->body());
        }

        $responseData = $response->json();
        $translations = $responseData['translations'] ?? [];

        $results = [];

        foreach ($translations as $index => $translationData) {
            $original   = $texts[$index];
            $translated = $translationData['text'] ?? $original;
            $detected   = $translationData['detected_source_language'] ?? null;

            // Normalize for comparison
            if (trim($original) === trim($translated) && !$sourceLang && $detected) {
                if (strtoupper($detected) === strtoupper($targetLang)) {
                    Log::warning('⚠️ Text already in target language', [
                        'lang' => $detected,
                        'preview' => substr($original, 0, 80),
                    ]);
                }
            }

            $results[] = $translated;
        }

        return $results;
    }
}
