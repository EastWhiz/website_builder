<?php

namespace App\Http\Controllers;

use App\Models\EditedTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use ZipArchive;
use Illuminate\Support\Facades\Http;

class EditedTemplateController extends Controller
{
    public function saveTemplate(Request $request)
    {
        $nameRequired = "required";
        if (isset($request->edit_id)) {
            $nameRequired = "";
        }

        $validator = Validator::make($request->all(), [
            'name' => $nameRequired,
            'main_html' => 'required',
        ], []);

        if ($validator->fails())
            return simpleValidate($validator);

        if ($request->edit_id != false) {
            $editedTemplate = EditedTemplate::find($request->edit_id);
        } else {
            $editedTemplate = new EditedTemplate;
        }

        if (!isset($request->edit_id)) {
            $editedTemplate->name = $request->name;
            $editedTemplate->template_id = $request->template_id;
            $editedTemplate->user_id = Auth::user()->id;
        }

        $editedTemplate->main_html = $request->main_html;
        $editedTemplate->save();

        return sendResponse(true, "Edited Template Saved Successfully!");
    }

    public function downloadTemplate(Request $request)
    {
        $decodedData = json_decode($request->data);
        $pageUrl = $decodedData->url;
        // $pageUrl = "https://www.google.com"; // Or manually set URL

        $htmlContent = Http::timeout(3600)->get($pageUrl)->body();

        // Create a temporary folder
        $folderPath = storage_path('app/temp_page');
        File::deleteDirectory($folderPath);
        File::makeDirectory($folderPath, 0777, true, true);

        // Save HTML
        File::put($folderPath . '/index.html', $htmlContent);

        // Parse and download assets
        $dom = new \DOMDocument();
        @$dom->loadHTML($htmlContent);

        $tags = [
            'img' => 'src',
            'link' => 'href',
            'script' => 'src',
        ];

        foreach ($tags as $tag => $attribute) {
            $elements = $dom->getElementsByTagName($tag);
            foreach ($elements as $element) {
                $url = $element->getAttribute($attribute);
                if (!$url || strpos($url, 'http') !== 0) continue; // Skip invalid links

                try {
                    $fileContent = Http::get($url)->body();
                    $parsedUrl = parse_url($url);
                    $filePath = ltrim($parsedUrl['path'], '/');

                    $fullPath = $folderPath . '/' . $filePath;
                    $dir = dirname($fullPath);

                    if (!File::exists($dir)) {
                        File::makeDirectory($dir, 0777, true, true);
                    }

                    File::put($fullPath, $fileContent);
                } catch (\Exception $e) {
                    // Ignore failed downloads
                }
            }
        }

        // Create Zip
        $zipFile = storage_path('app/page.zip');
        $zip = new ZipArchive;
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folderPath));

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($folderPath) + 1);

                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        }

        // Delete temp folder
        File::deleteDirectory($folderPath);

        // Download zip
        return response()->download($zipFile)->deleteFileAfterSend(true);
    }
}
