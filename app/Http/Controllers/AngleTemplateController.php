<?php

namespace App\Http\Controllers;

use App\Models\Angle;
use App\Models\AngleTemplate;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class AngleTemplateController extends Controller
{
    public function anglesApplying(Request $request)
    {
        $angles_ids = json_decode($request->angles_ids);
        $search_query = json_decode($request->search_query);
        $selected_templates = json_decode($request->selected_templates);
        $all_check = $request->all_check;

        if ($all_check == "true") {
            parse_str($search_query, $params);

            $angles_ids = Angle::when(isset($params['q']), function ($q) use ($params) {
                $q->where(function ($q) use ($params) {
                    $q->where('name', 'LIKE', '%' . $params['q'] . '%');
                });
            })->get()->pluck('id');
        }

        if (count($angles_ids) == 0)
            return sendResponse(false, 'At least select one angle!');

        // return $request->all();

        DB::beginTransaction();
        try {
            foreach ($angles_ids as $key => $angleId) {
                foreach ($selected_templates as $key => $template) {

                    // CREATING MAIN HTML

                    $currentAngle = Angle::with('contents')->where('id', $angleId)->first();
                    $currentTemplate = Template::where('id', $template->value)->first();
                    $allBodies = $currentAngle->contents()->where('type', 'html')->get();
                    $updatingIndex = $currentTemplate->index;

                    foreach ($allBodies as $key => $body) {
                        $bodyKey = $key + 1;
                        $updatingIndex = str_replace("<!--INTERNAL--BD$bodyKey--EXTERNAL-->", $body->content, $updatingIndex);
                    }

                    // UPDATING INDEX WITH IMAGE CHANGES - ANGLES
                    $updatingIndex = preg_replace(
                        '/src="angle_images\//',
                        'src="../../storage/angles/' . $currentAngle->uuid . '/images/' . $currentAngle->asset_unique_uuid . '-',
                        $updatingIndex
                    );

                    // UPDATING INDEX WITH IMAGE CHANGES - TEMPLATES
                    $updatingIndex = preg_replace(
                        '/src="template_images\//',
                        'src="../../storage/templates/' . $currentTemplate->uuid . '/images/' . $currentTemplate->asset_unique_uuid . '-',
                        $updatingIndex
                    );

                    AngleTemplate::create([
                        'angle_id' => $currentAngle->id,
                        'template_id' => $currentTemplate->id,
                        'user_id' => Auth::user()->id,
                        'name' => "$currentTemplate->name ($currentAngle->name)",
                        'main_html' =>  $updatingIndex,
                    ]);
                }
            }
            DB::commit();
            return sendResponse(true, 'Angle Applied to Selected Publishers.');
            //
        } catch (\Exception $e) {
            return sendResponse(false, 'Angle applying facing issues: ' . $e->getMessage());
        }
    }

    public function saveEditedAngleTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'main_html' => 'required',
        ], []);

        if ($validator->fails())
            return simpleValidate($validator);

        if ($request->edit_id != false) {
            $editedAngleTemplate = AngleTemplate::find($request->edit_id);
        }

        $editedAngleTemplate->main_html = $request->main_html;
        $editedAngleTemplate->save();

        return sendResponse(true, "Edited Sales Page Saved Successfully!");
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
        $zipFile = storage_path('app/template.zip');
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
