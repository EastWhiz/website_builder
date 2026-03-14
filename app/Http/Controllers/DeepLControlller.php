<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\DeepLService;

class DeepLControlller extends Controller
{
    protected $deepL;

    public function __construct(DeepLService $deepL)
    {
        $this->deepL = $deepL;
    }

    public function deepL(Request $request)
    {
        $text = $request->text;
        $language = $request->language;
        $sourceLanguage = $request->source_language;
        $splitSentences = $request->split_sentences;
        $preserveFormatting = $request->preserve_formatting;

        if (empty(trim((string) $request->user()->deepl_api_key))) {
            return sendResponse(false, 'DeepL API key is required. Add your key in Profile → DeepL API Key Section.', null);
        }
        $deepL = new DeepLService($request->user()->deepl_api_key);
        $translatedText = $deepL->translate($text, $language, $sourceLanguage, $splitSentences, $preserveFormatting);

        return sendResponse(true, "DeepL Translation Retreived", $translatedText);
    }
}
