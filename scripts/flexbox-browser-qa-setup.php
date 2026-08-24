<?php

use App\Models\Angle;
use App\Models\AngleTemplate;
use App\Models\AngleTemplateBdContent;
use App\Models\Template;
use App\Models\User;
use App\Services\AngleTemplateMergeService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(AngleTemplateMergeService::class);
$user = User::query()->orderBy('id')->first();
if (! $user) {
    throw new RuntimeException('No user exists for browser QA setup.');
}

$runId = now()->format('YmdHis').'-'.Str::lower(Str::random(5));
$sourceTheme = Template::create([
    'uuid' => (string) Str::uuid(),
    'asset_unique_uuid' => (string) Str::uuid(),
    'name' => "Codex Flex QA Source Theme {$runId}",
    'head' => '',
    'index' => '<main class="qa-source-theme">'
        .'<section class="qa-slot qa-bd2-first"><!--INTERNAL--BD2--EXTERNAL--></section>'
        .'<section class="qa-slot qa-bd3-first"><!--INTERNAL--BD3--EXTERNAL--></section>'
        .'<section class="qa-slot qa-bd2-second"><!--INTERNAL--BD2--EXTERNAL--></section>'
        .'<section class="qa-slot qa-bd1"><!--INTERNAL--BD1--EXTERNAL--></section>'
        .'<section class="qa-slot qa-bd5"><!--INTERNAL--BD5--EXTERNAL--></section>'
        .'</main>',
]);

$targetTheme = Template::create([
    'uuid' => (string) Str::uuid(),
    'asset_unique_uuid' => (string) Str::uuid(),
    'name' => "Codex Flex QA Target Theme {$runId}",
    'head' => '',
    'index' => '<article class="qa-target-theme">'
        .'<header><!--INTERNAL--BD1--EXTERNAL--></header>'
        .'<section class="qa-target-bd3"><!--INTERNAL--BD3--EXTERNAL--></section>'
        .'<section class="qa-target-bd2"><!--INTERNAL--BD2--EXTERNAL--></section>'
        .'<footer><!--INTERNAL--BD5--EXTERNAL--></footer>'
        .'</article>',
]);

$anglePayload = [
    'uuid' => (string) Str::uuid(),
    'asset_unique_uuid' => (string) Str::uuid(),
    'name' => "Codex Flex QA Angle {$runId}",
];
if (Schema::hasColumn('angles', 'user_id')) {
    $anglePayload['user_id'] = $user->id;
}
if (Schema::hasColumn('angles', 'organization_id')) {
    $anglePayload['organization_id'] = $user->currentOrganization()?->id;
}
$angle = Angle::create($anglePayload);

$bdFlex = '<h1>QA Body 2-2</h1>'
    .'<div class="editableDiv lp-flex-box-wrapper" data-lp-flex-id="qa-bd2-flex" style="position: relative; width: 100%; margin: 16px 0; padding: 0;">'
    .'<style data-lp-flex-responsive-style="qa-bd2-flex">@media (max-width: 750px){.lp-flex-box[data-lp-flex-id="qa-bd2-flex"]{flex-direction:column!important}.lp-flex-box[data-lp-flex-id="qa-bd2-flex"]>.lp-flex-column{flex:1 1 100%!important;max-width:100%!important}}</style>'
    .'<div class="editableDiv lp-flex-box" data-lp-flex-id="qa-bd2-flex" data-lp-flex-columns="2" style="display:flex;gap:16px;width:100%;margin:0;">'
    .'<div class="editableDiv lp-flex-column" data-lp-flex-id="qa-bd2-flex" data-lp-flex-column="1" style="flex:1 1 0;min-height:80px;padding:12px;border:1px dashed #b8c2cc;">'
    .'<p>QA BD2 Flex Left Column</p>'
    .'</div>'
    .'<div class="editableDiv lp-flex-column" data-lp-flex-id="qa-bd2-flex" data-lp-flex-column="2" style="flex:1 1 0;min-height:80px;padding:12px;border:1px dashed #b8c2cc;">'
    .'<h2>QA BD2 Flex Right Heading</h2>'
    .'</div>'
    .'</div>'
    .'</div>';

$pageFlex = '<div class="editableDiv lp-flex-box-wrapper lp-structured-page-addition" data-lp-edit-context="page_addition" data-lp-flex-id="qa-page-flex" style="position: relative; width: 100%; margin: 16px 0; padding: 0;">'
    .'<style data-lp-flex-responsive-style="qa-page-flex">@media (max-width: 750px){.lp-flex-box[data-lp-flex-id="qa-page-flex"]{flex-direction:column!important}.lp-flex-box[data-lp-flex-id="qa-page-flex"]>.lp-flex-column{flex:1 1 100%!important;max-width:100%!important}}</style>'
    .'<div class="editableDiv lp-flex-box" data-lp-flex-id="qa-page-flex" data-lp-flex-columns="2" style="display:flex;gap:12px;width:100%;margin:0;">'
    .'<div class="editableDiv lp-flex-column" data-lp-flex-id="qa-page-flex" data-lp-flex-column="1" style="flex:1 1 0;min-height:70px;padding:12px;border:1px dashed #b8c2cc;">'
    .'<p class="editableDiv lp-structured-page-addition" data-lp-edit-context="page_addition">QA Page Flex Left Content</p>'
    .'</div>'
    .'<div class="editableDiv lp-flex-column" data-lp-flex-id="qa-page-flex" data-lp-flex-column="2" style="flex:1 1 0;min-height:70px;padding:12px;border:1px dashed #b8c2cc;">'
    .'<p class="editableDiv lp-structured-page-addition" data-lp-edit-context="page_addition">QA Page Flex Right Content</p>'
    .'</div>'
    .'</div>'
    .'</div>';

$bodies = [
    'BD1' => '<h1>QA Body 1 Heading</h1><p>QA Body 1 paragraph.</p>',
    'BD2' => $bdFlex,
    'BD3' => '<h1>QA Body 3-3</h1><p>QA publisher/body 3 text.</p>',
    'BD5' => '<h1>QA Body 5-5</h1>',
];

$sourceRendered = $service->renderStructuredBodies($sourceTheme, $bodies, $angle);
$sourceRendered['main_html'] = $service->appendStructuredPageLevelAdditions(
    $sourceRendered['main_html'],
    [$pageFlex]
);

$sourcePagePayload = [
    'uuid' => (string) Str::uuid(),
    'angle_id' => $angle->id,
    'template_id' => $sourceTheme->id,
    'user_id' => $user->id,
    'name' => "Codex Flex QA Source Page {$runId}",
    'main_html' => $sourceRendered['main_html'],
    'main_css' => $sourceRendered['main_css'],
    'main_js' => $sourceRendered['main_js'],
    'content_mode' => AngleTemplate::CONTENT_MODE_STRUCTURED_BD,
    'structured_version' => 1,
];
if (Schema::hasColumn('angle_templates', 'organization_id')) {
    $sourcePagePayload['organization_id'] = $user->currentOrganization()?->id;
}
$sourcePage = AngleTemplate::create($sourcePagePayload);

foreach ($bodies as $index => $content) {
    preg_match('/^(BD[1-5])/', $index, $matches);
    AngleTemplateBdContent::create([
        'angle_template_id' => $sourcePage->id,
        'angle_template_uuid' => $sourcePage->uuid,
        'parent_bd' => $matches[1] ?? $index,
        'slot_key' => $index,
        'slot_type' => 'html',
        'content' => $content,
        'sort' => count($sourcePage->bdContents) + 1,
    ]);
}

$pageLevelAdditions = $service->extractStructuredPageLevelAdditions($sourcePage->main_html);
$targetRendered = $service->renderStructuredBodies($targetTheme, $bodies, $angle);
$targetRendered['main_html'] = $service->appendStructuredPageLevelAdditions(
    $targetRendered['main_html'],
    $pageLevelAdditions
);

$targetPagePayload = [
    'uuid' => (string) Str::uuid(),
    'angle_id' => $angle->id,
    'template_id' => $targetTheme->id,
    'user_id' => $user->id,
    'name' => "Codex Flex QA Theme Switched Page {$runId}",
    'main_html' => $targetRendered['main_html'],
    'main_css' => $targetRendered['main_css'],
    'main_js' => $targetRendered['main_js'],
    'content_mode' => AngleTemplate::CONTENT_MODE_STRUCTURED_BD,
    'structured_version' => 1,
];
if (Schema::hasColumn('angle_templates', 'organization_id')) {
    $targetPagePayload['organization_id'] = $user->currentOrganization()?->id;
}
$targetPage = AngleTemplate::create($targetPagePayload);

foreach ($sourcePage->bdContents()->get() as $bdContent) {
    AngleTemplateBdContent::create([
        'angle_template_id' => $targetPage->id,
        'angle_template_uuid' => $targetPage->uuid,
        'parent_bd' => $bdContent->parent_bd,
        'slot_key' => $bdContent->slot_key,
        'slot_type' => $bdContent->slot_type,
        'content' => $bdContent->content,
        'sort' => $bdContent->sort,
        'metadata' => $bdContent->metadata,
    ]);
}

echo json_encode([
    'run_id' => $runId,
    'source_page_id' => $sourcePage->id,
    'target_page_id' => $targetPage->id,
    'source_url' => url("/angle-templates/preview/{$sourcePage->id}"),
    'target_url' => url("/angle-templates/preview/{$targetPage->id}"),
    'source_page_name' => $sourcePage->name,
    'target_page_name' => $targetPage->name,
    'page_level_additions_count' => count($pageLevelAdditions),
], JSON_PRETTY_PRINT).PHP_EOL;
