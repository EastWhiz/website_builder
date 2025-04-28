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
        $translatedText = $this->deepL->translate($text, $language, $sourceLanguage);
        return sendResponse(true, "DeepL Translation Retreived", $translatedText);
    }
}
