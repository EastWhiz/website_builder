<?php

use App\Services\LandingPageAssetService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->assetService = app(LandingPageAssetService::class);
});

it('discovers and rewrites every supported local storage URL variant', function () {
    $html = <<<'HTML'
<img src="../../storage/angles/angle-1/images/hero.jpg">
<img src="/storage/angleTemplates/page-1/images/root-relative.png">
<img src="https://builder.example.com/storage/angleContents/content-1/images/absolute.webp?version=2">
<img src="../../storage/angleTemplates/page-1/images/extensionless-asset">
HTML;
    $css = <<<'CSS'
.hero { background-image: url("../../storage/templates/theme-1/images/background image.png"); }
CSS;

    $paths = $this->assetService->collectStoragePaths([$html, $css]);

    expect($paths)->toContain(
        'angles/angle-1/images/hero.jpg',
        'angleTemplates/page-1/images/root-relative.png',
        'angleContents/content-1/images/absolute.webp',
        'templates/theme-1/images/background image.png',
        'angleTemplates/page-1/images/extensionless-asset',
    );

    $replacements = collect($paths)
        ->mapWithKeys(fn (string $path) => [$path => 'assets/'.$path])
        ->all();

    $rewritten = $this->assetService->rewriteStorageReferences($html.$css, $replacements);

    expect($rewritten)
        ->toContain('assets/angles/angle-1/images/hero.jpg')
        ->toContain('assets/angleTemplates/page-1/images/root-relative.png')
        ->toContain('assets/angleContents/content-1/images/absolute.webp')
        ->toContain('assets/templates/theme-1/images/background image.png')
        ->toContain('assets/angleTemplates/page-1/images/extensionless-asset')
        ->not->toContain('/storage/');
});

it('makes a cloned page independent by copying all referenced local assets', function () {
    Storage::disk('public')->put('angles/angle-1/images/article.jpg', 'article');
    Storage::disk('public')->put('angleTemplates/source/images/custom.png', 'custom');

    $html = '<img src="/storage/angles/angle-1/images/article.jpg">'
        .'<img src="https://builder.example.com/storage/angleTemplates/source/images/custom.png">';

    $result = $this->assetService->copyAssetsForClone('clone-1', [$html]);
    $rewritten = $this->assetService->rewriteStorageReferences($html, $result['replacements']);

    $articleClonePath = str_replace('../../storage/', '', $result['replacements']['angles/angle-1/images/article.jpg']);
    $customClonePath = str_replace('../../storage/', '', $result['replacements']['angleTemplates/source/images/custom.png']);

    Storage::disk('public')->assertExists($articleClonePath);
    Storage::disk('public')->assertExists($customClonePath);

    expect($articleClonePath)->toStartWith('angleTemplates/clone-1/assets/')
        ->and($customClonePath)->toStartWith('angleTemplates/clone-1/assets/')
        ->and($rewritten)
        ->toContain($result['replacements']['angles/angle-1/images/article.jpg'])
        ->toContain($result['replacements']['angleTemplates/source/images/custom.png'])
        ->not->toContain('https://builder.example.com');
});

it('copies available assets and preserves unavailable legacy references', function () {
    Storage::disk('public')->put('angleTemplates/source/images/available.jpg', 'available');

    $html = '<img src="/storage/angleTemplates/source/images/available.jpg">'
        .'<img src="/storage/angles/missing/images/lost.jpg">';

    $result = $this->assetService->copyAssetsForClone(
        'clone-1',
        [$html]
    );
    $rewritten = $this->assetService->rewriteStorageReferences($html, $result['replacements']);

    expect($result['missing'])->toBe(['angles/missing/images/lost.jpg'])
        ->and($rewritten)
        ->toContain('../../storage/angleTemplates/clone-1/assets/')
        ->toContain('/storage/angles/missing/images/lost.jpg');
});

it('prepares every referenced and registered asset for export', function () {
    Storage::disk('public')->put('angles/angle-1/images/article.jpg', 'article');
    Storage::disk('public')->put('templates/theme-1/fonts/site.woff2', 'font');
    Storage::disk('public')->put('angleTemplates/page-1/images/registered.png', 'registered');

    $result = $this->assetService->prepareAssetsForExport(
        [
            '<img src="/storage/angles/angle-1/images/article.jpg">',
            '@font-face { src: url("../../storage/templates/theme-1/fonts/site.woff2"); }',
        ],
        ['/storage/angleTemplates/page-1/images/registered.png']
    );

    expect($result['missing'])->toBeEmpty()
        ->and($result['paths'])->toContain(
            'angles/angle-1/images/article.jpg',
            'templates/theme-1/fonts/site.woff2',
            'angleTemplates/page-1/images/registered.png',
        )
        ->and($result['replacements']['angles/angle-1/images/article.jpg'])
        ->toBe('assets/angles/angle-1/images/article.jpg');
});
