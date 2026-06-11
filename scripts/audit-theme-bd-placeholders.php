<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$outputPath = $argv[1] ?? (__DIR__ . '/../storage/app/theme-bd-placeholder-audit.csv');
$outputDir = dirname($outputPath);

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$handle = fopen($outputPath, 'wb');
if ($handle === false) {
    fwrite(STDERR, "Could not open output file: {$outputPath}" . PHP_EOL);
    exit(1);
}

fputcsv($handle, [
    'id',
    'name',
    'uuid',
    'sequence',
    'unique',
    'repeated',
    'classification',
    'placeholder_count',
]);

$summary = [
    'simple' => 0,
    'reordered' => 0,
    'repeated' => 0,
    'no placeholders' => 0,
];

$templates = App\Models\Template::query()
    ->select('id', 'name', 'uuid', 'index')
    ->orderBy('id')
    ->get();

foreach ($templates as $template) {
    preg_match_all('/<!--INTERNAL--BD(\d+)--EXTERNAL-->/', (string) $template->index, $matches);

    $sequence = $matches[1] ?? [];
    $counts = array_count_values($sequence);
    $repeated = [];

    foreach ($counts as $bd => $count) {
        if ($count > 1) {
            $repeated[] = 'BD' . $bd . 'x' . $count;
        }
    }

    $uniqueInOrder = [];
    foreach ($sequence as $bd) {
        if (!in_array($bd, $uniqueInOrder, true)) {
            $uniqueInOrder[] = $bd;
        }
    }

    $numeric = array_map('intval', $uniqueInOrder);
    $sorted = $numeric;
    sort($sorted);

    $classification = 'simple';
    if ($sequence === []) {
        $classification = 'no placeholders';
    } elseif ($repeated !== []) {
        $classification = 'repeated';
    } elseif ($numeric !== $sorted) {
        $classification = 'reordered';
    }

    $summary[$classification] = ($summary[$classification] ?? 0) + 1;

    fputcsv($handle, [
        $template->id,
        (string) $template->name,
        (string) $template->uuid,
        implode(' -> ', array_map(fn (string $bd): string => 'BD' . $bd, $sequence)),
        implode(', ', array_map(fn (string $bd): string => 'BD' . $bd, $uniqueInOrder)),
        implode(', ', $repeated),
        $classification,
        count($sequence),
    ]);
}

fclose($handle);

echo 'Theme BD placeholder audit complete.' . PHP_EOL;
echo 'Output: ' . realpath($outputPath) . PHP_EOL;
echo 'Total themes: ' . $templates->count() . PHP_EOL;

foreach ($summary as $classification => $count) {
    echo ucfirst($classification) . ': ' . $count . PHP_EOL;
}
