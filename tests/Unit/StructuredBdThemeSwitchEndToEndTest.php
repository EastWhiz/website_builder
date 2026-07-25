<?php

use App\Models\Angle;
use App\Models\AngleContent;
use App\Models\AngleTemplate;
use App\Models\AngleTemplateBdContent;
use App\Models\Template;
use App\Models\TemplateContent;
use App\Models\User;
use App\Services\AngleTemplateMergeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $enabled = env('RUN_STRUCTURED_BD_E2E_TESTS') === 'true'
        || getenv('RUN_STRUCTURED_BD_E2E_TESTS') === 'true'
        || ($_SERVER['RUN_STRUCTURED_BD_E2E_TESTS'] ?? null) === 'true';

    if (! $enabled) {
        $this->markTestSkipped(
            'Set RUN_STRUCTURED_BD_E2E_TESTS=true to run the structured BD end-to-end database test.'
        );
    }

    foreach ([
        'users',
        'angles',
        'templates',
        'template_contents',
        'angle_templates',
        'angle_template_bd_contents',
    ] as $table) {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Missing required table: {$table}");
        }
    }

    foreach ([
        ['angles', 'user_id'],
        ['templates', 'asset_unique_uuid'],
        ['angle_templates', 'uuid'],
        ['angle_templates', 'main_css'],
        ['angle_templates', 'main_js'],
        ['angle_templates', 'content_mode'],
        ['angle_templates', 'structured_version'],
    ] as [$table, $column]) {
        if (! Schema::hasColumn($table, $column)) {
            $this->markTestSkipped("Missing required column: {$table}.{$column}");
        }
    }
});

it('creates structured bd angle, themes, landing page, then switches theme using saved bd content', function () {
    $service = new AngleTemplateMergeService;
    $unique = (string) Str::uuid();

    $user = User::create([
        'name' => 'Structured BD E2E Test',
        'email' => "structured-bd-{$unique}@example.test",
        'phone' => '+10000000000',
        'password' => Hash::make('password'),
    ]);

    $angle = Angle::create([
        'user_id' => $user->id,
        'name' => "Structured BD E2E Angle {$unique}",
        'uuid' => (string) Str::uuid(),
        'asset_unique_uuid' => (string) Str::uuid(),
    ]);

    $sourceTheme = Template::create([
        'name' => "Structured BD E2E Source Theme {$unique}",
        'uuid' => (string) Str::uuid(),
        'asset_unique_uuid' => (string) Str::uuid(),
        'head' => '',
        'index' => '<main class="theme-a">'
            .'<!--INTERNAL--BD1--EXTERNAL-->'
            .'<!--INTERNAL--BD2--EXTERNAL-->'
            .'<!--INTERNAL--BD3--EXTERNAL-->'
            .'</main>',
    ]);

    $targetTheme = Template::create([
        'name' => "Structured BD E2E Target Theme {$unique}",
        'uuid' => (string) Str::uuid(),
        'asset_unique_uuid' => (string) Str::uuid(),
        'head' => '',
        'index' => '<article class="theme-b">'
            .'<!--INTERNAL--BD2_HEADER--EXTERNAL-->'
            .'<!--INTERNAL--BD3_PUBLISHER--EXTERNAL-->'
            .'<!--INTERNAL--BD2_BANNER--EXTERNAL-->'
            .'<!--INTERNAL--BD1--EXTERNAL-->'
            .'<!--INTERNAL--BD2--EXTERNAL-->'
            .'</article>',
    ]);

    TemplateContent::create([
        'template_uuid' => $targetTheme->uuid,
        'name' => 'structured-bd-e2e.css',
        'type' => 'css',
        'content' => '.structured-bd-e2e-target{color:#123456;}',
    ]);

    TemplateContent::create([
        'template_uuid' => $targetTheme->uuid,
        'name' => 'structured-bd-e2e.js',
        'type' => 'js',
        'content' => 'window.structuredBdE2e = true;',
    ]);

    $sourcePage = AngleTemplate::create([
        'uuid' => (string) Str::uuid(),
        'angle_id' => $angle->id,
        'template_id' => $sourceTheme->id,
        'user_id' => $user->id,
        'name' => "Structured BD E2E Page {$unique}",
        'main_html' => '',
        'main_css' => '',
        'main_js' => '',
        'content_mode' => AngleTemplate::CONTENT_MODE_STRUCTURED_BD,
        'structured_version' => 1,
    ]);

    foreach ([
        ['BD1', 'BD1', '<section>BD1 STRUCTURED ORIGINAL</section>', 1],
        ['BD2', 'BD2', '<section>BD2 FULL STRUCTURED</section>', 2],
        ['BD2', 'BD2_HEADER', '<h1>BD2 HEADER STRUCTURED</h1>', 3],
        ['BD2', 'BD2_BANNER', '<img src="angle_images/banner.jpg" alt="BD2 BANNER STRUCTURED">', 4],
        ['BD3', 'BD3', '<section>BD3 FULL STRUCTURED</section>', 5],
        ['BD3', 'BD3_PUBLISHER', '<p>BD3 PUBLISHER STRUCTURED</p>', 6],
    ] as [$parentBd, $slotKey, $content, $sort]) {
        AngleTemplateBdContent::create([
            'angle_template_id' => $sourcePage->id,
            'angle_template_uuid' => $sourcePage->uuid,
            'parent_bd' => $parentBd,
            'slot_key' => $slotKey,
            'slot_type' => 'html',
            'content' => $content,
            'sort' => $sort,
        ]);
    }

    $renderedSource = $service->renderStructuredBd($sourcePage->fresh(['angle', 'template', 'bdContents']));
    $sourcePage->update($renderedSource);

    $targetPage = createStructuredBdThemeSwitchCopy($sourcePage->fresh(['angle', 'bdContents']), $targetTheme, $service);

    expect($targetPage->id)->not->toBe($sourcePage->id)
        ->and($sourcePage->fresh()->template_id)->toBe($sourceTheme->id)
        ->and($targetPage->template_id)->toBe($targetTheme->id)
        ->and($targetPage->content_mode)->toBe(AngleTemplate::CONTENT_MODE_STRUCTURED_BD)
        ->and($targetPage->structured_version)->toBe(1)
        ->and($targetPage->bdContents()->count())->toBe($sourcePage->bdContents()->count())
        ->and($targetPage->main_html)->toContain('BD2 HEADER STRUCTURED')
        ->and($targetPage->main_html)->toContain('BD3 PUBLISHER STRUCTURED')
        ->and($targetPage->main_html)->toContain('BD2 BANNER STRUCTURED')
        ->and($targetPage->main_html)->toContain('BD1 STRUCTURED ORIGINAL')
        ->and($targetPage->main_html)->toContain('BD2 FULL STRUCTURED')
        ->and($targetPage->main_html)->not->toContain('<!--INTERNAL--')
        ->and($targetPage->main_css)->toContain('.structured-bd-e2e-target')
        ->and($targetPage->main_js)->toContain('window.structuredBdE2e = true;');
});

it('creates five base bd rows through the landing page create endpoint', function () {
    $unique = (string) Str::uuid();

    $user = User::create([
        'name' => 'Structured BD Create Test',
        'email' => "structured-bd-create-{$unique}@example.test",
        'phone' => '+10000000001',
        'password' => Hash::make('password'),
    ]);
    $user->forceFill(['role_id' => 1])->save();

    $angle = Angle::create([
        'user_id' => $user->id,
        'name' => "Structured BD Create Angle {$unique}",
        'uuid' => (string) Str::uuid(),
        'asset_unique_uuid' => (string) Str::uuid(),
    ]);

    foreach ([
        ['BD1', '<section>Endpoint BD1 content</section>', 1],
        ['BD2', '<section>Endpoint BD2 content</section>', 2],
        ['BD3', '<section>Endpoint BD3 content</section>', 3],
    ] as [$name, $content, $sort]) {
        AngleContent::create([
            'uuid' => (string) Str::uuid(),
            'angle_uuid' => $angle->uuid,
            'name' => $name,
            'type' => 'html',
            'content' => $content,
            'sort' => $sort,
            'can_be_deleted' => false,
        ]);
    }

    $theme = Template::create([
        'name' => "Structured BD Create Theme {$unique}",
        'uuid' => (string) Str::uuid(),
        'asset_unique_uuid' => (string) Str::uuid(),
        'head' => '',
        'index' => '<main>'
            .'<!--INTERNAL--BD1--EXTERNAL-->'
            .'<!--INTERNAL--BD2--EXTERNAL-->'
            .'<!--INTERNAL--BD3--EXTERNAL-->'
            .'<!--INTERNAL--BD4--EXTERNAL-->'
            .'<!--INTERNAL--BD5--EXTERNAL-->'
            .'</main>',
    ]);

    $response = $this->actingAs($user)->postJson(route('landing-pages.create-from-angle-template'), [
        'angle_id' => $angle->id,
        'template_id' => $theme->id,
        'content_mode' => AngleTemplate::CONTENT_MODE_STRUCTURED_BD,
        'bd_contents' => [
            'BD1' => '<section>Modal BD1 override content</section>',
            'BD2' => '',
            'BD3' => '',
            'BD4' => '',
            'BD5' => '',
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.content_mode', AngleTemplate::CONTENT_MODE_STRUCTURED_BD);

    $pageId = $response->json('data.angle_template_id');
    $page = AngleTemplate::with('bdContents')->findOrFail($pageId);

    expect($page->isStructuredBd())->toBeTrue()
        ->and($page->bdContents->pluck('slot_key')->sort()->values()->all())->toBe([
            'BD1',
            'BD2',
            'BD3',
            'BD4',
            'BD5',
        ])
        ->and($page->bdContents->firstWhere('slot_key', 'BD1')->content)->toContain('Modal BD1 override content')
        ->and($page->bdContents->firstWhere('slot_key', 'BD4')->content)->toBe('')
        ->and($page->main_html)->toContain('Modal BD1 override content')
        ->and($page->main_html)->not->toContain('<!--INTERNAL--');
});

it('rejects structured landing page creation when submitted bd fields are empty', function () {
    $unique = (string) Str::uuid();

    $user = User::create([
        'name' => 'Structured BD Empty Create Test',
        'email' => "structured-bd-empty-create-{$unique}@example.test",
        'phone' => '+10000000002',
        'password' => Hash::make('password'),
    ]);
    $user->forceFill(['role_id' => 1])->save();

    $angle = Angle::create([
        'user_id' => $user->id,
        'name' => "Structured BD Empty Create Angle {$unique}",
        'uuid' => (string) Str::uuid(),
        'asset_unique_uuid' => (string) Str::uuid(),
    ]);

    AngleContent::create([
        'uuid' => (string) Str::uuid(),
        'angle_uuid' => $angle->uuid,
        'name' => 'BD1',
        'type' => 'html',
        'content' => '<section>Existing angle content should not bypass empty modal data</section>',
        'sort' => 1,
        'can_be_deleted' => false,
    ]);

    $theme = Template::create([
        'name' => "Structured BD Empty Create Theme {$unique}",
        'uuid' => (string) Str::uuid(),
        'asset_unique_uuid' => (string) Str::uuid(),
        'head' => '',
        'index' => '<main><!--INTERNAL--BD1--EXTERNAL--></main>',
    ]);

    $response = $this->actingAs($user)->postJson(route('landing-pages.create-from-angle-template'), [
        'angle_id' => $angle->id,
        'template_id' => $theme->id,
        'content_mode' => AngleTemplate::CONTENT_MODE_STRUCTURED_BD,
        'bd_contents' => [
            'BD1' => '',
            'BD2' => '',
            'BD3' => '',
            'BD4' => '',
            'BD5' => '',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Please provide content for at least one BD field.');
});

function createStructuredBdThemeSwitchCopy(
    AngleTemplate $sourcePage,
    Template $targetTheme,
    AngleTemplateMergeService $service
): AngleTemplate {
    $bodies = $sourcePage->bdContents
        ->pluck('content', 'slot_key')
        ->map(fn ($content) => (string) $content)
        ->all();

    $renderedTarget = $service->renderStructuredBodies($targetTheme, $bodies, $sourcePage->angle);

    $targetPage = AngleTemplate::create([
        'uuid' => (string) Str::uuid(),
        'angle_id' => $sourcePage->angle_id,
        'template_id' => $targetTheme->id,
        'user_id' => $sourcePage->user_id,
        'organization_id' => $sourcePage->organization_id,
        'name' => $sourcePage->name.' (Theme Switch Copy)',
        'main_html' => $renderedTarget['main_html'],
        'main_css' => $renderedTarget['main_css'],
        'main_js' => $renderedTarget['main_js'],
        'content_mode' => AngleTemplate::CONTENT_MODE_STRUCTURED_BD,
        'structured_version' => $sourcePage->structured_version ?: 1,
    ]);

    foreach ($sourcePage->bdContents as $bdContent) {
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

    return $targetPage->fresh(['bdContents']);
}
