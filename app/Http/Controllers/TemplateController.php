<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $templates = Template::with('contents')->when($request->get('q'), function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                $q->orWhere('name', 'LIKE', '%' . $request->q . '%');
            });
        })->when($request->get('sort'), function ($q) use ($request) {
            $q->orderBy(...explode(' ', $request->get('sort')));
        })->cursorPaginate($request->page_count);
        return sendResponse(true, 'Templates retrieved successfully!', $templates);
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
    public function saveProcess(Request $request)
    {
        // return $request;

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'head' => 'required',
            'index' => 'required',
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
            if (!$fontFile && !$imageFile) {
                $templateId = $request->uuid; // Generate a unique ID for template storage
                $basePath = "templates/$templateId";
                Storage::disk('public')->deleteDirectory($basePath);
                TemplateContent::where('template_uuid', $request->uuid)->delete();
                return sendResponse(false, 'File not uploaded correctly!');
            }
        }

        try {

            $templateId = $request->uuid; // Generate a unique ID for template storage
            $basePath = "templates/$templateId";

            // Store fonts
            $fonts = [];
            foreach ($request->allFiles() as $key => $file) {
                if (Str::startsWith($key, 'font')) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = $originalName . '.' . $extension;
                    $path = "{$basePath}/fonts/{$fileName}";
                    Storage::disk('public')->putFileAs("{$basePath}/fonts", $file, $fileName);
                    $fonts[] = Storage::url($path);
                }
            }

            $fonts = collect($fonts)->transform(function ($item) use ($templateId) {
                return [
                    "template_uuid" => $templateId,
                    "type" => "font",
                    'name' => $item
                ];
            });

            TemplateContent::upsert($fonts->toArray(), ['id']);

            // Store images
            $images = [];
            foreach ($request->allFiles() as $key => $file) {
                if (Str::startsWith($key, 'image')) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = $originalName . '.' . $extension;
                    $path = "{$basePath}/images/{$fileName}";
                    Storage::disk('public')->putFileAs("{$basePath}/images", $file, $fileName);
                    $images[] = Storage::url($path);
                }
            }

            $images = collect($images)->transform(function ($item) use ($templateId) {
                return [
                    "template_uuid" => $templateId,
                    "type" => "image",
                    'name' => $item
                ];
            });

            TemplateContent::upsert($images->toArray(), ['id']);

            // NOW SAVING DATA TO DATABASE

            if ($request->last_iteration == "true") {

                $html = collect($html)->transform(function ($item) use ($templateId) {
                    return [
                        "template_uuid" => $templateId,
                        "type" => "html",
                        'name' => $item['name'],
                        'content' => $item['content']
                    ];
                });

                $css = collect($css)->transform(function ($item) use ($templateId) {
                    return [
                        "template_uuid" => $templateId,
                        "type" => "css",
                        'name' => $item['name'],
                        'content' => $item['content']
                    ];
                });

                $js = collect($js)->transform(function ($item) use ($templateId) {
                    return [
                        "template_uuid" => $templateId,
                        "type" => "js",
                        'name' => $item['name'],
                        'content' => $item['content']
                    ];
                });

                Template::create($request->all());
                TemplateContent::upsert($html->toArray(), ['id']);
                TemplateContent::upsert($css->toArray(), ['id']);
                TemplateContent::upsert($js->toArray(), ['id']);
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
