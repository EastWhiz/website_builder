<?php

declare(strict_types=1);

use App\Models\AngleTemplate;
use App\Services\AngleTemplateMergeService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$outputPath = $argv[1] ?? (__DIR__.'/../storage/app/landing-page-bd-compatibility-audit.csv');
$outputDir = dirname($outputPath);

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$handle = fopen($outputPath, 'wb');
if ($handle === false) {
    fwrite(STDERR, "Could not open output file: {$outputPath}".PHP_EOL);
    exit(1);
}

fputcsv($handle, [
    'landing_page_id',
    'landing_page_name',
    'theme_id',
    'theme_name',
    'mapping_status',
    'source_repeated_bds',
    'error',
]);

$service = app(AngleTemplateMergeService::class);
$summary = [
    'bd_mapped' => 0,
    'safe_fallback' => 0,
    'missing_relationships' => 0,
    'errors' => 0,
];

AngleTemplate::query()
    ->with(['angle', 'template'])
    ->orderBy('id')
    ->chunkById(100, function ($landingPages) use ($handle, $service, &$summary): void {
        foreach ($landingPages as $landingPage) {
            if (! $landingPage->angle || ! $landingPage->template) {
                $summary['missing_relationships']++;

                fputcsv($handle, [
                    $landingPage->id,
                    (string) $landingPage->name,
                    $landingPage->template_id,
                    '',
                    'missing_relationships',
                    '',
                    '',
                ]);

                continue;
            }

            try {
                $result = $service->changeThemePreservingContent(
                    $landingPage->angle,
                    (string) $landingPage->main_html,
                    $landingPage->template,
                    $landingPage->template
                );

                $summary[$result['mapping_status']]++;

                fputcsv($handle, [
                    $landingPage->id,
                    (string) $landingPage->name,
                    $landingPage->template->id,
                    (string) $landingPage->template->name,
                    $result['mapping_status'],
                    json_encode($result['source_repeated_bds'], JSON_UNESCAPED_SLASHES),
                    '',
                ]);
            } catch (Throwable $e) {
                $summary['errors']++;

                fputcsv($handle, [
                    $landingPage->id,
                    (string) $landingPage->name,
                    $landingPage->template->id,
                    (string) $landingPage->template->name,
                    'error',
                    '',
                    $e->getMessage(),
                ]);
            }
        }
    });

fclose($handle);

echo 'Landing page BD compatibility audit complete.'.PHP_EOL;
echo 'Output: '.realpath($outputPath).PHP_EOL;

foreach ($summary as $status => $count) {
    echo $status.': '.$count.PHP_EOL;
}
