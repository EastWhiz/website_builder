<?php

namespace App\Http\Controllers;

use App\Models\Angle;
use App\Models\AngleContent;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AngleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $angles = Angle::with('contents')->when($request->get('q'), function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                $q->orWhere('name', 'LIKE', '%' . $request->q . '%');
            });
        })->when($request->get('sort'), function ($q) use ($request) {
            $q->orderBy(...explode(' ', $request->get('sort')));
        })->cursorPaginate($request->page_count);

        $templates = Template::get();

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
                    "angle_uuid" => $angleId,
                    "type" => "font",
                    'name' => $item
                ];
            });

            $doneFonts = [];
            foreach ($request->all() as $key => $value) {
                if (Str::startsWith($key, 'font') && str_contains($key, 'Done')) {
                    $doneFonts[] = [
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
                    "angle_uuid" => $angleId,
                    "type" => "image",
                    'name' => $item
                ];
            });

            $doneImages = [];
            foreach ($request->all() as $key => $value) {
                if (Str::startsWith($key, 'image') && str_contains($key, 'Done')) {
                    $doneImages[] = [
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
                        "angle_uuid" => $angleId,
                        "type" => "html",
                        'name' => $item['name'],
                        'content' => $item['content'],
                        'can_be_deleted' => false
                    ];
                });

                $css = collect($css)->transform(function ($item) use ($angleId) {
                    return [
                        "angle_uuid" => $angleId,
                        "type" => "css",
                        'name' => $item['name'],
                        'content' => $item['content'],
                        'can_be_deleted' => false
                    ];
                });

                $js = collect($js)->transform(function ($item) use ($angleId) {
                    return [
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
    public function destroy(string $id)
    {
        //
    }
}
