<?php

use App\Models\Template;
use App\Services\AngleTemplateMergeService;

beforeEach(function () {
    $this->service = new AngleTemplateMergeService;
});

it('extracts body content keyed by bd identifier instead of position', function () {
    $shell = '<header>H</header>'
        .'<!--INTERNAL--BD2--EXTERNAL-->'
        .'<aside>A</aside>'
        .'<!--INTERNAL--BD3--EXTERNAL-->'
        .'<main>M</main>'
        .'<!--INTERNAL--BD1--EXTERNAL-->'
        .'<footer>F</footer>';

    $merged = '<header>H</header>'
        .'<h2>Heading</h2>'
        .'<aside>A</aside>'
        .'<p>Publisher</p>'
        .'<main>M</main>'
        .'<article>Article</article>'
        .'<footer>F</footer>';

    expect($this->service->extractBodySegmentsFromMergedHtml($shell, $merged))->toBe([
        'BD2' => '<h2>Heading</h2>',
        'BD3' => '<p>Publisher</p>',
        'BD1' => '<article>Article</article>',
    ]);
});

it('extracts body content when an editor decodes static html entities', function () {
    $shell = '<header><button>&times;</button></header>'
        .'<!--INTERNAL--BD1--EXTERNAL-->'
        .'<footer>F</footer>';
    $merged = '<header><button>×</button></header>'
        .'<article>Article</article>'
        .'<footer>F</footer>';

    $decodedShell = html_entity_decode($shell, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($this->service->extractBodySegmentsFromMergedHtml($decodedShell, $merged))->toBe([
        'BD1' => '<article>Article</article>',
    ]);
});

it('collapses repeated bd placeholders when their content is identical', function () {
    $shell = '<a>A</a>'
        .'<!--INTERNAL--BD2--EXTERNAL-->'
        .'<b>B</b>'
        .'<!--INTERNAL--BD3--EXTERNAL-->'
        .'<c>C</c>'
        .'<!--INTERNAL--BD2--EXTERNAL-->'
        .'<d>D</d>';

    $merged = '<a>A</a>'
        .'<section>Shared BD2</section>'
        .'<b>B</b>'
        .'<section>BD3</section>'
        .'<c>C</c>'
        .'<section>Shared BD2</section>'
        .'<d>D</d>';

    expect($this->service->extractBodySegmentsFromMergedHtml($shell, $merged))->toBe([
        'BD2' => '<section>Shared BD2</section>',
        'BD3' => '<section>BD3</section>',
    ]);
});

it('rejects repeated bd placeholders when their extracted content differs', function () {
    $shell = '<a>A</a>'
        .'<!--INTERNAL--BD2--EXTERNAL-->'
        .'<b>B</b>'
        .'<!--INTERNAL--BD2--EXTERNAL-->'
        .'<c>C</c>';

    $merged = '<a>A</a>'
        .'<section>BD2 heading only</section>'
        .'<b>B</b>'
        .'<section>BD2 banner only</section>'
        .'<c>C</c>';

    expect($this->service->extractBodySegmentsFromMergedHtml($shell, $merged))->toBeNull();
});

it('injects bodies by bd identifier into reordered and repeated placeholders', function () {
    $shell = '<!--INTERNAL--BD2--EXTERNAL-->'
        .'<hr>'
        .'<!--INTERNAL--BD1--EXTERNAL-->'
        .'<hr>'
        .'<!--INTERNAL--BD2--EXTERNAL-->'
        .'<!--INTERNAL--BD4--EXTERNAL-->';

    $result = $this->service->injectBodySegmentsIntoShell($shell, [
        'BD1' => '<article>Article</article>',
        'BD2' => '<h2>Heading</h2>',
    ]);

    expect($result)->toBe(
        '<h2>Heading</h2><hr><article>Article</article><hr><h2>Heading</h2>'
    );
});

it('injects explicitly named sub slots without changing plain bd placeholders', function () {
    $shell = '<!--INTERNAL--BD2_HEADER--EXTERNAL-->'
        .'<aside>Publisher</aside>'
        .'<!--INTERNAL--BD2_BANNER--EXTERNAL-->'
        .'<!--INTERNAL--BD1--EXTERNAL-->';

    $result = $this->service->injectBodySegmentsIntoShell($shell, [
        'BD1' => '<article>Article</article>',
        'BD2_HEADER' => '<h1>Heading</h1>',
        'BD2_BANNER' => '<img src="banner.jpg">',
    ]);

    expect($result)->toBe(
        '<h1>Heading</h1><aside>Publisher</aside><img src="banner.jpg"><article>Article</article>'
    );
});

it('can preserve unmatched placeholders for backward-compatible page creation', function () {
    $shell = '<!--INTERNAL--BD1--EXTERNAL--><!--INTERNAL--BD2_HEADER--EXTERNAL-->';

    expect($this->service->injectBodySegmentsIntoShell(
        $shell,
        ['BD1' => '<article>Article</article>'],
        false
    ))->toBe('<article>Article</article><!--INTERNAL--BD2_HEADER--EXTERNAL-->');
});

it('maps explicitly named angle bodies and sub slots by identifier', function () {
    $bodies = [
        (object) ['name' => 'BD2', 'content' => '<h1>Heading</h1>'],
        (object) ['name' => 'BD2_HEADER', 'content' => '<h1>Split heading</h1>'],
        (object) ['name' => 'BD1', 'content' => '<article>Article</article>'],
    ];

    expect($this->service->mapAngleBodiesByIdentifier($bodies))->toBe([
        'BD2' => '<h1>Heading</h1>',
        'BD2_HEADER' => '<h1>Split heading</h1>',
        'BD1' => '<article>Article</article>',
    ]);
});

it('keeps positional compatibility for legacy unnamed angle bodies', function () {
    $bodies = [
        (object) ['name' => '<body>', 'content' => '<article>Article</article>'],
        (object) ['name' => '<body2>', 'content' => '<h1>Heading</h1>'],
    ];

    expect($this->service->mapAngleBodiesByIdentifier($bodies))->toBe([
        'BD1' => '<article>Article</article>',
        'BD2' => '<h1>Heading</h1>',
    ]);
});

it('preserves the original position of legacy bodies mixed with named bodies', function () {
    $bodies = [
        (object) ['name' => 'BD1', 'content' => '<article>Article</article>'],
        (object) ['name' => '<body2>', 'content' => '<h1>Legacy heading</h1>'],
        (object) ['name' => 'BD3', 'content' => '<p>Publisher</p>'],
    ];

    expect($this->service->mapAngleBodiesByIdentifier($bodies))->toBe([
        'BD1' => '<article>Article</article>',
        'BD3' => '<p>Publisher</p>',
        'BD2' => '<h1>Legacy heading</h1>',
    ]);
});

it('returns null when a theme shell has no bd placeholders', function () {
    expect($this->service->extractBodySegmentsFromMergedHtml(
        '<main>No placeholders</main>',
        '<main>No placeholders</main>'
    ))->toBeNull();
});

it('reports repeated bd placeholder usage', function () {
    $usage = $this->service->placeholderUsage(
        '<!--INTERNAL--BD2--EXTERNAL-->'
        .'<!--INTERNAL--BD3--EXTERNAL-->'
        .'<!--INTERNAL--BD2--EXTERNAL-->'
        .'<!--INTERNAL--BD3--EXTERNAL-->'
        .'<!--INTERNAL--BD3--EXTERNAL-->'
    );

    expect($usage)->toBe([
        'sequence' => ['BD2', 'BD3', 'BD2', 'BD3', 'BD3'],
        'counts' => ['BD2' => 2, 'BD3' => 3],
        'repeated' => ['BD2' => 2, 'BD3' => 3],
    ]);
});

it('reports unique optional sub slots requested by a theme', function () {
    expect($this->service->subSlotIds(
        '<!--INTERNAL--BD2_HEADER--EXTERNAL-->'
        .'<!--INTERNAL--BD1--EXTERNAL-->'
        .'<!--INTERNAL--BD2_BANNER--EXTERNAL-->'
        .'<!--INTERNAL--BD2_HEADER--EXTERNAL-->'
    ))->toBe([
        'BD2_HEADER',
        'BD2_BANNER',
    ]);
});

it('extracts unique public storage asset paths from preserved content', function () {
    $paths = $this->service->publicStorageAssetPaths(
        '<img src="../../storage/angles/a/images/one.jpg">'
        .'<img src="/storage/angleContents/b/images/two.png">'
        .'<video poster="storage/angleTemplates/c/images/three.jpg"></video>'
        .'<img src="../../storage/angles/a/images/one.jpg">'
        .'<img src="https://example.com/external.jpg">'
    );

    expect($paths)->toBe([
        'angles/a/images/one.jpg',
        'angleContents/b/images/two.png',
        'angleTemplates/c/images/three.jpg',
    ]);
});

it('removes old theme media without removing article assets', function () {
    $template = new Template([
        'uuid' => 'old-theme-uuid',
    ]);

    $html = '<img src="../../storage/templates/old-theme-uuid/images/right-ad.png">'
        .'<img src="../../storage/angles/angle-uuid/images/article.jpg">'
        .'<source src="/storage/templates/old-theme-uuid/images/decor.webp">'
        .'<p>Article text</p>';

    expect($this->service->removeTemplateAssetElements($html, $template))->toBe(
        '<img src="../../storage/angles/angle-uuid/images/article.jpg"><p>Article text</p>'
    );
});
