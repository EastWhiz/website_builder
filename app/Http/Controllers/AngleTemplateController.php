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
            return sendResponse(true, 'Angle Applied to Selected Themes.');
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

        $zipFileName = 'LandingPage_' . $angleTemplate->name . '.zip';
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
            return sendResponse(false, "Landing Page Not Found");
        }

        $extraContents = ExtraContent::where('angle_template_uuid', $angleTemplate->uuid)->get();
        $extraContents->each(function ($content) {
            Storage::disk('public')->deleteDirectory("angleTemplates/{$content->angle_template_uuid}");
            $content->delete();
        });

        $angleTemplate->delete();

        return sendResponse(true, "Landing Page is deleted Successfully.");
    }

    /**
     * Rename an AngleTemplate (Landing Page)
     */
    public function renameAngleTemplate(Request $request)
    {
        $request->validate([
            'angle_template_id' => 'required|integer',
            'name' => 'required|string|max:255',
        ]);

        $angleTemplate = AngleTemplate::find($request->angle_template_id);
        if (!$angleTemplate) {
            return sendResponse(false, 'Landing Page Not Found');
        }

        $angleTemplate->name = $request->name;
        $angleTemplate->save();

        return sendResponse(true, 'Landing Page renamed successfully.', $angleTemplate);
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

                case 'riceleads.php':
                    $content = str_replace("'affid' => '',", "'affid' => '" . ($userApiCredentials->riceleads_affid ?? '') . "',", $content);
                    //$content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->riceleads_api_key ?? '') . '";', $content);
                    break;

                case 'newmedis.php':
                    $content = str_replace("'ai' => '',", "'ai' => '" . ($userApiCredentials->newmedis_ai ?? '') . "',", $content);
                    $content = str_replace("'ci' => '',", "'ci' => '" . ($userApiCredentials->newmedis_ci ?? '') . "',", $content);
                    $content = str_replace("'gi' => '',", "'gi' => '" . ($userApiCredentials->newmedis_gi ?? '') . "',", $content);
                    $content = str_replace('$username = "";', '$username = "' . ($userApiCredentials->newmedis_username ?? '') . '";', $content);
                    $content = str_replace('$password = "";', '$password = "' . ($userApiCredentials->newmedis_password ?? '') . '";', $content);
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->newmedis_api_key ?? '') . '";', $content);
                    break;

                case 'seamediaone.php':
                    $content = str_replace("'ai' => '',", "'ai' => '" . ($userApiCredentials->seamediaone_ai ?? '') . "',", $content);
                    $content = str_replace("'ci' => '',", "'ci' => '" . ($userApiCredentials->seamediaone_ci ?? '') . "',", $content);
                    $content = str_replace("'gi' => '',", "'gi' => '" . ($userApiCredentials->seamediaone_gi ?? '') . "',", $content);
                    $content = str_replace('$username = "";', '$username = "' . ($userApiCredentials->seamediaone_username ?? '') . '";', $content);
                    $content = str_replace('$password = "";', '$password = "' . ($userApiCredentials->seamediaone_password ?? '') . '";', $content);
                    $content = str_replace('$xapikey = "";', '$xapikey = "' . ($userApiCredentials->seamediaone_api_key ?? '') . '";', $content);
                    break;

                case 'nauta.php':
                    $content = str_replace('$nautaApiToken = "";', '$nautaApiToken = "' . ($userApiCredentials->nauta_api_token ?? '') . '";', $content);
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
            $request->validate([
                'angle_template_id' => 'required|exists:angle_templates,id',
            'target_language'   => 'required|string|max:10',
                'split_sentences' => 'nullable|string',
                'preserve_formatting' => 'nullable|integer'
            ]);

        $angleTemplate   = AngleTemplate::findOrFail($request->angle_template_id);
        $targetLanguage  = strtoupper($request->target_language);
            $splitSentences = $request->split_sentences;
            $preserveFormatting = $request->preserve_formatting;

        if (empty(trim($angleTemplate->main_html))) {
            return sendResponse(false, 'No HTML content to translate');
        }
    
        try {
            $deepLService = new \App\Services\DeepLService();
    
            $start = microtime(true);
    
            $translatedHtml = $this->translateHtmlUsingDOM(
                $angleTemplate->main_html,
                $targetLanguage,
                $deepLService,
                $splitSentences,
                $preserveFormatting
            );
    
            // RTL support
            if ($this->isRtlLanguage($targetLanguage)) {
                $translatedHtml = $this->applyRtlSupport($translatedHtml);
            }
    
            $angleTemplate->update([
                'main_html' => $translatedHtml,
                'name'      => $angleTemplate->name . " ({$targetLanguage})",
            ]);
    
            Log::info('Translation completed', [
                'time_sec' => round(microtime(true) - $start, 2),
                'chars'    => strlen($translatedHtml)
            ]);
    
            return sendResponse(true, "Translated successfully", $angleTemplate);
    
        } catch (\Throwable $e) {
            Log::error('Translation failed', [
                'error' => $e->getMessage()
            ]);
    
            return sendResponse(false, 'Translation failed: ' . $e->getMessage());
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
        $html = preg_replace_callback('/>(.*?)</s', function ($matches) use (&$textToTranslate, &$placeholders, &$placeholderIndex) {
            $originalText = $matches[1]; // Keep original with whitespace
            $text = trim($originalText);
            
            // Skip if this is a protected tag placeholder
            if (strpos($originalText, '##PROTECTED_TAG_') !== false) {
                return $matches[0];
            }

            // Check if we're inside a list item by examining the full match
            $fullMatch = $matches[0];
            $isInsideListItem = preg_match('/<li[^>]*>\s*' . preg_quote($originalText, '/') . '/is', $fullMatch) ||
                                preg_match('/<li[^>]*>/i', substr($fullMatch, 0, 50));
            
            // Skip empty text
            if (empty($text)) {
                return $matches[0];
            }
            
            // For list items, be more lenient - translate even if it starts with numbers
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

            // Store original text with whitespace for exact replacement
            $placeholder = "##TRANSLATE_" . $placeholderIndex . "##";
            $textToTranslate[$placeholder] = [
                'original' => $originalText, // Keep original with whitespace
                'text' => $text // Trimmed version for translation
            ];
            $placeholders[] = $placeholder;
            $placeholderIndex++;

            return '>' . $placeholder . '<';
        }, $html);
        
        // Restore protected tags
        foreach ($protectedTags as $placeholder => $originalTag) {
            $html = str_replace($placeholder, $originalTag, $html);
        }

        Log::info('✅ Text extraction completed', [
            'texts_found_between_tags' => count($textToTranslate),
            'sample_texts' => array_slice(array_map(function($item) {
                return is_array($item) ? $item['text'] : $item;
            }, array_values($textToTranslate)), 0, 3)
        ]);

        Log::info('📝 Extracting text from HTML attributes...');

        // Find common attributes that should be translated
        // Pattern matches: attribute="value" or attribute='value' (handles both single and double quotes)
        // Also handles whitespace around the equals sign
        $attributePattern = '/(alt|title|placeholder)\s*=\s*["\']([^"\']{2,})["\']/i';
        $html = preg_replace_callback($attributePattern, function ($matches) use (&$textToTranslate, &$placeholders, &$placeholderIndex) {
            $attribute = $matches[1];
            $text = $matches[2];
            $fullMatch = $matches[0];
            // Extract the quote character used (single or double)
            preg_match('/=\s*(["\'])/', $fullMatch, $quoteMatch);
            $quoteChar = $quoteMatch[1] ?? '"';

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
            if (preg_match('/^##TRANSLATE_|^PLACEHOLDER_|^##PROTECTED_/i', $text)) {
                return $matches[0];
            }

            $placeholder = "##TRANSLATE_" . $placeholderIndex . "##";
            $textToTranslate[$placeholder] = [
                'original' => $text,
                'text' => trim($text),
                'attribute' => strtolower($attribute) // Store which attribute this is for
            ];
            $placeholders[] = $placeholder;
            $placeholderIndex++;

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
            
            // CRITICAL: Ensure counts match before mapping to prevent wrong translations
            if (count($translatedParts) !== count($uniqueTexts)) {
                Log::error('❌ CRITICAL: Cannot safely map translations - count mismatch', [
                    'expected' => count($uniqueTexts),
                    'received' => count($translatedParts),
                    'difference' => count($uniqueTexts) - count($translatedParts)
                ]);
                // Pad or trim to match counts to prevent index misalignment
                while (count($translatedParts) < count($uniqueTexts)) {
                    $missingIndex = count($translatedParts);
                    $translatedParts[] = $uniqueTexts[$missingIndex]; // Use original text
                }
                if (count($translatedParts) > count($uniqueTexts)) {
                    $translatedParts = array_slice($translatedParts, 0, count($uniqueTexts));
                }
            }

            // Map translations back to placeholders using deduplication map
            $translations = [];
            $unchangedCount = 0;
            $retriedTexts = []; // Track which texts we've retried to avoid duplicate API calls
            
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
                if (isset($uniqueTranslations[$normalizedText])) {
                    $translation = $uniqueTranslations[$normalizedText];
                    
                    // CRITICAL FIX: Validate button text translations
                    // If button text gets a very long translation (like banner text), it's wrong
                    if ((stripos($textForComparison, 'anmelden') !== false || stripos($textForComparison, 'jetzt') !== false) && 
                        strlen($translation) > strlen($textForComparison) * 2.5) {
                        // Button text got wrong translation - use original instead
                        Log::error("❌ Button text translation validation failed", [
                            'placeholder' => $placeholder,
                            'original' => $textForComparison,
                            'wrong_translation_length' => strlen($translation),
                            'original_length' => strlen($textForComparison)
                        ]);
                        $translation = $textForComparison;
                    }
                } else {
                    // If not found in map, use original text (shouldn't happen but safety fallback)
                    $translation = $textForComparison;
                    Log::warning("⚠️ Translation not found in map", [
                        'normalized_text' => $normalizedText,
                        'original_text' => substr($textForComparison, 0, 50),
                        'placeholder' => $placeholder
                    ]);
                }
                
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

            Log::info('🔄 Replacing placeholders in HTML...');

            // Replace placeholders with translations
            // Use a single pass with proper escaping to ensure each placeholder is replaced exactly once
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
                    $replacementText = htmlspecialchars($finalTranslation, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
                }
                
                // Escape special regex characters in placeholder
                $escapedPlaceholder = preg_quote($placeholder, '/');
                // Escape special regex characters in replacement text for preg_replace
                $escapedReplacement = preg_replace('/([\\\\$])/', '\\\\$1', $replacementText);
                
                // Log button text replacement for debugging
                if (isset($textData['text']) && (stripos($textData['text'], 'anmelden') !== false || stripos($textData['text'], 'jetzt') !== false)) {
                    Log::info("🔘 Button text replacement", [
                        'placeholder' => $placeholder,
                        'original' => $textData['text'],
                        'translated' => $finalTranslation,
                        'before_count' => $beforeCount
                    ]);
                }
                
                // Replace ONLY the first occurrence of this placeholder
                // Each placeholder should only appear once in the HTML, so we replace it once
                // This prevents duplicate replacements that could cause content duplication
                $html = preg_replace('/' . $escapedPlaceholder . '/', $escapedReplacement, $html, 1);
                
                if ($isAttribute && $beforeCount > 0) {
                    Log::info("🔄 Attribute placeholder replaced", [
                        'placeholder' => $placeholder,
                        'attribute' => $textData['attribute'],
                        'original' => $textData['text'],
                        'translated' => $finalTranslation,
                        'before_count' => $beforeCount
                    ]);
                }
                
                // Count occurrences after replacement
                $afterCount = substr_count($html, $placeholder);
                $replaced = $beforeCount - $afterCount;
                
                if ($beforeCount > 1) {
                    Log::warning("⚠️ Placeholder appeared multiple times", [
                        'placeholder' => $placeholder,
                        'occurrences' => $beforeCount,
                        'replaced' => $replaced
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

            Log::info('✅ Translation replacement completed successfully');

        } catch (\Exception $e) {
            Log::error('❌ Batch translation failed, falling back to original text', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);

            return $html;
        } catch (\Exception $e) {
            Log::error('❌ Batch translation failed, falling back to original text', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);

            // If batch translation fails, fallback to original text
            if (isset($textToTranslate) && is_array($textToTranslate)) {
                foreach ($textToTranslate as $placeholder => $textData) {
                    $originalText = is_array($textData) ? (isset($textData['original']) ? $textData['original'] : (isset($textData['text']) ? $textData['text'] : $textData)) : $textData;
                $html = str_replace($placeholder, $originalText, $html);
                }
            }
        }

        Log::info('🏁 HTML translation process completed', [
            'final_html_length' => strlen($html)
        ]);

        return $html;
    }

    /**
     * Check if a language code represents an RTL (Right-to-Left) language
     */
    private function isRtlLanguage($lang)
{
    return in_array(strtoupper($lang), ['AR', 'HE', 'FA', 'UR']);
}

    private function applyRtlSupport($html)
    {
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

    private function translateHtmlUsingDOM($html, $targetLanguage, $deepLService, $splitSentences = null, $preserveFormatting = null)
    {
        libxml_use_internal_errors(true);

        // Better encoding handling - use mb_convert_encoding if needed
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><body>' . $html . '</body>',
            LIBXML_HTML_NODEFDTD
        );

        $xpath = new \DOMXPath($dom);

        $textNodes = [];
        $nodeMap   = [];
        $attributeNodes = []; // For alt, title, placeholder, aria-label
        $attributeTexts = []; // Separate array for attribute texts to translate

        // Extract text nodes
        foreach ($xpath->query('//text()[normalize-space()]') as $node) {
            if ($this->shouldSkipNode($node)) {
                continue;
            }

            $text = trim($node->nodeValue);

            // Skip numbers / symbols
            if (!preg_match('/[A-Za-zÀ-ÿ]/u', $text)) {
                continue;
            }

            // Skip likely names
            if ($this->looksLikePersonName($text)) {
                continue;
            }

            // Skip initials used for avatar boxes
            if ($this->looksLikeInitials($text)) {
                continue;
            }
            
            $hash = md5($text);
            $nodeMap[$hash][] = $node;
            $textNodes[$hash] = $text;
        }

        // Extract translatable attributes
        // Note: We use shouldSkipNodeForAttributes instead of shouldSkipNode
        // because we want to translate attributes of input/textarea/select elements
        // even though we skip their text content
        $attributesToTranslate = ['alt', 'title', 'placeholder', 'aria-label'];
        foreach ($attributesToTranslate as $attrName) {
            // Use XPath to find all elements with this attribute
            $query = "//*[@{$attrName}]";
            $elements = $xpath->query($query);
            
            foreach ($elements as $element) {
                /** @var \DOMElement $element */
                // For input/textarea/select, we want to translate their attributes
                // So we check if the element itself should be skipped (not its parent)
                $tagName = strtolower($element->nodeName);
                
                // Skip script, style, svg completely
                if (in_array($tagName, ['script', 'style', 'noscript', 'svg', 'path', 'defs', 'symbol'])) {
                    continue;
                }
                
                // Check if element is hidden
                if ($element->hasAttribute('hidden') || 
                    $element->getAttribute('aria-hidden') === 'true') {
                    continue;
                }
                
                // Check inline styles for hidden
                $style = strtolower($element->getAttribute('style'));
                if (str_contains($style, 'display:none') ||
                    str_contains($style, 'visibility:hidden') ||
                    str_contains($style, 'opacity:0')) {
                    continue;
                }
                
                $attrValue = trim($element->getAttribute($attrName));
                
                // Skip empty
                if (empty($attrValue)) {
                    continue;
                }
                
                // Skip URLs
                if (preg_match('/^https?:\/\//', $attrValue)) {
                    continue;
                }
                
                // For placeholder attributes, be more lenient - translate any text that looks translatable
                // Support all languages (Latin, Arabic, Chinese, Japanese, Cyrillic, etc.)
                if ($attrName === 'placeholder') {
                    // For placeholders, translate if it has any Unicode letters from any language
                    // This includes: Latin, Arabic, Chinese, Japanese, Cyrillic, Hebrew, etc.
                    if (!preg_match('/[\p{L}]/u', $attrValue)) {
                        continue;
                    }
                } else {
                    // For other attributes, require Unicode letters (supports all languages)
                    if (!preg_match('/[\p{L}]/u', $attrValue)) {
                        continue;
                    }
                }
                
                // Skip likely names (but be less strict for placeholders)
                if ($attrName !== 'placeholder' && $this->looksLikePersonName($attrValue)) {
                    continue;
                }
                
                // Use a unique hash that includes element position to avoid collisions
                // This ensures each attribute gets its own translation even if text is the same
                $uniqueId = uniqid('attr_', true);
                $hash = md5($attrValue . '|' . $attrName . '|' . $uniqueId);
                
                $attributeNodes[$hash] = [
                    'element' => $element,
                    'attribute' => $attrName,
                    'text' => $attrValue,
                    'original_text' => $attrValue // Keep original for deduplication
                ];
                
                // Store in separate array to avoid conflicts with text nodes
                $attributeTexts[$hash] = $attrValue;
            }
        }

        // Batch translate text nodes
        $translations = [];
        if (!empty($textNodes)) {
            $chunks = array_chunk($textNodes, 20, true);
            foreach ($chunks as $chunk) {
                $translated = $deepLService->translateBatch(
                    array_values($chunk),
                    $targetLanguage,
                    null,
                    $splitSentences,
                    $preserveFormatting
                );

                foreach (array_keys($chunk) as $i => $hash) {
                    $translations[$hash] = $translated[$i] ?? $chunk[$hash];
                }
            }
        }

        // Batch translate attributes separately to ensure all get translated
        if (!empty($attributeTexts)) {
            // Deduplicate attribute texts for translation (translate each unique text once)
            $uniqueAttributeTexts = [];
            $attributeTextMap = []; // Maps unique text to all hashes that use it
            foreach ($attributeTexts as $hash => $text) {
                $normalizedText = strtolower(trim($text));
                if (!isset($uniqueAttributeTexts[$normalizedText])) {
                    $uniqueAttributeTexts[$normalizedText] = $text;
                }
                $attributeTextMap[$normalizedText][] = $hash;
            }

            // Translate unique attribute texts
            if (!empty($uniqueAttributeTexts)) {
                $chunks = array_chunk($uniqueAttributeTexts, 20, true);
                foreach ($chunks as $chunk) {
                    // Get the texts in order for translation
                    $textsToTranslate = array_values($chunk);
                    $normalizedKeys = array_keys($chunk);
                    
                    $translated = $deepLService->translateBatch(
                        $textsToTranslate,
                        $targetLanguage,
                        null,
                        $splitSentences,
                        $preserveFormatting
                    );

                    // Map translations back using the correct indices
                    foreach ($normalizedKeys as $i => $normalizedText) {
                        $translatedText = $translated[$i] ?? $textsToTranslate[$i];
                        // Map translation back to all hashes that use this text
                        if (isset($attributeTextMap[$normalizedText])) {
                            foreach ($attributeTextMap[$normalizedText] as $hash) {
                                $translations[$hash] = $translatedText;
                            }
                        }
                    }
                }
            }
        }

        // Replace text nodes
        foreach ($nodeMap as $hash => $nodes) {
            if (isset($translations[$hash])) {
                foreach ($nodes as $node) {
                    $node->nodeValue = $translations[$hash];
                }
            }
        }

        // Replace attribute values
        foreach ($attributeNodes as $hash => $attrData) {
            if (isset($translations[$hash])) {
                /** @var \DOMElement $element */
                $element = $attrData['element'];
                $element->setAttribute($attrData['attribute'], $translations[$hash]);
            }
        }

       // Extract only BODY content to preserve structure (prevents header loss)
        $body = $dom->getElementsByTagName('body')->item(0);

        $html = '';
        foreach ($body->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        // Ensure UTF-8 encoding
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');

        return $html;
    }

private function shouldSkipNode(\DOMNode $node): bool
{
    while ($node) {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            /** @var \DOMElement $node */
            $tag = strtolower($node->nodeName);

            // Never touch these
            if (in_array($tag, [
                'script', 'style', 'noscript',
                'svg', 'path', 'defs', 'symbol',
                'input', 'textarea', 'select', 'option'
            ])) {
                return true;
            }

            // Hidden
            if ($node->hasAttribute('hidden')) {
                return true;
            }

            if ($node->getAttribute('aria-hidden') === 'true') {
                return true;
            }

            $style = strtolower($node->getAttribute('style'));
            if (
                str_contains($style, 'display:none') ||
                str_contains($style, 'visibility:hidden') ||
                str_contains($style, 'opacity:0')
            ) {
                return true;
            }
        }

        $node = $node->parentNode;
    }

    return false;
}

    /**
     * Check if a node should be skipped when extracting attributes
     * This is different from shouldSkipNode because we want to translate
     * attributes of input/textarea/select elements (like placeholder)
     * even though we skip their text content
     */
    private function shouldSkipNodeForAttributes(\DOMNode $node): bool
    {
        while ($node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                /** @var \DOMElement $node */
                $tag = strtolower($node->nodeName);

                // Never touch these (but allow their attributes to be checked)
                // We only skip script/style/svg completely
                if (in_array($tag, [
                    'script', 'style', 'noscript',
                    'svg', 'path', 'defs', 'symbol'
                ])) {
                    return true;
                }

                // Hidden elements - skip their attributes too
                if ($node->hasAttribute('hidden')) {
                    return true;
                }

                if ($node->getAttribute('aria-hidden') === 'true') {
                    return true;
                }

                $style = strtolower($node->getAttribute('style'));
                if (
                    str_contains($style, 'display:none') ||
                    str_contains($style, 'visibility:hidden') ||
                    str_contains($style, 'opacity:0')
                ) {
                    return true;
                }
            }

            $node = $node->parentNode;
        }

        return false;
    }

private function looksLikePersonName(string $text): bool
{
    $text = trim($text);

    // Short strings only
    if (str_word_count($text) > 3) {
        return false;
    }

    $words = preg_split('/\s+/', $text);

    foreach ($words as $word) {
        if (!preg_match('/^[A-ZÀ-Ý][a-zà-ÿ]+$/u', $word)) {
            return false;
        }
    }

    return true;
}

private function looksLikeInitials(string $text): bool
{
    $text = trim($text);

    // Remove spaces for checking (e.g. "R K")
    $compact = str_replace(' ', '', $text);

    // Length 1–4, all uppercase letters
    if (strlen($compact) >= 1 && strlen($compact) <= 4) {
        return preg_match('/^[A-ZÀ-Ý]+$/u', $compact) === 1;
    }

    return false;
}


}
