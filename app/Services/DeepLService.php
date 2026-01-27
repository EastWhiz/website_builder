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

    public function translate($text, $targetLang = 'EN', $sourceLang = null, $splitSentences = null, $preserveFormatting = null)
    {
        $data = [
            'auth_key' => $this->apiKey,
            'text' => $text,
            'target_lang' => $targetLang,
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

        // logger(json_encode($data, JSON_PRETTY_PRINT));

        // Configure HTTP client with SSL verification based on environment
        $httpClient = Http::asForm();
        
        // Disable SSL verification only for localhost (WAMP/Windows local development)
        // On server, SSL verification should be enabled for security
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

        if ($response->successful()) {
            $data = $response->json();
            // logger("RESPONSE: " . json_encode($data, JSON_PRETTY_PRINT));
            $translationData = $data['translations'][0];
            $translatedText = $translationData['text'];
            $detectedSourceLang = $translationData['detected_source_language'] ?? null;
            
            // Check if translation is identical to original (DeepL sometimes returns unchanged text)
            // Normalize both texts for comparison (trim whitespace, case-insensitive for short texts)
            $normalizedOriginal = trim($text);
            $normalizedTranslated = trim($translatedText);
            
            // If translation is identical and we didn't specify source language, try forcing translation
            if ($normalizedOriginal === $normalizedTranslated && !$sourceLang && $detectedSourceLang) {
                // If detected language matches target, DeepL won't translate
                // Try with explicit source language to force translation
                if (strtoupper($detectedSourceLang) === strtoupper($targetLang)) {
                    // Text is already in target language - log warning but return as-is
                    Log::warning('⚠️ DeepL detected text is already in target language', [
                        'detected_source' => $detectedSourceLang,
                        'target' => $targetLang,
                        'text_preview' => substr($text, 0, 100)
                    ]);
                } else {
                    // Retry with explicit source language to force translation
                    Log::info('🔄 Retrying translation with explicit source language', [
                        'detected_source' => $detectedSourceLang,
                        'target' => $targetLang
                    ]);
                    
                    $data['source_lang'] = $detectedSourceLang;
                    $retryResponse = $httpClient->post($this->endpoint, $data);
                    
                    if ($retryResponse->successful()) {
                        $retryData = $retryResponse->json();
                        $retryTranslated = $retryData['translations'][0]['text'];
                        
                        // If retry still returns same text, log and return
                        if (trim($retryTranslated) === $normalizedOriginal) {
                            Log::warning('⚠️ Translation retry returned unchanged text', [
                                'source' => $detectedSourceLang,
                                'target' => $targetLang
                            ]);
                        } else {
                            Log::info('✅ Retry translation succeeded');
                            return $retryTranslated;
                        }
                    }
                }
            }
            
            // logger("TRANSLATED: " . json_encode($translatedText));
            return $translatedText;
        }

        throw new \Exception('DeepL API Error: ' . $response->body());
    }
}
