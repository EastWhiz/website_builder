<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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

        $response = Http::asForm()->post($this->endpoint, $data);

        if ($response->successful()) {
            $data = $response->json();
            // logger("RESPONSE: " . json_encode($data, JSON_PRETTY_PRINT));
            $translatedText = $data['translations'][0]['text'];
            // logger("TRANSLATED: " . json_encode($translatedText));
            return $translatedText;
        }

        throw new \Exception('DeepL API Error: ' . $response->body());
    }
}
