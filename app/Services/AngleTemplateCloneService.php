<?php

namespace App\Services;

use App\Models\AngleTemplate;
use App\Models\ExtraContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AngleTemplateCloneService
{
    /**
     * Duplicate a landing page (angle template) for another user in the same organization.
     */
    public function cloneIntoOrgForUser(
        AngleTemplate $source,
        int $targetUserId,
        int $organizationId
    ): AngleTemplate {
        return DB::transaction(function () use ($source, $targetUserId, $organizationId) {
            $newUuid = (string) Str::uuid();

            $new = AngleTemplate::create([
                'uuid' => $newUuid,
                'angle_id' => $source->angle_id,
                'template_id' => $source->template_id,
                'user_id' => $targetUserId,
                'organization_id' => $organizationId,
                'name' => $source->name,
                'main_html' => $source->main_html,
                'main_css' => $source->main_css,
                'main_js' => $source->main_js,
            ]);

            $preSearch = 'angleTemplates/' . $source->uuid . '/images';
            $preReplace = 'angleTemplates/' . $newUuid . '/images';
            $new->main_html = str_replace($preSearch, $preReplace, $new->main_html);
            $new->save();

            $originalFolderPath = 'angleTemplates/' . $source->uuid;
            $newFolderPath = 'angleTemplates/' . $newUuid;
            if (Storage::disk('public')->exists($originalFolderPath)) {
                $this->copyDirectory($originalFolderPath, $newFolderPath);
            }

            $originalContents = ExtraContent::query()
                ->where('angle_template_uuid', $source->uuid)
                ->get();

            foreach ($originalContents as $content) {
                ExtraContent::create([
                    'angle_template_uuid' => $newUuid,
                    'angle_uuid' => $content->angle_uuid,
                    'name' => $content->name,
                    'blob_url' => $content->blob_url,
                    'type' => $content->type,
                    'can_be_deleted' => $content->can_be_deleted,
                ]);
            }

            return $new->fresh();
        });
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $disk = Storage::disk('public');
        $files = $disk->allFiles($source);

        foreach ($files as $file) {
            $relativePath = str_replace($source, '', $file);
            $destinationFile = $destination . $relativePath;
            $disk->put($destinationFile, $disk->get($file));
        }

        $directories = $disk->allDirectories($source);
        foreach ($directories as $directory) {
            $relativePath = str_replace($source, '', $directory);
            $destinationDir = $destination . $relativePath;
            $disk->makeDirectory($destinationDir);
        }
    }
}
