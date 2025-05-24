<?php

namespace App\Http\Controllers;

use App\Models\Angle;
use App\Models\AngleTemplate;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

                    $updatingCss = '';
                    $currentTemplate->contents()->where('type', 'css')->get()->each(function ($item) use (&$updatingCss) {
                        $updatingCss .= $item->content;
                    });

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

                    // UPDATING CSS WITH FONT CHANGES
                    $updatingCss = preg_replace(
                        '/fonts\//',
                        '../../storage/templates/' . $currentTemplate->uuid . '/fonts/' . $currentTemplate->asset_unique_uuid . '-',
                        $updatingCss
                    );

                    AngleTemplate::create([
                        'angle_id' => $currentAngle->id,
                        'template_id' => $currentTemplate->id,
                        'user_id' => Auth::user()->id,
                        'name' => "$currentTemplate->name ($currentAngle->name)",
                        'main_html' =>  $updatingIndex,
                        'main_css' =>  $updatingCss,
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
        // return $request->all();

        $angleTemplate = AngleTemplate::where('id', $request->angle_template_id)->first();
        $template = $angleTemplate->template;
        $angle = $angleTemplate->angle;

        $templateImages = $template->contents()->where('type', 'image')->get()->pluck('name')->toArray();
        $angleImages = $angle->contents()->where('type', 'image')->get()->pluck('name')->toArray();

        $fontPaths = $template->contents()->where('type', 'font')->get()->pluck('name')->toArray();
        $imagePaths = array_merge($templateImages, $angleImages);

        $zipFileName = 'SalesPage_' . $angleTemplate->name . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            return response()->json(['error' => 'Could not create zip file.'], 500);
        }

        // Add image files under images/ folder
        foreach ($imagePaths as $path) {
            $relative = str_replace('/storage/', '', $path);
            $fullPath = storage_path('app/public/' . $relative);
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, 'images/' . basename($fullPath));
            }
        }

        // Add font files under fonts/ folder
        foreach ($fontPaths as $path) {
            $relative = str_replace('/storage/', '', $path);
            $fullPath = storage_path('app/public/' . $relative);
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, 'fonts/' . basename($fullPath));
            }
        }

        $angleImages = $angle->contents()->where('type', 'image')->get();

        $updatingIndex = $angleTemplate->main_html;
        $updatingCss = $angleTemplate->main_css;

        // UPDATING INDEX WITH IMAGE CHANGES - ANGLES
        $updatingIndex = str_replace(
            'src="../../storage/angles/' . $angle->uuid . '/images/' . $angle->asset_unique_uuid . '-',
            'src="images/' . $angle->asset_unique_uuid . '-',
            $updatingIndex
        );

        // UPDATING INDEX WITH IMAGE CHANGES - TEMPLATES
        $updatingIndex = str_replace(
            'src="../../storage/templates/' . $template->uuid . '/images/' . $template->asset_unique_uuid . '-',
            'src="images/' . $template->asset_unique_uuid . '-',
            $updatingIndex
        );

        // UPDATING CSS WITH FONT CHANGES
        $updatingCss = str_replace(
            '../../storage/templates/' . $template->uuid . '/fonts/' . $template->asset_unique_uuid . '-',
            'fonts/' . $template->asset_unique_uuid . '-',
            $updatingCss
        );

        $fullHtml = <<<HTMLDOC
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$angleTemplate->name}</title>
            {$template->head}
            <style>
                {$updatingCss}
            </style>
        </head>
        <body>
            {$updatingIndex}
        </body>
        </html>
        HTMLDOC;

        $zip->addFromString('index.php', $fullHtml);

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function deleteAngleTemplate(Request $request)
    {
        $angleTemplate = AngleTemplate::find($request->angle_template_id);

        if (!$angleTemplate) {
            return sendResponse(false, "Sales Page Not Found");
        }

        $angleTemplate->delete();

        return sendResponse(true, "Sales Page is deleted Successfully.");
    }
}
