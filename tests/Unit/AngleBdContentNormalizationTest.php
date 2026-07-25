<?php

use App\Http\Controllers\AngleController;

function normalizeAngleHtmlForTest(array $html): array
{
    $controller = app(AngleController::class);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('normalizeAngleHtmlContent');
    $method->setAccessible(true);

    return $method->invoke($controller, $html);
}

it('creates the five base bd slots for a new empty angle', function () {
    $normalized = normalizeAngleHtmlForTest([]);

    expect(array_column($normalized, 'name'))->toBe(['BD1', 'BD2', 'BD3', 'BD4', 'BD5'])
        ->and(array_column($normalized, 'content'))->toBe(['', '', '', '', '']);
});

it('maps legacy unnamed chunks into bd1 to bd5 while preserving extra chunks', function () {
    $normalized = normalizeAngleHtmlForTest([
        ['name' => '<body>', 'content' => '<p>First body</p>'],
        ['name' => '<body2>', 'content' => '<p>Second body</p>'],
        ['name' => '<body6>', 'content' => '<p>Sixth body</p>'],
    ]);

    expect($normalized[0])->toMatchArray(['name' => 'BD1', 'content' => '<p>First body</p>'])
        ->and($normalized[1])->toMatchArray(['name' => 'BD2', 'content' => '<p>Second body</p>'])
        ->and($normalized[2])->toMatchArray(['name' => 'BD3', 'content' => '<p>Sixth body</p>'])
        ->and(array_column($normalized, 'name'))->toContain('BD4', 'BD5');
});

it('keeps explicit bd slots and optional sub slots', function () {
    $normalized = normalizeAngleHtmlForTest([
        ['name' => 'BD2_HEADER:', 'content' => '<h1>Headline</h1>'],
        ['name' => 'BD1', 'content' => '<article>Story</article>'],
    ]);

    expect($normalized[0])->toMatchArray(['name' => 'BD1', 'content' => '<article>Story</article>'])
        ->and(array_column($normalized, 'name'))->toBe([
            'BD1',
            'BD2',
            'BD3',
            'BD4',
            'BD5',
            'BD2_HEADER',
        ]);
});
