<?php

use App\Models\Angle;
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

it('normalizes decorated sub slot names before falling back to positional body mapping', function () {
    $bodies = [
        (object) ['name' => 'BD2_HEADER:', 'content' => '<h2>BODY TWO HEADER ACTIVE HERE</h2>'],
        (object) ['name' => '<body name="BD2_BANNER">', 'content' => '<h2>BODY TWO BANNER ACTIVE HERE</h2>'],
        (object) ['name' => 'BD3 Publisher', 'content' => '<h2>BODY THREE PUBLISHER ACTIVE HERE</h2>'],
        (object) ['name' => 'BD1', 'content' => '<h2>BODY ONE CONTENT ACTIVE HERE</h2>'],
    ];

    expect($this->service->mapAngleBodiesByIdentifier($bodies))->toBe([
        'BD2_HEADER' => '<h2>BODY TWO HEADER ACTIVE HERE</h2>',
        'BD2_BANNER' => '<h2>BODY TWO BANNER ACTIVE HERE</h2>',
        'BD3_PUBLISHER' => '<h2>BODY THREE PUBLISHER ACTIVE HERE</h2>',
        'BD1' => '<h2>BODY ONE CONTENT ACTIVE HERE</h2>',
    ]);
});

it('renders report sub slot bodies into their matching boxes instead of bd1', function () {
    $shell = '<div class="box-1"><!--INTERNAL--BD2_HEADER--EXTERNAL--></div>'
        .'<div class="box-2"><!--INTERNAL--BD3_PUBLISHER--EXTERNAL--></div>'
        .'<div class="box-3"><!--INTERNAL--BD2_BANNER--EXTERNAL--></div>'
        .'<div class="box-4"><!--INTERNAL--BD1--EXTERNAL--></div>';
    $bodies = $this->service->mapAngleBodiesByIdentifier([
        (object) ['name' => 'BD2_HEADER:', 'content' => '<h2>BODY TWO HEADER ACTIVE HERE</h2>'],
        (object) ['name' => 'BD2_BANNER:', 'content' => '<h2>BODY TWO BANNER ACTIVE HERE</h2>'],
        (object) ['name' => 'BD3_PUBLISHER:', 'content' => '<h2>BODY THREE PUBLISHER ACTIVE HERE</h2>'],
        (object) ['name' => 'BD1:', 'content' => '<h2>BODY ONE CONTENT ACTIVE HERE</h2>'],
    ]);

    $result = $this->service->injectBodySegmentsIntoShell($shell, $bodies);

    expect($result)
        ->toContain('<div class="box-1"><h2>BODY TWO HEADER ACTIVE HERE</h2></div>')
        ->toContain('<div class="box-2"><h2>BODY THREE PUBLISHER ACTIVE HERE</h2></div>')
        ->toContain('<div class="box-3"><h2>BODY TWO BANNER ACTIVE HERE</h2></div>')
        ->toContain('<div class="box-4"><h2>BODY ONE CONTENT ACTIVE HERE</h2></div>');
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

it('reports required sub slots missing from available body content', function () {
    expect($this->service->missingSubSlotIds(
        '<!--INTERNAL--BD2_HEADER--EXTERNAL-->'
        .'<!--INTERNAL--BD2_BANNER--EXTERNAL-->',
        ['BD2_HEADER' => '<h1>Heading</h1>']
    ))->toBe(['BD2_BANNER']);
});

it('creates a page without warning when optional theme sub slots are missing', function () {
    $angle = new class(['uuid' => 'angle-uuid', 'asset_unique_uuid' => 'asset-uuid']) extends Angle
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect([
                        (object) ['name' => 'BD1', 'content' => '<article>Article</article>'],
                    ]);
                }
            };
        }
    };
    $template = new class(['uuid' => 'theme-uuid', 'index' => '<!--INTERNAL--BD1--EXTERNAL--><!--INTERNAL--BD2_HEADER--EXTERNAL-->']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };

    $result = $this->service->merge($angle, $template);

    expect($result['main_html'])
        ->toBe('<article>Article</article>')
        ->not->toContain('BD2_HEADER');
});

it('renders structured bd content into target theme placeholders', function () {
    $template = new class([
        'uuid' => 'theme-uuid',
        'asset_unique_uuid' => 'asset-uuid',
        'index' => '<main>'
            .'<!--INTERNAL--BD2_HEADER--EXTERNAL-->'
            .'<!--INTERNAL--BD3--EXTERNAL-->'
            .'<!--INTERNAL--BD2_BANNER--EXTERNAL-->'
            .'<!--INTERNAL--BD2_HEADER--EXTERNAL-->'
            .'<!--INTERNAL--BD5--EXTERNAL-->'
            .'<img src="template_images/logo.png">'
            .'</main>',
    ]) extends Template
    {
        public function contents()
        {
            return new class
            {
                private string $type = '';

                public function where($column = null, $value = null)
                {
                    $this->type = (string) $value;

                    return $this;
                }

                public function get()
                {
                    return match ($this->type) {
                        'css' => collect([(object) ['content' => '@font-face{src:url("fonts/news.woff2")} .theme{color:red;}']]),
                        'js' => collect([(object) ['content' => 'console.log("theme");']]),
                        default => collect(),
                    };
                }
            };
        }
    };

    $result = $this->service->renderStructuredBodies($template, [
        'BD2_HEADER' => '<h1>Structured headline</h1>',
        'BD2_BANNER' => '<figure>Banner</figure>',
        'BD3' => '<p>Publisher</p>',
    ]);

    expect($result['main_html'])
        ->toContain('<h1>Structured headline</h1><p>Publisher</p><figure>Banner</figure><h1>Structured headline</h1>')
        ->toContain('src="../../storage/templates/theme-uuid/images/asset-uuid-logo.png"')
        ->not->toContain('BD5')
        ->and($result['main_css'])->toContain('../../storage/templates/theme-uuid/fonts/asset-uuid-news.woff2')
        ->and($result['main_js'])->toContain('console.log("theme");');
});

it('uses safe fallback instead of original angle content when switching into a missing sub slot', function () {
    $angle = new Angle(['uuid' => 'angle-uuid', 'asset_unique_uuid' => 'asset-uuid']);
    $oldTemplate = new class(['uuid' => 'old-theme', 'index' => '<header>H</header><!--INTERNAL--BD2--EXTERNAL--><footer>F</footer>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };
    $newTemplate = new class(['uuid' => 'new-theme', 'index' => '<main><!--INTERNAL--BD2_HEADER--EXTERNAL--></main>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };

    $result = $this->service->changeThemePreservingContent(
        $angle,
        '<header>H</header><h1>Current full BD2 content</h1><footer>F</footer>',
        $oldTemplate,
        $newTemplate
    );

    expect($result['mapping_status'])->toBe('safe_fallback')
        ->and($result['unresolved_sub_slots'])->toBe(['BD2_HEADER'])
        ->and($result['main_html'])->toContain('Current full BD2 content');
});

it('keeps source css and js when safe fallback preserves the current page html', function () {
    $angle = new Angle(['uuid' => 'angle-uuid', 'asset_unique_uuid' => 'asset-uuid']);
    $oldTemplate = new class(['uuid' => 'old-theme', 'index' => '<header>H</header><!--INTERNAL--BD2--EXTERNAL--><footer>F</footer>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };
    $newTemplate = new class(['uuid' => 'new-theme', 'index' => '<main><!--INTERNAL--BD2_HEADER--EXTERNAL--></main>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect([
                        (object) ['content' => '.target-theme-social-icon { font-size: 80px; }'],
                    ]);
                }
            };
        }
    };

    $result = $this->service->changeThemePreservingContent(
        $angle,
        '<header>H</header><h1>Current full BD2 content</h1><footer>F</footer>',
        $oldTemplate,
        $newTemplate,
        '.source-theme-social-icon { font-size: 16px; }',
        'window.sourceThemeReady = true;'
    );

    expect($result['mapping_status'])->toBe('safe_fallback')
        ->and($result['main_css'])->toContain('.source-theme-social-icon')
        ->and($result['main_css'])->not->toContain('.target-theme-social-icon')
        ->and($result['main_js'])->toBe('window.sourceThemeReady = true;');
});

it('uses safe fallback when split source content cannot reconstruct a required plain bd', function () {
    $angle = new Angle(['uuid' => 'angle-uuid', 'asset_unique_uuid' => 'asset-uuid']);
    $oldTemplate = new class(['uuid' => 'old-theme', 'index' => '<header><!--INTERNAL--BD2_HEADER--EXTERNAL--></header><figure><!--INTERNAL--BD2_BANNER--EXTERNAL--></figure>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };
    $newTemplate = new class(['uuid' => 'new-theme', 'index' => '<main><!--INTERNAL--BD2--EXTERNAL--></main>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };

    $result = $this->service->changeThemePreservingContent(
        $angle,
        '<header><h1>Heading</h1></header><figure><img src="banner.jpg"></figure>',
        $oldTemplate,
        $newTemplate
    );

    expect($result['mapping_status'])->toBe('safe_fallback')
        ->and($result['unresolved_body_ids'])->toBe(['BD2'])
        ->and($result['main_html'])->toContain('Heading')
        ->and($result['main_html'])->toContain('banner.jpg');
});

it('preserves edited landing page slot content over the original angle slot', function () {
    $angle = new class(['uuid' => 'angle-uuid', 'asset_unique_uuid' => 'asset-uuid']) extends Angle
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect([
                        (object) ['name' => 'BD2_HEADER', 'content' => '<h1>Original angle heading</h1>'],
                    ]);
                }
            };
        }
    };
    $oldTemplate = new class(['uuid' => 'old-theme', 'index' => '<header>H</header><!--INTERNAL--BD2_HEADER--EXTERNAL--><footer>F</footer>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };
    $newTemplate = new class(['uuid' => 'new-theme', 'index' => '<main><!--INTERNAL--BD2_HEADER--EXTERNAL--></main>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };

    $result = $this->service->changeThemePreservingContent(
        $angle,
        '<header>H</header><h1>Edited landing page heading</h1><footer>F</footer>',
        $oldTemplate,
        $newTemplate
    );

    expect($result['mapping_status'])->toBe('slot_mapped')
        ->and($result['main_html'])->toContain('Edited landing page heading')
        ->and($result['main_html'])->not->toContain('Original angle heading');
});

it('does not add layout wrapper css when bd mapping succeeds', function () {
    $angle = new Angle(['uuid' => 'angle-uuid', 'asset_unique_uuid' => 'asset-uuid']);
    $oldTemplate = new class(['uuid' => 'old-theme', 'index' => '<section class="old-left"><!--INTERNAL--BD1--EXTERNAL--></section><aside class="old-right"><!--INTERNAL--BD4--EXTERNAL--></aside>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };
    $newTemplate = new class(['uuid' => 'new-theme', 'index' => '<main class="main-content"><div class="main-con-left"><!--INTERNAL--BD1--EXTERNAL--></div><div class="main-con-right"><!--INTERNAL--BD4--EXTERNAL--></div></main>']) extends Template
    {
        public function contents()
        {
            return new class
            {
                public function where()
                {
                    return $this;
                }

                public function get()
                {
                    return collect();
                }
            };
        }
    };

    $result = $this->service->changeThemePreservingContent(
        $angle,
        '<section class="old-left"><article>Article body</article></section><aside class="old-right"><p>Sidebar body</p></aside>',
        $oldTemplate,
        $newTemplate
    );

    expect($result['mapping_status'])->toBe('bd_mapped')
        ->and($result['main_html'])->toBe('<main class="main-content"><div class="main-con-left"><article>Article body</article></div><div class="main-con-right"><p>Sidebar body</p></div></main>')
        ->and($result['main_html'])->not->toContain('lp-theme-body-inner')
        ->and($result['main_css'])->not->toContain('lp-theme-body-inner');
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
