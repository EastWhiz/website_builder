<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DeepLService;

class DeepLControlller extends Controller
{
    /**
     * Translate text via DeepL using the authenticated user's DeepL API key from the database (Profile only).
     */
    public function deepL(Request $request)
    {
        $text = $request->text;
        $language = $request->language;
        $sourceLanguage = $request->source_language;
        $splitSentences = $request->split_sentences;
        $preserveFormatting = $request->preserve_formatting;

        $apiKey = $request->user()->getDeeplApiKey();
        if ($apiKey === '') {
            return sendResponse(false, 'DeepL API key is required. Please add your DeepL API key in Profile → DeepL API Key Section.', null);
        }
        $deepL = new DeepLService($apiKey);
        $translatedText = $deepL->translate($text, $language, $sourceLanguage, $splitSentences, $preserveFormatting);

        return sendResponse(true, "DeepL Translation Retreived", $translatedText);
    }
}
