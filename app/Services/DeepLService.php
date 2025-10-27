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

    public function translate($text, $targetLang = 'EN', $sourceLang = null)
    {
        $data = [
            'auth_key' => $this->apiKey,
            'text' => $text,
            'target_lang' => $targetLang,
        ];

        if ($sourceLang) {
            $data['source_lang'] = $sourceLang;
        }

        $response = Http::asForm()->post($this->endpoint, $data);

        if ($response->successful()) {
            return $response->json()['translations'][0]['text'];
        }

        throw new \Exception('DeepL API Error: ' . $response->body());
    }
}
