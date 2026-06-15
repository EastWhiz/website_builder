<?php

namespace App\Services;

use App\Models\AngleTemplate;
use App\Models\ExtraContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AngleTemplateCloneService
{
    public function __construct(
        private readonly LandingPageAssetService $landingPageAssetService
    ) {}

    /**
     * Duplicate a landing page (angle template) for another user in the same organization.
     */
    public function cloneIntoOrgForUser(
        AngleTemplate $source,
        int $targetUserId,
        int $organizationId
    ): AngleTemplate {
        $newUuid = (string) Str::uuid();

        try {
            return DB::transaction(function () use ($source, $targetUserId, $organizationId, $newUuid) {
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

                $originalContents = ExtraContent::query()
                    ->where('angle_template_uuid', $source->uuid)
                    ->get();

                $cloneAssets = $this->landingPageAssetService->copyAssetsForClone(
                    $newUuid,
                    [$source->main_html, $source->main_css, $source->main_js],
                    $originalContents->pluck('name')->all()
                );
                $replacements = $cloneAssets['replacements'];

                $new->main_html = $this->landingPageAssetService->rewriteStorageReferences($new->main_html, $replacements);
                $new->main_css = $this->landingPageAssetService->rewriteStorageReferences((string) $new->main_css, $replacements);
                $new->main_js = $this->landingPageAssetService->rewriteStorageReferences((string) $new->main_js, $replacements);
                $new->save();

                foreach ($originalContents as $content) {
                    $relativeName = $this->landingPageAssetService->normalizeStoragePath($content->name);
                    $clonedName = $relativeName !== null && isset($replacements[$relativeName])
                        ? str_replace('../../storage/', '/storage/', $replacements[$relativeName])
                        : $content->name;

                    ExtraContent::create([
                        'angle_template_uuid' => $newUuid,
                        'angle_uuid' => $content->angle_uuid,
                        'asset_unique_uuid' => $content->asset_unique_uuid,
                        'name' => $clonedName,
                        'blob_url' => $content->blob_url,
                        'type' => $content->type,
                        'can_be_deleted' => $content->can_be_deleted,
                    ]);
                }

                return $new->fresh();
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->deleteDirectory('angleTemplates/'.$newUuid);

            throw $exception;
        }
    }
}
