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
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AngleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // where('user_id', Auth::user()->id)->
        $angles = Angle::with(['user', 'contents' => function ($query) {
            $query->select('type', 'angle_uuid'); // columns you want
        }])->when($request->get('q'), function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                $q->orWhere('name', 'LIKE', '%' . $request->q . '%');
            });
        })->when($request->get('sort'), function ($q) use ($request) {
            $q->orderBy(...explode(' ', $request->get('sort')));
        })->select(['id', 'name', 'uuid', 'user_id'])->cursorPaginate($request->page_count);

        $templates = Template::get()->select(['id', 'name']);

        $users = User::where('role_id', 2)->get()->select(['id', 'name']);

        return sendResponse(true, 'Angles retrieved successfully!', $angles, $templates, $users);
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
            'angle' => [
                'id' => $newAngle->id,
                'uuid' => $newUuid,
                'name' => $newAngle->name
            ]
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

    public function assignToUsers(Request $request)
    {
        $angles_ids = json_decode($request->angles_ids);
        $search_query = json_decode($request->search_query);
        $selected_user = json_decode($request->selected_user);
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

        foreach ($angles_ids as $key => $angleId) {
            Angle::where('id', $angleId)->update([
                'user_id' => $selected_user->value
            ]);
        }

        return sendResponse(true, 'Angle Assigned to Selected User Successfully.');
    }

    public function translateAngle(Request $request)
    {
        Log::info('🚀 Angle Translation started', [
            'angle_id' => $request->angle_id,
            'target_language' => $request->target_language,
            'user_id' => Auth::id()
        ]);

        try {
            $request->validate([
                'angle_id' => 'required|exists:angles,id',
                'target_language' => 'required|string|max:10',
                'split_sentences' => 'nullable|string',
                'preserve_formatting' => 'nullable|integer'
            ]);

            $angle = Angle::findOrFail($request->angle_id);
            $targetLanguage = $request->target_language;
            $splitSentences = $request->split_sentences;
            $preserveFormatting = $request->preserve_formatting;

            Log::info('✅ Angle found', [
                'angle_id' => $angle->id,
                'angle_name' => $angle->name,
                'user_id' => $angle->user_id,
                'target_language' => $targetLanguage
            ]);

            // Check if user has permission to edit this angle
            // if (Auth::user()->role->name !== 'admin' && $angle->user_id !== Auth::id()) {
            //     Log::warning('❌ Permission denied', [
            //         'user_role' => Auth::user()->role->name,
            //         'angle_owner' => $angle->user_id,
            //         'current_user' => Auth::id()
            //     ]);
            //     return sendResponse(false, "You don't have permission to translate this angle", null);
            // }

            Log::info('✅ Permission granted');

            // Initialize DeepL service
            $deepLService = new \App\Services\DeepLService();
            Log::info('✅ DeepL service initialized');

            // Get all HTML content bodies for this angle
            $htmlBodies = $angle->contents()->where('type', 'html')->get();

            if ($htmlBodies->isEmpty()) {
                Log::warning('❌ No HTML content to translate');
                return sendResponse(false, "No HTML content found to translate", null);
            }

            Log::info('📝 Starting HTML bodies translation', [
                'bodies_count' => $htmlBodies->count(),
                'target_language' => $targetLanguage
            ]);

            // Translate each body separately
            $translatedCount = 0;
            foreach ($htmlBodies as $body) {
                $originalContent = $body->content;

                if (empty($originalContent)) {
                    Log::info("⏭️ Skipping empty body {$body->id}");
                    continue;
                }

                Log::info("🔄 Translating body {$body->id}", [
                    'body_name' => $body->name,
                    'content_length' => strlen($originalContent)
                ]);

                // Extract and translate text content
                $startTime = microtime(true);
                $translatedContent = $this->translateHtmlContent($originalContent, $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
                $endTime = microtime(true);

                Log::info("✅ Body {$body->id} translated", [
                    'translation_time_seconds' => round($endTime - $startTime, 2),
                    'translated_content_length' => strlen($translatedContent)
                ]);

                // Update the content
                $body->content = $translatedContent;
                $body->save();
                $translatedCount++;
            }

            // Update the angle name to indicate it's translated
            $originalName = $angle->name;
            $angle->name = $angle->name . " ({$targetLanguage})";
            $angle->save();

            Log::info('✅ Angle updated successfully', [
                'original_name' => $originalName,
                'new_name' => $angle->name,
                'angle_id' => $angle->id,
                'translated_bodies' => $translatedCount
            ]);

            return sendResponse(true, "Angle translated successfully to {$targetLanguage}. {$translatedCount} bodies were translated.", $angle);
        } catch (\Exception $e) {
            Log::error('❌ Angle Translation failed', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            return sendResponse(false, "Translation failed: " . $e->getMessage(), null);
        }
    }

    private function translateHtmlContent($html, $targetLanguage, $deepLService, $splitSentences = null, $preserveFormatting = null)
    {
        Log::info('🔍 Starting HTML content parsing', [
            'html_length' => strlen($html),
            'target_language' => $targetLanguage
        ]);

        // Extract all text content that needs translation using simple regex
        $textToTranslate = [];
        $placeholders = [];
        $placeholderIndex = 0;

        Log::info('📝 Extracting text between HTML tags...');

        // Find text between HTML tags (excluding scripts, styles, and certain attributes)
        $html = preg_replace_callback('/>(.*?)</s', function ($matches) use (&$textToTranslate, &$placeholders, &$placeholderIndex) {
            $text = trim($matches[1]);

            // Skip empty text, single characters, numbers, URLs, emails
            if (empty($text) ||
                strlen($text) < 3 ||
                is_numeric($text) ||
                preg_match('/^https?:\/\//', $text) ||
                preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $text) ||
                preg_match('/^[^a-zA-Z]*$/', $text)) {
                return $matches[0];
            }

            $placeholder = "PLACEHOLDER_" . $placeholderIndex++;
            $textToTranslate[$placeholder] = $text;
            return '>' . $placeholder . '<';
        }, $html);

        Log::info('📊 Text extraction completed', [
            'extracted_texts' => count($textToTranslate),
            'target_language' => $targetLanguage
        ]);

        if (empty($textToTranslate)) {
            Log::info('⚠️ No translatable text found');
            return $html;
        }

        // Batch translate all text at once (much faster than individual calls)
        try {
            $allText = implode("\n---SPLIT---\n", array_values($textToTranslate));

            Log::info('📤 Sending text to DeepL API', [
                'combined_text_length' => strlen($allText),
                'separator_count' => substr_count($allText, "\n---SPLIT---\n"),
                'preview' => substr($allText, 0, 200) . (strlen($allText) > 200 ? '...' : '')
            ]);

            $apiStartTime = microtime(true);
            $translatedText = $deepLService->translate($allText, $targetLanguage, null, $splitSentences, $preserveFormatting);
            $apiEndTime = microtime(true);

            Log::info('📥 DeepL API response received', [
                'api_call_time_seconds' => round($apiEndTime - $apiStartTime, 2),
                'translated_text_length' => strlen($translatedText),
                'preview' => substr($translatedText, 0, 200) . (strlen($translatedText) > 200 ? '...' : '')
            ]);

            $translatedParts = explode("\n---SPLIT---\n", $translatedText);

            Log::info('🔄 Processing translation results', [
                'expected_parts' => count($textToTranslate),
                'received_parts' => count($translatedParts)
            ]);

            // Map translations back to placeholders
            $translations = [];
            $index = 0;
            foreach ($textToTranslate as $placeholder => $originalText) {
                $translation = isset($translatedParts[$index]) ? trim($translatedParts[$index]) : $originalText;
                $translations[$placeholder] = $translation;

                if ($index < 3) { // Log first 3 translations as samples
                    Log::info("📋 Translation sample #{$index}", [
                        'original' => $originalText,
                        'translated' => $translation
                    ]);
                }
                $index++;
            }

            // Replace placeholders with translations
            foreach ($translations as $placeholder => $translation) {
                $html = str_replace($placeholder, $translation, $html);
            }

            Log::info('✅ HTML translation completed successfully');
            return $html;

        } catch (\Exception $e) {
            Log::error('❌ Translation API error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
