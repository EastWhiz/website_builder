<?php

namespace App\Http\Controllers;

use App\Models\Angle;
use App\Models\AngleTemplate;
use App\Models\ExtraContent;
use App\Models\Template;
use App\Models\TemplateContent;
use App\Models\UserApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use Symfony\Component\DomCrawler\Crawler;

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

                    $updatingJs = '';
                    $currentTemplate->contents()->where('type', 'js')->get()->each(function ($item) use (&$updatingJs) {
                        $updatingJs .= $item->content . "\n";
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
                        'uuid' => Str::uuid(),
                        'angle_id' => $currentAngle->id,
                        'template_id' => $currentTemplate->id,
                        'user_id' => Auth::user()->id,
                        'name' => "$currentTemplate->name ($currentAngle->name)",
                        'main_html' =>  $updatingIndex,
                        'main_css' =>  $updatingCss,
                        'main_js' =>  $updatingJs,
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
        // return $request;

        for ($i = 0; $i < $request->chunk_count; $i++) {
            $imageFile = $request->file('image' . $i);
            if (!$imageFile) {
                $existing_templates = ExtraContent::where('name', 'like', "%" . $request->asset_unique_uuid . "%")->where('angle_template_uuid', $request->angle_template_uuid)->where('can_be_deleted', true)->get();
                foreach ($existing_templates as $key => $exContent) {
                    if ($exContent->type == "image") {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $exContent->name));
                    }
                }
                ExtraContent::where('name', 'like', "%" . $request->asset_unique_uuid . "%")->where('angle_template_uuid', $request->angle_template_uuid)->where('can_be_deleted', true)->delete();
                return sendResponse(false, 'File not uploaded correctly!');
            }
        }

        try {

            $angleTemplateUUID = $request->angle_template_uuid; // Generate a unique ID for template storage
            $assetUUID = $request->asset_unique_uuid; // Generate a unique ID for template storage
            $basePath = "angleTemplates/$angleTemplateUUID";

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

            $images = collect($images)->transform(function ($item) use ($angleTemplateUUID) {
                return [
                    "angle_template_uuid" => $angleTemplateUUID,
                    "type" => "image",
                    'name' => $item['name'],
                    'blob_url' => $item['blob_url']
                ];
            });

            ExtraContent::upsert($images->toArray(), ['id']);

            // NOW SAVING DATA TO DATABASE

            if ($request->last_iteration == "true") {

                $editedAngleTemplate = AngleTemplate::where('uuid', $request->angle_template_uuid)->first();
                $editedAngleTemplate->main_html = $request->main_html;
                $editedAngleTemplate->save();

                $old_contents = ExtraContent::where('can_be_deleted', false)->where('angle_template_uuid', $request->angle_template_uuid)->whereIn('type', ['image'])->get();
                foreach ($old_contents as $key => $exContent) {
                    if (!Str::contains($editedAngleTemplate->main_html, $exContent->name)) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $exContent->name));
                        $exContent->delete();
                    }
                }

                $new_contents = ExtraContent::where('can_be_deleted', true)->where('angle_template_uuid', $request->angle_template_uuid)->whereIn('type', ['image'])->get();
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

                    $editedAngleTemplate->main_html = str_replace($content->blob_url, $finalImageName, $editedAngleTemplate->main_html);
                    $editedAngleTemplate->save();

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

    public function downloadTemplate(Request $request)
    {
        // return $request->all();

        $angleTemplate = AngleTemplate::where('id', $request->angle_template_id)->first();
        $template = $angleTemplate->template;
        $angle = $angleTemplate->angle;

        $templateImages = $template->contents()->where('type', 'image')->get()->pluck('name')->toArray();
        $angleImages = $angle->contents()->where('type', 'image')->get()->pluck('name')->toArray();
        $extraImages = $angleTemplate->contents()->where('type', 'image')->get()->pluck('name')->toArray();
        $angleContentImages = $angle->extraContents()->where('type', 'image')->get()->pluck('name')->toArray();

        $fontPaths = $template->contents()->where('type', 'font')->get()->pluck('name')->toArray();
        $imagePaths = array_merge($templateImages, $angleImages, $extraImages, $angleContentImages);

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
        $updatingJs = $angleTemplate->main_js;

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


        // UPDATING INDEX WITH IMAGE CHANGES - TEMPLATES
        // (ASSET UNIQUE UUID NOT DELETED AS IT IS ALSO NOT SAVED FOR EXTRA CONTENTS AS IT IS ONLY USED FOR SAVING PURPOSE)
        $updatingIndex = str_replace(
            'src="../../storage/angleTemplates/' . $angleTemplate->uuid . '/images/',
            'src="images/',
            $updatingIndex
        );

        $angle->extraContents()->where('type', 'image')->each(function ($extraContent) use (&$updatingIndex) {
            $updatingIndex = str_replace(
                'src="../../storage/angleContents/' . $extraContent->angle_content_uuid . '/images/',
                'src="images/',
                $updatingIndex
            );
        });

        // UPDATING CSS WITH FONT CHANGES
        $updatingCss = str_replace(
            '../../storage/templates/' . $template->uuid . '/fonts/' . $template->asset_unique_uuid . '-',
            'fonts/' . $template->asset_unique_uuid . '-',
            $updatingCss
        );

        $selfHostedProperty = $request->is_self_hosted;

        $fullHtml = <<<HTMLDOC
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$angleTemplate->name}</title>
            <script src=" https://cdn.jsdelivr.net/npm/sweetalert2@11.22.4/dist/sweetalert2.all.min.js "></script>
            <link href=" https://cdn.jsdelivr.net/npm/sweetalert2@11.22.4/dist/sweetalert2.min.css " rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.2/build/css/intlTelInput.css">
            {$template->head}
            <style>
                input {
                    outline: none !important;
                    outline-offset: none !important;
                    box-shadow: none !important;
                }

                .toggle_button {
                    padding: 5px !important;
                    text-transform: capitalize !important;
                }

                .MuiOutlinedInput-input:focus {
                    --tw-ring-inset: 0px
                }

                .customPicker .react-colorful {
                    height: 104px;
                }

                .customPicker .react-colorful__hue {
                    height: 15px;
                }

                .customPicker .react-colorful__hue-pointer {
                    width: 15px;
                    height: 15px
                }

                .customPicker .react-colorful__saturation-pointer {
                    width: 15px;
                    height: 15px
                }

                .customPickerTwo .react-colorful {
                    height: 70px;
                }

                .customPickerTwo .react-colorful__hue {
                    height: 15px;
                }

                .customPickerTwo .react-colorful__hue-pointer {
                    width: 15px;
                    height: 15px
                }

                .customPickerTwo .react-colorful__saturation-pointer {
                    width: 15px;
                    height: 15px
                }

                .cptlz {
                    text-transform: capitalize !important;
                }

                .megaButton {
                    height: 105px !important;
                    font-size: 26px !important;
                }

                .megaButtonSquare {
                    height: 225px !important;
                    font-size: 32px !important;
                }

                .swal2-container {
                    z-index: 9999
                }

                .editable-hover-border {
                    outline: 2px solid red !important;
                    cursor: pointer;
                    z-index: 9999;
                }

                .app-anchor {
                    cursor: pointer !important;
                    text-decoration: underline !important;
                    color: #3b7de3 !important;
                }

                .sticky-left-div {
                    position: fixed;
                    top: 50%;
                    left: 0;
                    transform: translateY(-50%);
                    z-index: 9999;
                    /* optional: ensures it stays on top */
                }

                .iti {
                    width: 100% !important;
                }

                .iti input {
                    width: 100% !important;
                    box-sizing: border-box;
                    /* ensures padding doesn't break layout */
                }

                .iti__country-name {
                    color: black !important;
                }

                .iti__selected-dial-code {
                    color: black !important;
                }

                .loader {
                    border: 3px solid #f3f3f3;
                    border-radius: 50%;
                    border-top: 3px solid #000000;
                    width: 25px;
                    height: 25px;
                    animation: spin 1s linear infinite;
                }

                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                .telInputs {
                    padding-left: 45px !important
                }

                {$updatingCss}
            </style>
        </head>
        <body>
            {$updatingIndex}
            <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.2/build/js/intlTelInput.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.2/build/js/utils.js"></script>
            <script>
                function initTelInputs(country) {
                    document.querySelectorAll(".telInputs").forEach(input => {
                        const iti = intlTelInput(input, {
                            initialCountry: country,
                            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.2/build/js/utils.js"
                        });
                        input.style.width = "100%";

                        input.form?.addEventListener("submit", async e => {
                            e.preventDefault(); // stop immediate submission

                            const btn = input.form.querySelector('[type="submit"]');
                            if (btn) {
                                btn.dataset.original = btn.innerHTML; // save original
                                btn.innerHTML = `<div class="loader"></div>`; // show CSS loader
                                btn.style.opacity = "0.6";
                                btn.disabled = true;
                            }

                            // SELF HOSTED
                            const selfHosted = Object.assign(document.createElement("input"), {
                                type: "hidden",
                                name: "is_self_hosted",
                                value: {$selfHostedProperty}
                            });
                            input.form.appendChild(selfHosted);

                            const raw = input.value.trim();
                            if (!raw) {
                                input.form.submit(); // allow submit anyway (no phone)
                                return;
                            }

                            const { iso2 } = iti.getSelectedCountryData();

                            // Phone field
                            const hiddenPhone = Object.assign(document.createElement("input"), {
                                type: "hidden",
                                name: "phone",
                                value: iti.getNumber()
                            });
                            input.form.appendChild(hiddenPhone);

                            // Country field
                            const hiddenCountry = Object.assign(document.createElement("input"), {
                                type: "hidden",
                                name: "country",
                                value: iso2
                            });
                            input.form.appendChild(hiddenCountry);

                            // Language field
                            const hiddenLang = Object.assign(document.createElement("input"), {
                                type: "hidden",
                                name: "lang",
                                value: navigator.language || navigator.userLanguage
                            });
                            input.form.appendChild(hiddenLang);

                            // User IP (fetch before submitting)
                            try {
                                const res = await fetch("https://ipinfo.io/json");
                                const data = await res.json();

                                const hiddenIP = Object.assign(document.createElement("input"), {
                                    type: "hidden",
                                    name: "userip",
                                    value: data.ip || ""
                                });
                                input.form.appendChild(hiddenIP);
                            } catch (err) {
                                console.error("Failed to fetch IP:", err);

                                // still add empty IP field
                                const hiddenIP = Object.assign(document.createElement("input"), {
                                    type: "hidden",
                                    name: "userip",
                                    value: ""
                                });
                                input.form.appendChild(hiddenIP);
                            }

                            if (!iti.isValidNumber()) {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error!",
                                    text: "Please enter a valid phone number.",
                                });
                                btn.innerHTML = btn.dataset.original;
                                btn.style.opacity = "1";
                                btn.disabled = false;
                                return;
                            }

                            // ✅ Now submit form after IP is ready
                            input.form.submit();
                        });
                    });
                }

                // Try to detect user country
                fetch("https://ipapi.co/json/")
                    .then(res => res.json())
                    .then(data => {
                        const userCountry = data.country_code || "us"; // fallback if undefined
                        initTelInputs(userCountry.toLowerCase());
                    })
                    .catch(() => {
                        // If API fails, fallback to US
                        initTelInputs("us");
                    });
            </script>
            <script>{$updatingJs}</script>
            <script>
                // Additional JavaScript can go here
                document.addEventListener("DOMContentLoaded", function () {
                    const params = new URLSearchParams(window.location.search);
                    const form = document.querySelector("form");

                    if (params.toString() && form) {
                        if (params.has("api_error")) {
                            Swal.fire({
                                icon: "error",
                                title: "Error!",
                                text: decodeURIComponent(params.get("api_error")),
                            });
                            // Smooth scroll to form
                            form.scrollIntoView();
                        } else if (params.has("api_success")) {
                            // decodeURIComponent(params.get("api_success"));
                        }

                        // Remove api_error/api_success from URL without reload
                        params.delete("api_error");
                        params.delete("api_success");

                        const newUrl =
                            window.location.origin +
                            window.location.pathname +
                            (params.toString() ? "?" + params.toString() : "");

                        window.history.replaceState({}, document.title, newUrl);
                    }
                });
            </script>
            <script>
                // Grab current URL params
                const params = window.location.search; // e.g. ?id=123&status=active

                // Append them to form action
                const form = document.querySelector("form");
                form.action += params;
            </script>
        </body>
        </html>
        HTMLDOC;

        $zip->addFromString('index.php', $fullHtml);

        // Add files from public/api_files directory with text modifications
        $publicFilesPath = public_path('api_files');
        if (is_dir($publicFilesPath)) {
            // Get current user's API credentials
            $userApiCredentials = Auth::user()->apiCredential;

            $files = scandir($publicFilesPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $publicFilesPath . DIRECTORY_SEPARATOR . $file;
                    if (is_file($filePath)) {
                        // Read file content
                        $fileContent = file_get_contents($filePath);

                        // Modify the content based on your requirements
                        $modifiedContent = $this->modifyApiFileContent($fileContent, $file, $userApiCredentials, $fullHtml);

                        // Add modified content to zip under 'api_files/' directory
                        $zip->addFromString('api_files/' . $file, $modifiedContent);
                    }
                }
            }
        }

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function deleteAngleTemplate(Request $request)
    {
        // return $request;

        $angleTemplate = AngleTemplate::find($request->angle_template_id);

        if (!$angleTemplate) {
            return sendResponse(false, "Sales Page Not Found");
        }

        $extraContents = ExtraContent::where('angle_template_uuid', $angleTemplate->uuid)->get();
        $extraContents->each(function ($content) {
            Storage::disk('public')->deleteDirectory("angleTemplates/{$content->angle_template_uuid}");
            $content->delete();
        });

        $angleTemplate->delete();

        return sendResponse(true, "Sales Page is deleted Successfully.");
    }

    /**
     * Rename an AngleTemplate (Sales Page)
     */
    public function renameAngleTemplate(Request $request)
    {
        $request->validate([
            'angle_template_id' => 'required|integer',
            'name' => 'required|string|max:255',
        ]);

        $angleTemplate = AngleTemplate::find($request->angle_template_id);
        if (!$angleTemplate) {
            return sendResponse(false, 'Sales Page Not Found');
        }

        $angleTemplate->name = $request->name;
        $angleTemplate->save();

        return sendResponse(true, 'Sales Page renamed successfully.', $angleTemplate);
    }

    /**
     * Modify API file content before adding to zip
     *
     * @param string $content The original file content
     * @param string $filename The name of the file being processed
     * @param \App\Models\UserApiCredential|null $userApiCredentials The user's API credentials
     * @return string The modified content
     */
    private function modifyApiFileContent($content, $filename, $userApiCredentials = null, $fullHTML = null)
    {
        // If no credentials provided, return content as-is
        if (!$userApiCredentials) {
            return $content;
        }

        // For PHP files, replace configuration values
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'php') {

            switch ($filename) {
                case 'aweber.php':
                    $content = str_replace('$clientId = "";', '$clientId = "' . ($userApiCredentials->aweber_client_id ?? '') . '";', $content);
                    $content = str_replace('$clientSecret = "";', '$clientSecret = "' . ($userApiCredentials->aweber_client_secret ?? '') . '";', $content);
                    $content = str_replace('$accountId = "";', '$accountId = "' . ($userApiCredentials->aweber_account_id ?? '') . '";', $content);
                    $content = str_replace('$listId = "";', '$listId = "' . ($userApiCredentials->aweber_list_id ?? '') . '";', $content);
                    break;

                case 'electra.php':
                    $content = str_replace("'affid' => '13',", "'affid' => '" . ($userApiCredentials->electra_affid ?? '') . "',", $content);
                    // Note: electra seems to use a different API key mechanism, might need to check the file more
                    break;

                case 'dark.php':
                    $content = str_replace("'ai' => '',", "'ai' => '" . ($userApiCredentials->dark_ai ?? '') . "',", $content);
                    $content = str_replace("'ci' => '',", "'ci' => '" . ($userApiCredentials->dark_ci ?? '') . "',", $content);
                    $content = str_replace("'gi' => '',", "'gi' => '" . ($userApiCredentials->dark_gi ?? '') . "',", $content);
                    $content = str_replace('$username = "";', '$username = "' . ($userApiCredentials->dark_username ?? '') . '";', $content);
                    $content = str_replace('$password = "";', '$password = "' . ($userApiCredentials->dark_password ?? '') . '";', $content);
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->dark_api_key ?? '') . '";', $content);
                    break;

                case 'elps.php':
                    $content = str_replace("'ai' => '',", "'ai' => '" . ($userApiCredentials->elps_ai ?? '') . "',", $content);
                    $content = str_replace("'ci' => '',", "'ci' => '" . ($userApiCredentials->elps_ci ?? '') . "',", $content);
                    $content = str_replace("'gi' => '',", "'gi' => '" . ($userApiCredentials->elps_gi ?? '') . "',", $content);
                    $content = str_replace('$username = "";', '$username = "' . ($userApiCredentials->elps_username ?? '') . '";', $content);
                    $content = str_replace('$password = "";', '$password = "' . ($userApiCredentials->elps_password ?? '') . '";', $content);
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->elps_api_key ?? '') . '";', $content);
                    break;

                case 'meeseeksmedia.php':
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->meeseeks_api_key ?? '') . '";', $content);
                    break;

                case 'novelix.php':
                    $content = str_replace("'affid' => '',", "'affid' => '" . ($userApiCredentials->novelix_affid ?? '') . "',", $content);
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->novelix_api_key ?? '') . '";', $content);
                    break;

                case 'tigloo.php':
                    $content = str_replace("'ai' => '',", "'ai' => '" . ($userApiCredentials->tigloo_ai ?? '') . "',", $content);
                    $content = str_replace("'ci' => '',", "'ci' => '" . ($userApiCredentials->tigloo_ci ?? '') . "',", $content);
                    $content = str_replace("'gi' => '',", "'gi' => '" . ($userApiCredentials->tigloo_gi ?? '') . "',", $content);
                    $content = str_replace('$username = "";', '$username = "' . ($userApiCredentials->tigloo_username ?? '') . '";', $content);
                    $content = str_replace('$password = "";', '$password = "' . ($userApiCredentials->tigloo_password ?? '') . '";', $content);
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->tigloo_api_key ?? '') . '";', $content);
                    break;

                case 'koi.php':
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->koi_api_key ?? '') . '";', $content);
                    break;

                case 'pastile.php':
                    $content = str_replace("'ai' => '',", "'ai' => '" . ($userApiCredentials->pastile_ai ?? '') . "',", $content);
                    $content = str_replace("'ci' => '',", "'ci' => '" . ($userApiCredentials->pastile_ci ?? '') . "',", $content);
                    $content = str_replace("'gi' => '',", "'gi' => '" . ($userApiCredentials->pastile_gi ?? '') . "',", $content);
                    $content = str_replace('$username = "";', '$username = "' . ($userApiCredentials->pastile_username ?? '') . '";', $content);
                    $content = str_replace('$password = "";', '$password = "' . ($userApiCredentials->pastile_password ?? '') . '";', $content);
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->pastile_api_key ?? '') . '";', $content);
                    break;

                case 'thank_you.php':
                    $content = str_replace("let DynamicFacebookPixelURL = '';", "let DynamicFacebookPixelURL = '" . ($userApiCredentials->facebook_pixel_url ?? '') . "';", $content);
                    $content = str_replace("let DynamicSecondaryPixelURL = '';", "let DynamicSecondaryPixelURL = '" . ($userApiCredentials->second_pixel_url ?? '') . "';", $content);
                    $content = str_replace("PROJECTURL/", env('APP_URL') . "/images/", $content);
                    break;

                case 'config.php':
                    $crawler = new Crawler($fullHTML);
                    $node = $crawler->filter('input[name="project_directory"]');
                    $value = $node->count() > 0 ? $node->attr('value') : '';
                    // logger("TEST: " . $value);
                    // Update BASE_URL to current domain if needed
                    // $baseUrl = request()->getSchemeAndHttpHost();
                    if ($value)
                        $content = str_replace('http://localhost/myAppFolder', $value, $content);
                    break;

                default:
                    // No specific modifications for other files
                    break;
            }
        }

        // For JSON files (like tokens.json)
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'json') {
            $data = json_decode($content, true);
            if ($data !== null) {
                // You can modify JSON data here if needed
                $content = json_encode($data, JSON_PRETTY_PRINT);
            }
        }

        return $content;
    }

    public function duplicateAngleTemplate(Request $request, AngleTemplate $angleTemplate)
    {
        try {
            DB::beginTransaction();

            // Generate new UUIDs for the duplicated template
            $newUuid = (string) Str::uuid();

            // Create new AngleTemplate with duplicated data
            $newAngleTemplate = AngleTemplate::create([
                'uuid' => $newUuid,
                'angle_id' => $angleTemplate->angle_id,
                'template_id' => $angleTemplate->template_id,
                'user_id' => Auth::id(), // Set current user as owner
                'name' => $angleTemplate->name . ' (Copy)',
                'main_html' => $angleTemplate->main_html,
                'main_css' => $angleTemplate->main_css,
                'main_js' => $angleTemplate->main_js,
            ]);

            $preSearch = "angleTemplates/{$angleTemplate->uuid}/images";
            $preReplace = "angleTemplates/{$newUuid}/images";

            $newAngleTemplate->main_html = str_replace($preSearch, $preReplace, $newAngleTemplate->main_html);
            $newAngleTemplate->save();

            // Get original folder path
            $originalFolderPath = "angleTemplates/{$angleTemplate->uuid}";
            $newFolderPath = "angleTemplates/{$newUuid}";

            // Check if original folder exists
            if (Storage::disk('public')->exists($originalFolderPath)) {
                // Copy entire folder structure
                $this->copyDirectory($originalFolderPath, $newFolderPath);
            }

            // Duplicate ExtraContent records
            $originalContents = ExtraContent::where('angle_template_uuid', $angleTemplate->uuid)->get();

            foreach ($originalContents as $content) {
                $newBlobUrl = $content->blob_url;

                ExtraContent::create([
                    'angle_template_uuid' => $newUuid,
                    'angle_uuid' => $content->angle_uuid,
                    'name' => $content->name,
                    'blob_url' => $newBlobUrl,
                    'type' => $content->type,
                    'can_be_deleted' => $content->can_be_deleted,
                ]);
            }

            DB::commit();

            return sendResponse(true, 'AngleTemplate duplicated successfully!', [
                'angleTemplate' => $newAngleTemplate,
                'original_uuid' => $angleTemplate->uuid,
                'new_uuid' => $newUuid
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return sendResponse(false, 'Error duplicating AngleTemplate: ' . $e->getMessage());
        }
    }

    private function copyDirectory($source, $destination)
    {
        $disk = Storage::disk('public');

        // Get all files in the source directory recursively
        $files = $disk->allFiles($source);

        foreach ($files as $file) {
            // Create the destination path
            $relativePath = str_replace($source, '', $file);
            $destinationFile = $destination . $relativePath;

            // Get file contents and copy to new location
            $contents = $disk->get($file);
            $disk->put($destinationFile, $contents);
        }

        // Also copy any subdirectories structure
        $directories = $disk->allDirectories($source);
        foreach ($directories as $directory) {
            $relativePath = str_replace($source, '', $directory);
            $destinationDir = $destination . $relativePath;
            $disk->makeDirectory($destinationDir);
        }
    }

    public function translateAngleTemplate(Request $request)
    {
        Log::info('🚀 Translation started', [
            'angle_template_id' => $request->angle_template_id,
            'target_language' => $request->target_language,
            'user_id' => Auth::id()
        ]);

        try {
            $request->validate([
                'angle_template_id' => 'required|exists:angle_templates,id',
                'target_language' => 'required|string|max:10',
                'split_sentences' => 'nullable|string',
                'preserve_formatting' => 'nullable|integer'
            ]);

            $angleTemplate = AngleTemplate::findOrFail($request->angle_template_id);
            $targetLanguage = $request->target_language;
            $splitSentences = $request->split_sentences;
            $preserveFormatting = $request->preserve_formatting;

            Log::info('✅ Template found', [
                'template_id' => $angleTemplate->id,
                'template_name' => $angleTemplate->name,
                'user_id' => $angleTemplate->user_id,
                'html_length' => strlen($angleTemplate->main_html)
            ]);

            // Check if user has permission to edit this template
            // if (Auth::user()->role->name !== 'admin' && $angleTemplate->user_id !== Auth::id()) {
            //     Log::warning('❌ Permission denied', [
            //         'user_role' => Auth::user()->role->name,
            //         'template_owner' => $angleTemplate->user_id,
            //         'current_user' => Auth::id()
            //     ]);
            //     return sendResponse(false, "You don't have permission to translate this template", null);
            // }

            Log::info('✅ Permission granted');

            // Initialize DeepL service
            $deepLService = new \App\Services\DeepLService();
            Log::info('✅ DeepL service initialized');

            // Get the main_html content
            $originalHtml = $angleTemplate->main_html;

            if (empty($originalHtml)) {
                Log::warning('❌ No HTML content to translate');
                return sendResponse(false, "No content to translate", null);
            }

            Log::info('📝 Starting HTML content translation', [
                'original_html_length' => strlen($originalHtml),
                'target_language' => $targetLanguage
            ]);

            // Extract and translate text content in batches
            $startTime = microtime(true);
            $translatedHtml = $this->translateHtmlContentMinimal($originalHtml, $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
            $endTime = microtime(true);

            Log::info('✅ Translation completed', [
                'translation_time_seconds' => round($endTime - $startTime, 2),
                'translated_html_length' => strlen($translatedHtml)
            ]);

            // Update the angle template with translated content
            $originalName = $angleTemplate->name;
            $angleTemplate->main_html = $translatedHtml;
            $angleTemplate->name = $angleTemplate->name . " ({$targetLanguage})";
            $angleTemplate->save();

            Log::info('✅ Template updated successfully', [
                'original_name' => $originalName,
                'new_name' => $angleTemplate->name,
                'template_id' => $angleTemplate->id
            ]);

            return sendResponse(true, "Sales page translated successfully to {$targetLanguage}", $angleTemplate);
        } catch (\Exception $e) {
            Log::error('❌ Translation failed', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            return sendResponse(false, "Translation failed: " . $e->getMessage(), null);
        }
    }

    private function translateHtmlContentMinimal($html, $targetLanguage, $deepLService, $splitSentences = null, $preserveFormatting = null)
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

            $placeholder = "##TRANSLATE_" . $placeholderIndex . "##";
            $textToTranslate[$placeholder] = $text;
            $placeholders[] = $placeholder;
            $placeholderIndex++;

            return '>' . $placeholder . '<';
        }, $html);

        Log::info('✅ Text extraction completed', [
            'texts_found_between_tags' => count($textToTranslate),
            'sample_texts' => array_slice(array_values($textToTranslate), 0, 3)
        ]);

        Log::info('📝 Extracting text from HTML attributes...');

        // Find common attributes that should be translated
        $attributePattern = '/(alt|title|placeholder)=["\']([^"\']{3,})["\']i/';
        $html = preg_replace_callback($attributePattern, function ($matches) use (&$textToTranslate, &$placeholders, &$placeholderIndex) {
            $attribute = $matches[1];
            $text = $matches[2];

            // Skip if text contains URLs or looks like code
            if (preg_match('/^https?:\/\//', $text) ||
                preg_match('/[{}()<>\/\\\\]/', $text) ||
                strlen($text) < 3) {
                return $matches[0];
            }

            $placeholder = "##TRANSLATE_" . $placeholderIndex . "##";
            $textToTranslate[$placeholder] = $text;
            $placeholders[] = $placeholder;
            $placeholderIndex++;

            return $attribute . '="' . $placeholder . '"';
        }, $html);

        Log::info('✅ Attribute extraction completed', [
            'total_texts_to_translate' => count($textToTranslate),
            'sample_attribute_texts' => array_slice(array_values($textToTranslate), -3)
        ]);

        // If no text to translate, return original
        if (empty($textToTranslate)) {
            Log::warning('⚠️ No translatable text found, returning original HTML');
            return $html;
        }

        Log::info('🌐 Starting batch translation', [
            'total_items' => count($textToTranslate),
            'target_language' => $targetLanguage
        ]);

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

            Log::info('🔄 Replacing placeholders in HTML...');

            // Replace placeholders with translations
            foreach ($translations as $placeholder => $translation) {
                $html = str_replace($placeholder, $translation, $html);
            }

            Log::info('✅ Translation replacement completed successfully');

        } catch (\Exception $e) {
            Log::error('❌ Batch translation failed, falling back to original text', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);

            // If batch translation fails, fallback to original text
            foreach ($textToTranslate as $placeholder => $originalText) {
                $html = str_replace($placeholder, $originalText, $html);
            }
        }

        Log::info('🏁 HTML translation process completed', [
            'final_html_length' => strlen($html)
        ]);

        return $html;
    }
}
