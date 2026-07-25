<?php

require __DIR__.'/../vendor/autoload.php';

use App\Models\Template;
use App\Services\AngleTemplateMergeService;

$service = new AngleTemplateMergeService();
$themeFiles = [
    'standard_bd' => __DIR__.'/../docs/phase-8-pilot-theme-a-standard-bd.html',
    'sub_slots' => __DIR__.'/../docs/phase-8-pilot-theme-b-sub-slots.html',
];

$sampleBodies = [
    'BD1' => '<h1>BD1 PILOT CONTENT</h1>',
    'BD2' => '<p>BD2 FULL STORY CONTENT</p>',
    'BD2_HEADER' => '<h1>BD2 HEADER CONTENT</h1>',
    'BD2_BANNER' => '<figure>BD2 BANNER CONTENT</figure>',
    'BD3' => '<p>BD3 PUBLISHER CONTENT</p>',
    'BD3_PUBLISHER' => '<p>BD3 PUBLISHER SUB SLOT CONTENT</p>',
    'BD4' => '<p>BD4 SUPPORTING CONTENT</p>',
    'BD4_CTA' => '<button>BD4 CTA CONTENT</button>',
    'BD5' => '<small>BD5 FOOTER CONTENT</small>',
];

$failures = [];

foreach ($themeFiles as $name => $path) {
    if (! is_file($path)) {
        $failures[] = "{$name}: missing theme file {$path}";
        continue;
    }

    $html = file_get_contents($path);
    preg_match_all('/<!--INTERNAL--(?<id>BD\d+(?:_[A-Z][A-Z0-9_]*)?)--EXTERNAL-->/', $html, $matches);
    $placeholders = array_values(array_unique($matches['id'] ?? []));

    $template = new class([
        'uuid' => 'phase-8-'.$name,
        'asset_unique_uuid' => 'asset-'.$name,
        'index' => $html,
    ]) extends Template {
        public function contents()
        {
            return new class {
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

    $rendered = $service->renderStructuredBodies($template, $sampleBodies);

    foreach ($placeholders as $placeholder) {
        if (str_contains($rendered['main_html'], "<!--INTERNAL--{$placeholder}--EXTERNAL-->")) {
            $failures[] = "{$name}: placeholder {$placeholder} was not replaced";
        }
    }

    foreach ($placeholders as $placeholder) {
        $expectedText = strip_tags($sampleBodies[$placeholder] ?? '');
        if ($expectedText !== '' && ! str_contains($rendered['main_html'], $expectedText)) {
            $failures[] = "{$name}: expected content for {$placeholder} not found";
        }
    }

    echo "{$name}: ok, placeholders=".implode(',', $placeholders).PHP_EOL;
}

if ($failures !== []) {
    echo PHP_EOL.'Failures:'.PHP_EOL;
    foreach ($failures as $failure) {
        echo '- '.$failure.PHP_EOL;
    }
    exit(1);
}

echo PHP_EOL.'Phase 8 pilot theme validation passed.'.PHP_EOL;
