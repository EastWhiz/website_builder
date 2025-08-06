<?php

namespace App\Http\Controllers;

use App\Models\Angle;
use App\Models\AngleContent;
use App\Models\AngleTemplate;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\ExtraContent;
use Illuminate\Support\Facades\Auth;

class AngleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $angles = Angle::where('user_id',Auth::user()->id)->with(['contents' => function ($query) {
            $query->select('type', 'angle_uuid'); // columns you want
        }])->when($request->get('q'), function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                $q->orWhere('name', 'LIKE', '%' . $request->q . '%');
            });
        })->when($request->get('sort'), function ($q) use ($request) {
            $q->orderBy(...explode(' ', $request->get('sort')));
        })->select(['id', 'name', 'uuid'])->cursorPaginate($request->page_count);

        $templates = Template::get()->select(['id', 'name']);

        return sendResponse(true, 'Angles retrieved successfully!', $angles, $templates);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function addEditProcess(Request $request)
    {
        // return $request;

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'uuid' => 'required',
            'last_iteration' => 'required'
        ], []);

        if ($validator->fails())
            return simpleValidate($validator);

        $html = json_decode($request->html, true);
        $html_validator = Validator::make($html, [
            '*.name' => 'required',
            '*.content' => 'required'
        ], []);

        if ($html_validator->fails())
            return simpleValidate($html_validator);

        $css = json_decode($request->css, true);
        $css_validator = Validator::make($css, [
            '*.name' => 'required',
            '*.content' => 'required'
        ], []);

        if ($css_validator->fails())
            return simpleValidate($css_validator);

        $js = json_decode($request->js, true);
        $js_validator = Validator::make($js, [
            '*.name' => 'required',
            '*.content' => 'required'
        ], []);

        if ($js_validator->fails())
            return simpleValidate($js_validator);

        for ($i = 0; $i < $request->chunk_count; $i++) {
            $fontFile = $request->file('font' . $i);
            $imageFile = $request->file('image' . $i);
            $fontFileDone = $request->input('font' . $i . "Done");
            $imageFileDone = $request->input('image' . $i . "Done");
            if (!$fontFile && !$imageFile && !$fontFileDone && !$imageFileDone) {
                $existing_angles = AngleContent::where('name', 'like', "%" . $request->asset_unique_uuid . "%")->where('angle_uuid', $request->uuid)->where('can_be_deleted', true)->get();
                foreach ($existing_angles as $key => $exContent) {
                    if ($exContent->type == "image") {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $exContent->name));
                    } else  if ($exContent->type == "font") {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $exContent->name));
                    }
                }
                AngleContent::where('name', 'like', "%" . $request->asset_unique_uuid . "%")->where('angle_uuid', $request->uuid)->where('can_be_deleted', true)->delete();
                return sendResponse(false, 'File not uploaded correctly!');
            }
        }

        try {


            $angleId = $request->uuid; // Generate a unique ID for angle storage
            $assetUUID = $request->asset_unique_uuid; // Generate a unique ID for angle storage
            $basePath = "angles/$angleId";

            // Store fonts
            $fonts = [];
            foreach ($request->allFiles() as $key => $file) {
                if (Str::startsWith($key, 'font')) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = $assetUUID . '-' . $originalName . '.' . $extension;
                    $path = "{$basePath}/fonts/{$fileName}";
                    Storage::disk('public')->putFileAs("{$basePath}/fonts", $file, $fileName);
                    $fonts[] = Storage::url($path);
                }
            }

            $fonts = collect($fonts)->transform(function ($item) use ($angleId) {
                return [
                    "uuid" => Str::uuid(),
                    "angle_uuid" => $angleId,
                    "type" => "font",
                    'name' => $item
                ];
            });

            $doneFonts = [];
            foreach ($request->all() as $key => $value) {
                if (Str::startsWith($key, 'font') && str_contains($key, 'Done')) {
                    $doneFonts[] = [
                        "uuid" => Str::uuid(),
                        "angle_uuid" => $angleId,
                        "type" => "font",
                        'name' => $value,
                    ];
                }
            }

            AngleContent::upsert(array_merge($fonts->toArray(), $doneFonts), ['id']);

            // Store images
            $images = [];
            foreach ($request->allFiles() as $key => $file) {
                if (Str::startsWith($key, 'image')) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = $assetUUID . '-' . $originalName . '.' . $extension;
                    $path = "{$basePath}/images/{$fileName}";
                    Storage::disk('public')->putFileAs("{$basePath}/images", $file, $fileName);
                    $images[] = Storage::url($path);
                }
            }

            $images = collect($images)->transform(function ($item) use ($angleId) {
                return [
                    "uuid" => Str::uuid(),
                    "angle_uuid" => $angleId,
                    "type" => "image",
                    'name' => $item
                ];
            });

            $doneImages = [];
            foreach ($request->all() as $key => $value) {
                if (Str::startsWith($key, 'image') && str_contains($key, 'Done')) {
                    $doneImages[] = [
                        "uuid" => Str::uuid(),
                        "angle_uuid" => $angleId,
                        "type" => "image",
                        'name' => $value,
                    ];
                }
            }

            AngleContent::upsert(array_merge($images->toArray(), $doneImages), ['id']);

            // NOW SAVING DATA TO DATABASE

            if ($request->last_iteration == "true") {

                $html = collect($html)->transform(function ($item) use ($angleId) {
                    return [
                        "uuid" => Str::uuid(),
                        "angle_uuid" => $angleId,
                        "type" => "html",
                        'name' => $item['name'],
                        'content' => $item['content'],
                        'can_be_deleted' => false
                        // 'old_contents' => $newHtmlExtraContents
                    ];
                });

                $css = collect($css)->transform(function ($item) use ($angleId) {
                    return [
                        "uuid" => Str::uuid(),
                        "angle_uuid" => $angleId,
                        "type" => "css",
                        'name' => $item['name'],
                        'content' => $item['content'],
                        'can_be_deleted' => false
                    ];
                });

                $js = collect($js)->transform(function ($item) use ($angleId) {
                    return [
                        "uuid" => Str::uuid(),
                        "angle_uuid" => $angleId,
                        "type" => "js",
                        'name' => $item['name'],
                        'content' => $item['content'],
                        'can_be_deleted' => false
                    ];
                });


                if ($request->edit_angle_uuid != "false") {
                    $generatedAngle = Angle::where('uuid', $request->uuid)->first();
                    $generatedAngle->angleTemplates()->get()->each(function ($item) use ($generatedAngle, $request) {
                        $item->main_html = str_replace($generatedAngle->asset_unique_uuid, $request->asset_unique_uuid, $item->main_html);
                        $item->main_css = str_replace($generatedAngle->asset_unique_uuid, $request->asset_unique_uuid, $item->main_css);
                        $item->save();
                    });
                    $generatedAngle->update([
                        "uuid" => $request->uuid,
                        "asset_unique_uuid" => $request->asset_unique_uuid,
                        "name" => $request->name,
                    ]);
                } else {
                    $generatedAngle = Angle::create($request->all());
                }

                AngleContent::where('angle_uuid', $request->uuid)->whereIn('type', ['html', 'css', 'js'])->delete();
                AngleContent::upsert($html->toArray(), ['id']);
                AngleContent::upsert($css->toArray(), ['id']);
                AngleContent::upsert($js->toArray(), ['id']);

                $new_contents = AngleContent::where('can_be_deleted', true)->where('angle_uuid', $request->uuid)->whereIn('type', ['font', 'image'])->get();
                $existingImages = $new_contents->pluck('name')->toArray();
                $old_contents = AngleContent::where('can_be_deleted', false)->where('angle_uuid', $request->uuid)->whereIn('type', ['font', 'image'])->whereNotIn('name', $existingImages)->get();
                foreach ($old_contents as $key => $exContent) {
                    if ($exContent->type == "image") {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $exContent->name));
                    } else  if ($exContent->type == "font") {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $exContent->name));
                    }
                }
                AngleContent::where('can_be_deleted', false)->where('angle_uuid', $request->uuid)->whereIn('type', ['font', 'image'])->delete();

                foreach ($new_contents as $key => $content) {

                    $currentFilePath = str_replace('/storage/', '', $content->name); // Remove the /storage/ prefix to get the relative file path
                    $fileInfo = pathinfo($currentFilePath); // Get file

                    // Generate the new file name by replacing the UUID in the file path
                    $newFileName = preg_replace('/[a-f0-9\-]{36}(?!.*[a-f0-9\-]{36})/i', $request->asset_unique_uuid, $fileInfo['basename']);

                    // Set the new file path (the file will be moved to the same folder with a new name)
                    $newFilePath = str_replace($fileInfo['basename'], $newFileName, $currentFilePath);

                    // Check if the file exists using the public disk
                    if (Storage::disk('public')->exists($currentFilePath)) {
                        Storage::disk('public')->move($currentFilePath, $newFilePath);
                    }

                    $content->update([
                        'can_be_deleted' => false,
                        'name' => str_replace($fileInfo['basename'], $newFileName, $content->name) // Update the name with the new file name
                    ]);

                    // also change name from storage of file how can i do that
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading files: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function deleteAngle(Request $request)
    {
        $angle = Angle::find($request->angle_id);

        if (!$angle) {
            return sendResponse(false, "Angle Not Found");
        }

        $angleTemplates = AngleTemplate::where('angle_id', $angle->id)->get();
        if (count($angleTemplates) > 0) {
            return sendResponse(false, "Angle is assigned to different Sales Pages. Cannot delete it.");
        }

        Storage::disk('public')->deleteDirectory("angles/$angle->uuid");

        AngleContent::where('angle_uuid', $angle->uuid)->delete();

        $extraContents = ExtraContent::where('angle_uuid', $angle->uuid)->get();
        $extraContents->each(function ($content) {
            Storage::disk('public')->deleteDirectory("angleContents/{$content->angle_content_uuid}");
            $content->delete();
        });

        $angle->delete();

        return sendResponse(true, "Angle is deleted Successfully.");
    }

    public function saveEditedAngle(Request $request)
    {
        // return $request;

        for ($i = 0; $i < $request->chunk_count; $i++) {
            $imageFile = $request->file('image' . $i);
            if (!$imageFile) {
                $existing_templates = ExtraContent::where('name', 'like', "%" . $request->asset_unique_uuid . "%")->where('angle_content_uuid', $request->angle_content_uuid)->where('can_be_deleted', true)->get();
                foreach ($existing_templates as $key => $exContent) {
                    if ($exContent->type == "image") {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $exContent->name));
                    }
                }
                ExtraContent::where('name', 'like', "%" . $request->asset_unique_uuid . "%")->where('angle_content_uuid', $request->angle_content_uuid)->where('can_be_deleted', true)->delete();
                return sendResponse(false, 'File not uploaded correctly!');
            }
        }

        try {

            $angleUUID = $request->angle_uuid;
            $angleContentUUID = $request->angle_content_uuid; // Generate a unique ID for template storage
            $assetUUID = $request->asset_unique_uuid; // Generate a unique ID for template storage
            $basePath = "angleContents/$angleContentUUID";

            // Store images
            $images = [];
            foreach ($request->allFiles() as $key => $file) {
                if (Str::startsWith($key, 'image')) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = $assetUUID . '-' . $originalName . '.' . $extension;
                    $path = "{$basePath}/images/{$fileName}";
                    Storage::disk('public')->putFileAs("{$basePath}/images", $file, $fileName);
                    $images[] = [
                        'name' => Storage::url($path),
                        'blob_url' => $request->{$key . "blob_url"}
                    ];
                }
            }

            $images = collect($images)->transform(function ($item) use ($angleContentUUID, $angleUUID, $assetUUID) {
                return [
                    "angle_uuid" => $angleUUID,
                    "angle_content_uuid" => $angleContentUUID,
                    "asset_unique_uuid" => $assetUUID,
                    "type" => "image",
                    'name' => $item['name'],
                    'blob_url' => $item['blob_url']
                ];
            });

            ExtraContent::upsert($images->toArray(), ['id']);

            // NOW SAVING DATA TO DATABASE

            if ($request->last_iteration == "true") {

                $editedAngleContent = AngleContent::where('uuid', $request->angle_content_uuid)->first();
                $editedAngleContent->content = $request->main_html;
                $editedAngleContent->save();

                $old_contents = ExtraContent::where('can_be_deleted', false)->where('angle_content_uuid', $request->angle_content_uuid)->whereIn('type', ['image'])->get();
                foreach ($old_contents as $key => $exContent) {
                    if (!Str::contains($editedAngleContent->content, $exContent->name)) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $exContent->name));
                        $exContent->delete();
                    }
                }

                $new_contents = ExtraContent::where('can_be_deleted', true)->where('angle_content_uuid', $request->angle_content_uuid)->whereIn('type', ['image'])->get();
                foreach ($new_contents as $key => $content) {

                    $currentFilePath = str_replace('/storage/', '', $content->name); // Remove the /storage/ prefix to get the relative file path
                    $fileInfo = pathinfo($currentFilePath); // Get file

                    // Generate the new file name by replacing the UUID in the file path
                    $newFileName = preg_replace('/[a-f0-9\-]{36}(?!.*[a-f0-9\-]{36})/i', $request->asset_unique_uuid, $fileInfo['basename']);

                    // Set the new file path (the file will be moved to the same folder with a new name)
                    $newFilePath = str_replace($fileInfo['basename'], $newFileName, $currentFilePath);

                    // Check if the file exists using the public disk
                    if (Storage::disk('public')->exists($currentFilePath)) {
                        Storage::disk('public')->move($currentFilePath, $newFilePath);
                    }

                    $dbName = str_replace($fileInfo['basename'], $newFileName, $content->name);
                    $finalImageName = "../.." . $dbName;

                    $editedAngleContent->content = str_replace($content->blob_url, $finalImageName, $editedAngleContent->content);
                    $editedAngleContent->save();

                    $content->update([
                        'can_be_deleted' => false,
                        'name' => $dbName,
                        'blob_url' => NULL,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading files: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function duplicateAngle(Request $request, Angle $angle)
    {
        $newUuid = (string) Str::uuid();
        $newAssetUuid = (string) Str::uuid();
        $newAngleContentUuid = (string) Str::uuid();
        $newAngle = $angle->replicate();
        $newAngle->uuid = $newUuid;
        $newAngle->asset_unique_uuid = $newAssetUuid;
        $newAngle->name = $angle->name . ' (Copy)';
        $newAngle->push();

        // Duplicate all AngleContent and keep mapping of old to new uuids
        $angleContents = AngleContent::where('angle_uuid', $angle->uuid)->get();
        foreach ($angleContents as $content) {
            $newContent = $content->replicate();
            $newContent->uuid = (string) Str::uuid();
            $newContent->angle_uuid = $newUuid;
            // Duplicate file if type is font or image
            if (in_array($content->type, ['font', 'image']) && $content->name) {
                $oldPath = str_replace('/storage/', '', $content->name);
                $fileInfo = pathinfo($oldPath);
                // Remove old asset_unique_uuid from filename
                $oldAssetUuid = $angle->asset_unique_uuid;
                $baseFilename = preg_replace('/^' . preg_quote($oldAssetUuid, '/') . '-/', '', $fileInfo['filename']);
                $newFileName = $newAssetUuid . '-' . $baseFilename . '.' . $fileInfo['extension'];
                $newFolder = "angles/$newUuid/" . ($content->type === 'font' ? 'fonts' : 'images');
                $newPath = "$newFolder/$newFileName";
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->copy($oldPath, $newPath);
                    $newContent->name = '/storage/' . $newPath;
                }
            }
            $newContent->save();
        }

        // Duplicate ExtraContent, update both angle_uuid and angle_content_uuid
        $extraContents = ExtraContent::where('angle_uuid', $angle->uuid)->get();
        foreach ($extraContents as $extra) {
            $newExtra = $extra->replicate();
            $newExtra->angle_uuid = $newUuid;
            $newExtra->asset_unique_uuid = $newAssetUuid;
            $newExtra->angle_content_uuid = $newAngleContentUuid;   // IT DOESN'T EXIST IN OLD ANGLE, BUT WE ARE CREATING A NEW ONE TO FOLLOW THE SAME STRUCTURE
            // Update angle_content_uuid if present in map

            // Duplicate file if type is image
            if ($extra->type === 'image' && $extra->name) {
                $oldPath = str_replace('/storage/', '', $extra->name);
                $fileInfo = pathinfo($oldPath);
                // Remove old asset_unique_uuid from filename
                $baseFilename = preg_replace('/^' . preg_quote($extra->asset_unique_uuid, '/') . '-/', '', $fileInfo['filename']);
                $newFileName = $newAssetUuid . '-' . $baseFilename . '.' . $fileInfo['extension'];
                $newFolder = "angleContents/{$newExtra->angle_content_uuid}/images";
                $newPath = "$newFolder/$newFileName";
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->copy($oldPath, $newPath);
                    $newExtra->name = '/storage/' . $newPath;
                }
            }
            $newExtra->save();

            $preSearch = "{$extra->angle_content_uuid}/images/{$extra->asset_unique_uuid}";
            $preReplace = "{$newExtra->angle_content_uuid}/images/{$newExtra->asset_unique_uuid}";

            $angleContents = $newAngle->contents()->where('type', 'html')->each(function ($content) use ($preSearch, $preReplace) {
                $content->content = str_replace($preSearch, $preReplace, $content->content);
                $content->save();
            });
        }

        return sendResponse(true, "Angle duplicated successfully", [
            'new_angle_id' => $newAngle->id,
            'new_angle_uuid' => $newUuid
        ]);
    }

    public function duplicateMultipleAngles(Request $request)
    {
        $angles_ids = json_decode($request->angles_ids);
        $search_query = json_decode($request->search_query);
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

        foreach ($angles_ids as $id) {
            $angle = Angle::find($id);
            if ($angle) {
                // Reuse the single duplicate logic
                $response = $this->duplicateAngle(new Request(), $angle);
                $results[] = $response->getData();
            }
        }
        return sendResponse(true, 'Angles duplicated successfully!', $results);
    }
}
