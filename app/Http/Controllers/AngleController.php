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

           

            // Check if user has permission to edit this angle
            // if (Auth::user()->role->name !== 'admin' && $angle->user_id !== Auth::id()) {
            //     Log::warning('❌ Permission denied', [
            //         'user_role' => Auth::user()->role->name,
            //         'angle_owner' => $angle->user_id,
            //         'current_user' => Auth::id()
            //     ]);
            //     return sendResponse(false, "You don't have permission to translate this angle", null);
            // }

           

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

                

                // Extract and translate text content
                $startTime = microtime(true);
                $translatedContent = $this->translateHtmlContent($originalContent, $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
                
                // Apply RTL support if target language is RTL (Arabic, Hebrew)
                if ($this->isRtlLanguage($targetLanguage)) {
                    $translatedContent = $this->applyRtlSupport($translatedContent);
                }
                
                $endTime = microtime(true);

                

                // Update the content
                $body->content = $translatedContent;
                $body->save();
                $translatedCount++;
            }

            // Update the angle name to indicate it's translated
            $originalName = $angle->name;
            $angle->name = $angle->name . " ({$targetLanguage})";
            $angle->save();

            

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

        // First, protect script, style, SVG, and other non-translatable tags
        $protectedTags = [];
        $html = preg_replace_callback('/<(script|style|noscript|pre|code|textarea|svg)[^>]*>.*?<\/\1>/is', function ($matches) use (&$protectedTags) {
            $placeholder = '##PROTECTED_TAG_' . count($protectedTags) . '##';
            $protectedTags[$placeholder] = $matches[0];
            return $placeholder;
        }, $html);
        
        // Also protect SVG elements that might be self-closing or have nested structure
        // This ensures all SVG attributes (width, height, viewBox, style) are preserved
        $html = preg_replace_callback('/<svg[^>]*>.*?<\/svg>/is', function ($matches) use (&$protectedTags) {
            // Check if not already protected
            if (strpos($matches[0], '##PROTECTED_TAG_') === false) {
                $svgContent = $matches[0];
                
                // Ensure SVG has explicit width/height if viewBox is present but width/height are missing
                // This prevents SVG from scaling to 100% width
                if (preg_match('/viewBox=["\']([^"\']+)["\']/', $svgContent, $viewBoxMatch)) {
                    $viewBox = $viewBoxMatch[1];
                    // Extract width and height from viewBox (format: "0 0 width height")
                    if (preg_match('/viewBox=["\']\s*[\d\.]+\s+[\d\.]+\s+([\d\.]+)\s+([\d\.]+)\s*["\']/', $svgContent, $dimensions)) {
                        $svgWidth = $dimensions[1];
                        $svgHeight = $dimensions[2];
                        
                        // Add width and height attributes if they don't exist
                        if (!preg_match('/\bwidth\s*=/i', $svgContent)) {
                            $svgContent = preg_replace('/(<svg[^>]*)(>)/i', '$1 width="' . $svgWidth . 'px"$2', $svgContent, 1);
                        }
                        if (!preg_match('/\bheight\s*=/i', $svgContent)) {
                            $svgContent = preg_replace('/(<svg[^>]*)(>)/i', '$1 height="' . $svgHeight . 'px"$2', $svgContent, 1);
                        }
                    }
                }
                
                $placeholder = '##PROTECTED_TAG_' . count($protectedTags) . '##';
                $protectedTags[$placeholder] = $svgContent;
                return $placeholder;
            }
            return $matches[0];
        }, $html);
        
        // Protect SVG elements wrapped in other tags (like div, span) to preserve their container
        // This helps maintain SVG sizing when wrapped in containers
        $html = preg_replace_callback('/<(div|span|p)[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/\1>/is', function ($matches) use (&$protectedTags) {
            $content = $matches[0];
            // Check if not already protected and contains SVG
            if (strpos($content, '##PROTECTED_TAG_') === false && preg_match('/<svg/i', $content)) {
                $placeholder = '##PROTECTED_TAG_' . count($protectedTags) . '##';
                $protectedTags[$placeholder] = $content;
                return $placeholder;
            }
            return $content;
        }, $html);
        
        // Protect table structures and list items that contain currency/numeric data
        // This prevents breaking structured layouts
        $html = preg_replace_callback('/<(td|th|li)[^>]*>.*?<\/\1>/is', function ($matches) use (&$protectedTags) {
            $content = $matches[0];
            // Check if this cell/item contains currency or structured numeric data
            if (preg_match('/[\$€£¥]\s*[\d,]+\.?\d*/', $content) || 
                preg_match('/\d+[\d,]*\.?\d*\s*[\$€£¥]/', $content)) {
                $placeholder = '##PROTECTED_TAG_' . count($protectedTags) . '##';
                $protectedTags[$placeholder] = $content;
                return $placeholder;
            }
            return $content;
        }, $html);

        // Find text between HTML tags (excluding scripts, styles, and certain attributes)
        // Track already processed text positions to avoid duplicate extraction
        $processedPositions = [];
        $html = preg_replace_callback('/>(.*?)</s', function ($matches) use (&$textToTranslate, &$placeholders, &$placeholderIndex, &$processedPositions) {
            $originalText = $matches[1]; // Keep original with whitespace
            $text = trim($originalText);
            
            // Skip if this is a protected tag placeholder
            if (strpos($originalText, '##PROTECTED_TAG_') !== false) {
                return $matches[0];
            }
            
            // Skip if we've already processed this exact text at this position
            // This prevents duplicate extraction of the same content
            $textHash = md5($originalText . $matches[0]);
            if (isset($processedPositions[$textHash])) {
                // This text was already processed - return original match
                return $matches[0];
            }
            $processedPositions[$textHash] = true;
            
            // Check if we're inside a list item by examining the full match
            // The pattern '>(.*?)<' matches text between tags, so we need to check
            // if the tag before '>' is an <li> tag
            $fullMatch = $matches[0];
            $isInsideListItem = preg_match('/<li[^>]*>\s*' . preg_quote($originalText, '/') . '/is', $fullMatch) ||
                                preg_match('/<li[^>]*>/i', substr($fullMatch, 0, 50));
            
            // Skip empty text
            if (empty($text)) {
                return $matches[0];
            }
            
            // For list items, be more lenient - translate even if it starts with numbers or percentages
            // Skip pure numbers only if NOT in a list item
            if (is_numeric($text) && !$isInsideListItem) {
                return $matches[0];
            }
            
            // Skip URLs and emails
            if (preg_match('/^https?:\/\//', $text) ||
                preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $text)) {
                return $matches[0];
            }
            
            // Skip proper names (e.g., "Joseph Farley", "Steve Collins", "Liam O'Brien")
            // Pattern: 2-4 words, each starting with capital letter, may contain apostrophes/hyphens
            // This prevents names from being translated
            // Multi-word names (first and last name) are most common
            if (preg_match('/^[A-ZÀ-ŸĀ-Ž][a-zà-ÿā-ž]*[\'\-]?[a-zà-ÿā-ž]*(?:\s+[A-ZÀ-ŸĀ-Ž][a-zà-ÿā-ž]*[\'\-]?[a-zà-ÿā-ž]*){1,3}$/u', $text) && 
                strlen($text) >= 3 && 
                strlen($text) <= 50 && // Names are typically not longer than 50 chars
                !preg_match('/\d/', $text) && // Names don't contain numbers
                preg_match('/\s/', $text)) { // Must contain at least one space (first and last name)
                // Multi-word name pattern - skip it (don't translate names)
                return $matches[0];
            }
            
            // Skip text with no letters (but allow in list items if it's part of structured content)
            // IMPORTANT: Don't skip text that contains accented characters (like Spanish, German, French)
            // These are valid translatable content even if they don't match standard ASCII letters
            $hasLetters = preg_match('/[a-zA-ZÀ-ÿĀ-ž]/u', $text);
            if (!$isInsideListItem && !$hasLetters && preg_match('/^[^a-zA-ZÀ-ÿĀ-ž]*$/u', $text)) {
                return $matches[0];
            }
            
            // For very short text, only translate if it's in a list item or has meaningful content
            // Allow accented characters and non-ASCII letters
            if (strlen($text) < 3 && (!$isInsideListItem || (!$hasLetters && !preg_match('/[%£$€¥]/', $text)))) {
                return $matches[0];
            }
            
            // Skip time patterns like "18 min", "5 horas", "23 hrs" - these are not translatable
            // Pattern: number followed by time unit (min, minuten, horas, hrs, hours, etc.)
            if (preg_match('/^\d+\s*(min|minuten|horas|hrs|hours|h|stunden|sekunden|seconds|sec|tage|days|d)$/i', $text)) {
                return $matches[0];
            }
            
            // For list items, allow text that starts with numbers/percentages (like "92% of users...")
            if ($isInsideListItem && preg_match('/^\d+%?\s+/', $text)) {
                // This is a list item starting with a number/percentage - translate it
                // Don't skip it
            }
            
            // Skip text that contains mostly numbers/currency/dates (preserve formatting)
            // Check if text is mostly numeric or currency symbols
            $nonNumericChars = preg_replace('/[\d\s\$€£¥,\-\.:]/', '', $text);
            $textRatio = strlen($nonNumericChars) / max(strlen($text), 1);
            
            // Allow text that starts with numbers/percentages if it has substantial text content
            // This handles list items like "92% of users started..." which should be translated
            if (preg_match('/^\d+%?\s+[a-zA-Z]/', $text) && $textRatio > 0.4) {
                // Text starts with number/percentage but has substantial text - translate it
                // Continue processing
            } elseif ($textRatio < 0.3 && strlen($text) > 5) {
                // Text is mostly numbers/currency - skip it (unless it's a list item with meaningful start)
                if (!$isInsideListItem || !preg_match('/^\d+%?\s+[a-zA-Z]/', $text)) {
                    return $matches[0];
                }
            }
            
            // Skip if text contains HTML tags (shouldn't happen but safety check)
            if (preg_match('/<[^>]+>/', $originalText)) {
                return $matches[0];
            }
            
            // Skip if text contains currency symbols (unless in list items with meaningful text)
            if (preg_match('/[\$€£¥]/', $text)) {
                // Contains currency symbol - check if it's meaningful text or just a number
                $textRatio = strlen(preg_replace('/[\d\$€£¥,\-\.:\s]/', '', $text)) / max(strlen($text), 1);
                // If less than 40% letters, skip it (it's mostly numbers/currency)
                if ($textRatio < 0.4) {
                    return $matches[0];
                }
                // If in list item and has meaningful text, allow it
                if (!$isInsideListItem && $textRatio < 0.5) {
                    return $matches[0];
                }
            }
            
            // Skip if text ends with numbers (likely part of structured layout) - but allow list items
            if (!$isInsideListItem && preg_match('/\d+\s*$/', $text) && strlen($text) > 10) {
                // Ends with numbers and is long enough - likely structured
                $textRatio = strlen(preg_replace('/[\d\$€£¥,\-\.:\s]/', '', $text)) / max(strlen($text), 1);
                if ($textRatio < 0.7) {
                    return $matches[0];
                }
            }
            
            // Skip if text contains numbers in the middle (like "Parti 49 Canada") - but allow list items
            if (!$isInsideListItem && (preg_match('/\s+\d+\s+/', $text) || preg_match('/\d+[a-zA-Z]|[a-zA-Z]\d+/', $text))) {
                // Contains numbers mixed with text - be conservative
                $textRatio = strlen(preg_replace('/[\d\$€£¥,\-\.:\s]/', '', $text)) / max(strlen($text), 1);
                if ($textRatio < 0.6) {
                    return $matches[0];
                }
            }
            
            // For list items, be more permissive - allow text starting with numbers/percentages
            // This handles cases like "92% of users started..." which should be translated
            if ($isInsideListItem && preg_match('/^\d+%?\s+[a-zA-Z]/', $text)) {
                // List item starting with number/percentage followed by text - translate it
                // Continue processing
            }

            // Store original text with whitespace for exact replacement
            $placeholder = "PLACEHOLDER_" . $placeholderIndex++;
            $textToTranslate[$placeholder] = [
                'original' => $originalText, // Keep original with whitespace
                'text' => $text // Trimmed version for translation
            ];
            return '>' . $placeholder . '<';
        }, $html);
        
        // Restore protected tags
        foreach ($protectedTags as $placeholder => $originalTag) {
            $html = str_replace($placeholder, $originalTag, $html);
        }

        Log::info('📊 Text extraction completed', [
            'extracted_texts' => count($textToTranslate),
            'target_language' => $targetLanguage
        ]);

        Log::info('📝 Extracting text from HTML attributes (placeholder, alt, title)...');

        // Find common attributes that should be translated
        // This includes placeholder attributes in form inputs, alt text in images, and title attributes
        // Pattern matches: attribute="value" or attribute='value' (handles both single and double quotes)
        $attributePattern = '/(alt|title|placeholder)\s*=\s*["\']([^"\']{2,})["\']/i';
        $html = preg_replace_callback($attributePattern, function ($matches) use (&$textToTranslate, &$placeholders, &$placeholderIndex) {
            $attribute = $matches[1];
            $text = $matches[2];
            $quoteChar = substr($matches[0], strpos($matches[0], '=') + 1);
            $quoteChar = trim($quoteChar)[0]; // Get the quote character used (single or double)

            // Skip if text contains URLs or looks like code
            if (preg_match('/^https?:\/\//', $text) ||
                preg_match('/[{}()<>\/\\\\]/', $text) ||
                strlen(trim($text)) < 2) {
                return $matches[0];
            }
            
            // Skip if text is mostly numbers or special characters (but allow some numbers for placeholders like "Enter 5 digits")
            $nonAlphaChars = preg_replace('/[a-zA-Z\s]/', '', $text);
            if (strlen($nonAlphaChars) >= strlen($text) * 0.8 && strlen($text) > 5) {
                return $matches[0];
            }
            
            // Skip if it's already a placeholder (avoid double-processing)
            if (preg_match('/^PLACEHOLDER_|^##TRANSLATE_|^##PROTECTED_/i', $text)) {
                return $matches[0];
            }

            $placeholder = "PLACEHOLDER_" . $placeholderIndex++;
            $textToTranslate[$placeholder] = [
                'original' => $text,
                'text' => trim($text),
                'attribute' => strtolower($attribute) // Store which attribute this is for
            ];
            $placeholders[] = $placeholder;

            // Preserve the original quote style (single or double)
            return $attribute . '=' . $quoteChar . $placeholder . $quoteChar;
        }, $html);

        $attributeCount = 0;
        foreach ($textToTranslate as $item) {
            if (is_array($item) && isset($item['attribute'])) {
                $attributeCount++;
            }
        }
        
        Log::info('✅ Attribute extraction completed', [
            'total_texts_to_translate' => count($textToTranslate),
            'attributes_found' => $attributeCount,
            'sample_attribute_texts' => array_slice(array_filter(array_map(function($item) {
                return (is_array($item) && isset($item['attribute'])) ? $item['text'] : null;
            }, array_values($textToTranslate))), 0, 3)
        ]);

        if (empty($textToTranslate)) {
            Log::info('⚠️ No translatable text found');
            return $html;
        }

        // Batch translate all text at once (much faster than individual calls)
        try {
            // Extract just the text values for translation (not the original with whitespace)
            $textsForTranslation = array_map(function($item) {
                return is_array($item) ? $item['text'] : $item;
            }, array_values($textToTranslate));
            
            // Deduplicate texts to avoid translating the same text multiple times
            // This prevents repetition issues
            $uniqueTexts = [];
            $textToPlaceholderMap = []; // Map: normalized text => array of placeholders
            foreach ($textToTranslate as $placeholder => $textData) {
                $text = is_array($textData) ? $textData['text'] : trim($textData);
                $normalizedText = strtolower(trim($text));
                
                if (!isset($textToPlaceholderMap[$normalizedText])) {
                    $textToPlaceholderMap[$normalizedText] = [];
                    $uniqueTexts[] = $text; // Keep original case for first occurrence
                }
                $textToPlaceholderMap[$normalizedText][] = $placeholder;
            }
            
            // Translate only unique texts
            $allText = implode("\n---SPLIT---\n", $uniqueTexts);

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
            
            // If splitting failed (DeepL translated the separator), try alternative approach
            if (count($translatedParts) !== count($uniqueTexts)) {
                Log::warning('⚠️ Separator count mismatch, attempting recovery', [
                    'expected' => count($uniqueTexts),
                    'received' => count($translatedParts)
                ]);
                
                // Try splitting by any variation of the separator (with or without newlines, with spaces)
                $translatedParts = preg_split('/\s*---SPLIT---\s*/', $translatedText, -1, PREG_SPLIT_NO_EMPTY);
                
                // If still doesn't match, try more aggressive splitting with variations
                if (count($translatedParts) !== count($uniqueTexts)) {
                    Log::warning('⚠️ Recovery attempt failed, trying alternative separator patterns', [
                        'expected' => count($uniqueTexts),
                        'received' => count($translatedParts)
                    ]);
                    
                    // Try with case-insensitive and various spacing, including variations like ---SPLIT--
                    $translatedParts = preg_split('/\s*-{1,5}\s*SPLIT\s*-{1,5}\s*/i', $translatedText, -1, PREG_SPLIT_NO_EMPTY);
                }
            }
            
            // Clean separator from each part before processing (preventive measure)
            foreach ($translatedParts as $key => $part) {
                $translatedParts[$key] = $this->cleanSeparator($part);
            }

            Log::info('🔄 Processing translation results', [
                'expected_parts' => count($uniqueTexts),
                'received_parts' => count($translatedParts),
                'total_placeholders' => count($textToTranslate)
            ]);

            // Map translations back to placeholders using deduplication map
            $translations = [];
            $uniqueIndex = 0;
            $unchangedCount = 0;
            
            // First, create translation map for unique texts
            $uniqueTranslations = [];
            foreach ($uniqueTexts as $uniqueIndex => $uniqueText) {
                $translation = isset($translatedParts[$uniqueIndex]) ? trim($translatedParts[$uniqueIndex]) : $uniqueText;
                
                // Clean up separator
                $translation = $this->cleanSeparator($translation);
                $translation = preg_replace('/\s+/', ' ', $translation);
                $translation = trim($translation);
                
                $normalizedUniqueText = strtolower(trim($uniqueText));
                $uniqueTranslations[$normalizedUniqueText] = $translation;
            }
            
            // Now map translations to all placeholders
            foreach ($textToTranslate as $placeholder => $textData) {
                // Handle both old format (string) and new format (array)
                $originalTextData = is_array($textData) ? $textData : ['original' => $textData, 'text' => trim($textData)];
                $originalText = $originalTextData['original'];
                $textForComparison = $originalTextData['text'];
                
                // Get translation from unique translations map
                $normalizedText = strtolower(trim($textForComparison));
                $translation = isset($uniqueTranslations[$normalizedText]) ? $uniqueTranslations[$normalizedText] : $textForComparison;
                
                // Aggressively clean up any separator text that might have been translated
                // Handle multiple variations and occurrences
                $translation = $this->cleanSeparator($translation);
                // Clean up any remaining whitespace issues
                $translation = preg_replace('/\s+/', ' ', $translation);
                $translation = trim($translation);
                
                // If translation is empty after cleanup, use original text
                if (empty($translation)) {
                    $translation = $textForComparison;
                }
                
                // Detect if translation is unchanged (DeepL sometimes returns original text)
                // Check BEFORE setting finalTranslation so we can retry if needed
                $normalizedOriginal = trim($textForComparison);
                $normalizedTranslated = trim($translation);
                
                // Check if translation is identical to original (case-insensitive for short texts)
                if (strtolower($normalizedOriginal) === strtolower($normalizedTranslated) && 
                    strlen($normalizedOriginal) > 3) { // Only check for meaningful text
                    $unchangedCount++;
                    
                    // Try to force translation by retrying with explicit source language detection
                    // Only retry for unique texts to avoid duplicate API calls
                    if (!isset($retriedTexts[$normalizedText])) {
                        $retriedTexts[$normalizedText] = true;
                        try {
                            Log::warning("⚠️ Translation unchanged, attempting forced retranslation", [
                                'placeholder' => $placeholder,
                                'original' => substr($normalizedOriginal, 0, 50),
                                'target_language' => $targetLanguage
                            ]);
                            
                            // Retry translation with explicit source language (let DeepL detect)
                            $retryTranslation = $deepLService->translate($normalizedOriginal, $targetLanguage, null, $splitSentences, $preserveFormatting);
                            $retryTranslation = trim($retryTranslation);
                            $retryTranslation = $this->cleanSeparator($retryTranslation);
                            $retryTranslation = preg_replace('/\s+/', ' ', $retryTranslation);
                            $retryTranslation = trim($retryTranslation);
                            
                            // If retry produced different result, update translation for all placeholders with this text
                            if (strtolower($retryTranslation) !== strtolower($normalizedOriginal) && !empty($retryTranslation)) {
                                // Update the unique translation map so all placeholders get the new translation
                                $uniqueTranslations[$normalizedText] = $retryTranslation;
                                // Update current translation
                                $translation = $retryTranslation;
                                Log::info("✅ Forced retranslation succeeded", [
                                    'placeholder' => $placeholder,
                                    'original' => substr($normalizedOriginal, 0, 50),
                                    'new_translation' => substr($translation, 0, 50)
                                ]);
                            } else {
                                // Even if retry didn't change, log it for debugging
                                Log::warning("⚠️ Retry translation still returned unchanged text", [
                                    'placeholder' => $placeholder,
                                    'original' => substr($normalizedOriginal, 0, 50),
                                    'retry_result' => substr($retryTranslation, 0, 50),
                                    'target_language' => $targetLanguage
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::warning("⚠️ Retry translation failed", [
                                'placeholder' => $placeholder,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
                
                // Re-fetch translation from map after potential retry updates (in case it was updated)
                // This ensures we get the latest translation if it was retried
                if (isset($uniqueTranslations[$normalizedText])) {
                    $latestTranslation = $this->cleanSeparator($uniqueTranslations[$normalizedText]);
                    $latestTranslation = preg_replace('/\s+/', ' ', $latestTranslation);
                    $latestTranslation = trim($latestTranslation);
                    if (!empty($latestTranslation) && strtolower($latestTranslation) !== strtolower($normalizedOriginal)) {
                        $translation = $latestTranslation;
                    }
                }
                
                // For attributes, don't preserve whitespace - just use the translation directly
                // For text content, preserve original whitespace structure
                if (isset($textData['attribute'])) {
                    // This is an attribute (placeholder, alt, title) - use translation directly
                    $finalTranslation = $translation;
                } else {
                    // This is text content - preserve original whitespace structure
                    $leadingWhitespace = '';
                    $trailingWhitespace = '';
                    if (preg_match('/^(\s*).*?(\s*)$/s', $originalText, $wsMatches)) {
                        $leadingWhitespace = $wsMatches[1] ?? '';
                        $trailingWhitespace = $wsMatches[2] ?? '';
                    }
                    // Apply whitespace preservation
                    $finalTranslation = $leadingWhitespace . $translation . $trailingWhitespace;
                }
                
                $translations[$placeholder] = $finalTranslation;
            }
            
            // Log sample translations (only unique ones)
            $loggedTranslations = [];
            foreach ($translations as $placeholder => $translation) {
                $textData = $textToTranslate[$placeholder];
                $textForLog = is_array($textData) ? $textData['text'] : trim($textData);
                $normalizedText = strtolower(trim($textForLog));
                
                if (count($loggedTranslations) < 3 && !isset($loggedTranslations[$normalizedText])) {
                    $loggedTranslations[$normalizedText] = true;
                    Log::info("📋 Translation sample #" . count($loggedTranslations), [
                        'original' => $textForLog,
                        'translated' => $translation,
                        'placeholder' => $placeholder
                    ]);
                }
            }
            
            if ($unchangedCount > 0) {
                Log::warning("⚠️ Some translations were unchanged", [
                    'unchanged_count' => $unchangedCount,
                    'total_count' => count($textToTranslate),
                    'target_language' => $targetLanguage
                ]);
            }

            // Replace placeholders with translations
            // Use a single pass with proper escaping to ensure each placeholder is replaced exactly once
            // Build a pattern that matches all placeholders at once to avoid issues with overlapping replacements
            $replacementCount = 0;
            foreach ($translations as $placeholder => $finalTranslation) {
                // Count occurrences before replacement
                $beforeCount = substr_count($html, $placeholder);
                
                // For attributes, escape HTML entities in translation to prevent breaking HTML
                $textData = $textToTranslate[$placeholder] ?? null;
                $replacementText = $finalTranslation;
                $isAttribute = false;
                if ($textData && is_array($textData) && isset($textData['attribute'])) {
                    $isAttribute = true;
                    // This is an attribute - escape HTML entities but preserve quotes
                    // The translation should already be clean, but ensure it's properly escaped
                    $replacementText = htmlspecialchars($finalTranslation, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
                }
                
                // Escape special regex characters in placeholder
                $escapedPlaceholder = preg_quote($placeholder, '/');
                // Escape special regex characters in replacement text for preg_replace
                $escapedReplacement = preg_replace('/([\\\\$])/', '\\\\$1', $replacementText);
                // Replace ONLY the first occurrence of this placeholder
                // Each placeholder should only appear once in the HTML, so we replace it once
                // This prevents duplicate replacements that could cause content duplication
                $html = preg_replace('/' . $escapedPlaceholder . '/', $escapedReplacement, $html, 1);
                
                // Count occurrences after replacement
                $afterCount = substr_count($html, $placeholder);
                $replaced = $beforeCount - $afterCount;
                
                if ($isAttribute && $beforeCount > 0) {
                    Log::info("🔄 Attribute placeholder replaced", [
                        'placeholder' => $placeholder,
                        'attribute' => $textData['attribute'],
                        'original' => $textData['text'],
                        'translated' => $finalTranslation,
                        'before_count' => $beforeCount,
                        'after_count' => $afterCount,
                        'replaced' => $replaced
                    ]);
                }
                
                if ($beforeCount > 1) {
                    Log::warning("⚠️ Placeholder appeared multiple times", [
                        'placeholder' => $placeholder,
                        'occurrences' => $beforeCount,
                        'replaced' => $replaced,
                        'is_attribute' => $isAttribute
                    ]);
                }
                
                $replacementCount += $replaced;
            }
            
            Log::info('🔄 Placeholder replacement completed', [
                'total_replacements' => $replacementCount,
                'expected_replacements' => count($translations)
            ]);
            
            // Final aggressive cleanup: Remove any remaining separator text from HTML
            // Multiple passes to catch all variations
            $html = $this->cleanSeparator($html);
            // Clean up any double spaces or whitespace issues
            // BUT preserve whitespace inside style and script tags to prevent breaking CSS/JS selectors
            // This protects ALL CSS selectors (not just SVG) - e.g., #story .hero-deposit-svg, #story .some-div, etc.
            // Temporarily replace style/script tags with placeholders
            $styleScriptPlaceholders = [];
            $html = preg_replace_callback('/(<style[^>]*>.*?<\/style>|<script[^>]*>.*?<\/script>)/is', function($matches) use (&$styleScriptPlaceholders) {
                $placeholder = '##STYLE_SCRIPT_' . count($styleScriptPlaceholders) . '##';
                $styleScriptPlaceholders[$placeholder] = $matches[0];
                return $placeholder;
            }, $html);
            
            // Also protect inline style attributes to preserve CSS property formatting
            // This ensures inline styles like style="display: block; width: 100%;" maintain proper spacing
            $inlineStylePlaceholders = [];
            $html = preg_replace_callback('/style\s*=\s*["\']([^"\']*)["\']/i', function($matches) use (&$inlineStylePlaceholders) {
                $fullMatch = $matches[0];
                $styleContent = $matches[1];
                $quoteChar = substr($fullMatch, strpos($fullMatch, '=') + 1);
                $quoteChar = trim($quoteChar)[0]; // Get quote character
                
                $placeholder = '##INLINE_STYLE_' . count($inlineStylePlaceholders) . '##';
                $inlineStylePlaceholders[$placeholder] = $styleContent;
                return 'style=' . $quoteChar . $placeholder . $quoteChar;
            }, $html);
            
            // Clean up whitespace (this won't affect style/script content or inline styles now)
            $html = preg_replace('/\s+/', ' ', $html);
            // Clean up spaces before/after punctuation that might have been created
            $html = preg_replace('/\s+([.,!?;:])/', '$1', $html);
            
            // Restore inline style attributes with their original formatting preserved
            foreach ($inlineStylePlaceholders as $placeholder => $originalContent) {
                $html = str_replace($placeholder, $originalContent, $html);
            }
            
            // Restore style/script tags with their original whitespace preserved
            foreach ($styleScriptPlaceholders as $placeholder => $originalContent) {
                $html = str_replace($placeholder, $originalContent, $html);
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

    /**
     * Check if a language code represents an RTL (Right-to-Left) language
     */
    private function isRtlLanguage($languageCode)
    {
        $rtlLanguages = ['AR', 'HE']; // Arabic, Hebrew
        return in_array(strtoupper($languageCode), $rtlLanguages);
    }

    /**
     * Apply RTL (Right-to-Left) support to HTML content
     * Adds dir="rtl" attribute and injects RTL-specific CSS
     */
    private function applyRtlSupport($html)
    {
        Log::info('🔄 Applying RTL support to HTML');
        
        // RTL CSS to handle layout properly
        $rtlCss = '
        <style>
            /* RTL Support Styles */
            [dir="rtl"] {
                direction: rtl;
                text-align: right;
            }
            
            [dir="rtl"] body,
            [dir="rtl"] html {
                direction: rtl;
            }
            
            /* Flip text alignment for RTL */
            [dir="rtl"] .text-left {
                text-align: right !important;
            }
            
            [dir="rtl"] .text-right {
                text-align: left !important;
            }
            
            /* Flip float directions */
            [dir="rtl"] .float-left {
                float: right !important;
            }
            
            [dir="rtl"] .float-right {
                float: left !important;
            }
            
            /* Adjust margins and padding for RTL */
            [dir="rtl"] .ml-auto {
                margin-left: 0 !important;
                margin-right: auto !important;
            }
            
            [dir="rtl"] .mr-auto {
                margin-right: 0 !important;
                margin-left: auto !important;
            }
            
            /* Form elements RTL support */
            [dir="rtl"] input,
            [dir="rtl"] textarea,
            [dir="rtl"] select {
                direction: rtl;
                text-align: right;
            }
            
            /* Lists RTL support */
            [dir="rtl"] ul,
            [dir="rtl"] ol {
                padding-right: 0;
                padding-left: 1.5em;
            }
            
            /* Tables RTL support */
            [dir="rtl"] table {
                direction: rtl;
            }
            
            [dir="rtl"] th,
            [dir="rtl"] td {
                text-align: right;
            }
        </style>
        ';
        
        // Check if HTML already has a <html> tag
        if (preg_match('/<html[^>]*>/i', $html)) {
            // Add dir="rtl" to existing html tag
            $html = preg_replace('/(<html[^>]*)(>)/i', '$1 dir="rtl"$2', $html, 1);
            
            // Inject RTL CSS before closing </head> tag, or before </html> if no head tag
            if (preg_match('/<\/head>/i', $html)) {
                $html = preg_replace('/<\/head>/i', $rtlCss . '</head>', $html, 1);
            } else {
                // If no head tag, add it before html content or at the beginning
                if (preg_match('/<html[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
                    $htmlTagPos = $matches[0][1] + strlen($matches[0][0]);
                    $html = substr_replace($html, '<head>' . $rtlCss . '</head>', $htmlTagPos, 0);
                }
            }
        } else {
            // If no html tag, wrap content and add RTL support
            // Check if there's a <body> tag
            if (preg_match('/<body[^>]*>/i', $html)) {
                // Add dir="rtl" to body tag
                $html = preg_replace('/(<body[^>]*)(>)/i', '$1 dir="rtl"$2', $html, 1);
                
                // Inject RTL CSS before body tag
                $html = preg_replace('/(<body[^>]*>)/i', $rtlCss . '$1', $html, 1);
            } else {
                // Wrap in html structure with RTL support
                $html = '<html dir="rtl" lang="ar"><head>' . $rtlCss . '</head><body>' . $html . '</body></html>';
            }
        }
        
        Log::info('✅ RTL support applied successfully');
        return $html;
    }

    /**
     * Clean all variations of the separator from text
     * Handles variations like ---SPLIT---, ---SPLIT--, --SPLIT---, etc.
     */
    private function cleanSeparator($text)
    {
        if (empty($text)) {
            return $text;
        }
        
        // Remove all variations of the separator with different numbers of dashes
        // Pattern: -{1,5}SPLIT-{1,5} (1 to 5 dashes before and after SPLIT)
        $patterns = [
            // Exact matches first
            '/---SPLIT---/i',
            '/---SPLIT--/i',   // Three before, two after
            '/--SPLIT---/i',   // Two before, three after
            '/--SPLIT--/i',    // Two before, two after
            '/-SPLIT-/i',      // One before, one after
            
            // With whitespace variations
            '/\s*---\s*SPLIT\s*---\s*/i',
            '/\s*---\s*SPLIT\s*--\s*/i',
            '/\s*--\s*SPLIT\s*---\s*/i',
            '/\s*--\s*SPLIT\s*--\s*/i',
            '/\s*-\s*SPLIT\s*-\s*/i',
            
            // Flexible pattern: any number of dashes (1-5) before and after SPLIT
            '/-{1,5}\s*SPLIT\s*-{1,5}/i',
            
            // Unicode mode variations
            '/\s*---SPLIT---\s*/u',
            '/\s*---SPLIT--\s*/u',
            '/\s*--SPLIT---\s*/u',
            '/\s*--SPLIT--\s*/u',
        ];
        
        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        
        // Also use str_replace for common variations (faster)
        $replacements = [
            '---SPLIT---',
            '---SPLIT--',
            '--SPLIT---',
            '--SPLIT--',
            '-SPLIT-',
            '--- SPLIT ---',
            '--- SPLIT --',
            '-- SPLIT ---',
            '-- SPLIT --',
            '- SPLIT -',
        ];
        
        foreach ($replacements as $replacement) {
            $text = str_ireplace($replacement, '', $text);
        }
        
        // Loop to catch any remaining variations
        $maxIterations = 10;
        $iteration = 0;
        while ($iteration < $maxIterations && preg_match('/-{1,5}\s*SPLIT\s*-{1,5}/i', $text)) {
            $text = preg_replace('/-{1,5}\s*SPLIT\s*-{1,5}/i', '', $text);
            $iteration++;
        }
        
        return $text;
    }
}
