<?php

namespace App\Http\Controllers;

use App\Services\GrokService;
use Illuminate\Http\Request;

class GrokController extends Controller
{
    protected $grok;

    public function __construct(GrokService $grok)
    {
        $this->grok = $grok;
    }

    public function grok(Request $request)
    {
        // return $request->input('prompt');

        try {
            $response = $this->grok->askGrok($request->input('prompt'));
            return sendResponse(true, "Grok AI Answer Retreived", $response['choices'][0]['message']['content'] ?? 'No response from Grok.');
        } catch (\Exception $e) {
            return sendResponse(false, "Failed to get response from Grok AI: ",  $e->getMessage());
        }
    }
}
