<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepLService
{
    protected $apiKey;
    protected $endpoint;

    public function __construct(?string $apiKey = null)
    {
        if (empty(trim((string) $apiKey))) {
            throw new \InvalidArgumentException('DeepL API key is required. Please add your DeepL API key in Profile → DeepL API Key Section.');
        }
        $this->apiKey = $apiKey;
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
     * Optional DeepL body fields — same for single-text (asForm) and multi-text (raw body) so
     * Action Center, Angle templates, and Sales page (DOM batch) behave identically.
     *
     * @return array<string, mixed>
     */
    private function optionalDeepLFormFields($sourceLang, $splitSentences, $preserveFormatting): array
    {
        $fields = [];
        if ($sourceLang !== null && trim((string) $sourceLang) !== '') {
            // Pass through as callers send it (preview modal, sales pages) — same as historical asForm behaviour
            $fields['source_lang'] = (string) $sourceLang;
        }
        if ($splitSentences !== null) {
            $fields['split_sentences'] = $splitSentences;
        }
        if ($preserveFormatting !== null) {
            $fields['preserve_formatting'] = (int) $preserveFormatting;
        }

        return $fields;
    }

    /**
     * Translate multiple texts in ONE API request (FAST).
     *
     * Used by: DeepL modal (translate → batch of 1), Sales pages / templates translateHtmlUsingDOM (chunks of up to 20).
     * DeepL requires repeated "text=" keys for multiple segments; PHP/Laravel nested arrays send "text[0]=" which DeepL rejects.
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

        $texts = array_values($texts);

        $targetLangUpper = strtoupper((string) $targetLang);
        $optional = $this->optionalDeepLFormFields($sourceLang, $splitSentences, $preserveFormatting);

        // Configure HTTP client (Authorization + optional SSL relax on localhost)
        $httpClient = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'DeepL-Auth-Key ' . $this->apiKey,
            ]);

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

        if (count($texts) === 1) {
            $data = array_merge(
                [
                    'text'        => (string) $texts[0],
                    'target_lang' => $targetLangUpper,
                ],
                $optional
            );
            $response = $httpClient->asForm()->post($this->endpoint, $data);
        } else {
            $pairs = [];
            foreach ($texts as $t) {
                $pairs[] = 'text=' . rawurlencode((string) $t);
            }
            $pairs[] = 'target_lang=' . rawurlencode($targetLangUpper);
            foreach ($optional as $key => $value) {
                $pairs[] = $key . '=' . rawurlencode((string) $value);
            }
            $body = implode('&', $pairs);
            $response = $httpClient
                ->withBody($body, 'application/x-www-form-urlencoded')
                ->post($this->endpoint);
        }

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
                if (strtoupper($detected) === $targetLangUpper) {
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
