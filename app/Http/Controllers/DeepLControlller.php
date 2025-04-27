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
        // $languages = [
        //     'BG' => 'Bulgarian',
        //     'ZH' => 'Chinese (Simplified)',
        //     'CS' => 'Czech',
        //     'DA' => 'Danish',
        //     'NL' => 'Dutch',
        //     'EN-GB' => 'English (British)',
        //     'EN-US' => 'English (American)',
        //     'ET' => 'Estonian',
        //     'FI' => 'Finnish',
        //     'FR' => 'French',
        //     'DE' => 'German',
        //     'EL' => 'Greek',
        //     'HU' => 'Hungarian',
        //     'ID' => 'Indonesian',
        //     'IT' => 'Italian',
        //     'JA' => 'Japanese',
        //     'KO' => 'Korean',
        //     'LV' => 'Latvian',
        //     'LT' => 'Lithuanian',
        //     'NB' => 'Norwegian (Bokmål)',
        //     'PL' => 'Polish',
        //     'PT' => 'Portuguese (all Portuguese varieties)',
        //     'PT-BR' => 'Portuguese (Brazilian)',
        //     'PT-PT' => 'Portuguese (European)',
        //     'RO' => 'Romanian',
        //     'RU' => 'Russian',
        //     'SK' => 'Slovak',
        //     'SL' => 'Slovenian',
        //     'ES' => 'Spanish',
        //     'SV' => 'Swedish',
        //     'TR' => 'Turkish',
        //     'UK' => 'Ukrainian',
        // ];

        $text = $request->text;
        $language = $request->language;
        $sourceLanguage = $request->source_language;

        $translatedText = $this->deepL->translate($text, $language, $sourceLanguage);

        return response()->json([
            'translated' => $translatedText
        ]);
    }
}
