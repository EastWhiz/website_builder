<?php

use App\Services\CloudflareTurnstileService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->service = app(CloudflareTurnstileService::class);
});

it('normalizes urls and domains to hostnames', function () {
    expect($this->service->normalizeHostname('https://Example.com/campaign/page?cid=abc'))
        ->toBe('example.com')
        ->and($this->service->normalizeHostname('sub.example.com/folder'))
        ->toBe('sub.example.com')
        ->and($this->service->normalizeHostname('  HTTP://WWW.EXAMPLE.COM/  '))
        ->toBe('www.example.com')
        ->and($this->service->normalizeHostname(''))
        ->toBe('');
});

it('builds a widget payload with unique normalized domains', function () {
    $payload = $this->service->buildWidgetPayload(
        'Landing Pages',
        [
            'https://example.com/page-a',
            'example.com/page-b',
            'LP.example.com',
        ],
        'invisible'
    );

    expect($payload)->toBe([
        'name' => 'Landing Pages',
        'domains' => ['example.com', 'lp.example.com'],
        'mode' => 'invisible',
    ]);
});

it('uses managed mode when an invalid mode is provided', function () {
    $payload = $this->service->buildWidgetPayload('Widget', ['example.com'], 'bad-mode');

    expect($payload['mode'])->toBe('managed');
});

it('lists widgets through the Cloudflare API using bearer auth', function () {
    Http::fake([
        'https://api.cloudflare.com/client/v4/accounts/account-123/challenges/widgets' => Http::response([
            'success' => true,
            'result' => [],
        ]),
    ]);

    $result = $this->service->testConnection('account-123', 'token-123');

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe(200);

    Http::assertSent(fn ($request) =>
        $request->method() === 'GET'
        && $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account-123/challenges/widgets'
        && $request->hasHeader('Authorization', 'Bearer token-123')
    );
});

it('creates a widget with normalized domains', function () {
    Http::fake([
        'https://api.cloudflare.com/client/v4/accounts/account-123/challenges/widgets' => Http::response([
            'success' => true,
            'result' => [
                'sitekey' => 'site-key',
                'secret' => 'secret-key',
            ],
        ]),
    ]);

    $result = $this->service->createWidget(
        'account-123',
        'token-123',
        'Builder Landing Pages',
        ['https://example.com/path'],
        'managed'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['result']['sitekey'])->toBe('site-key');

    Http::assertSent(fn ($request) =>
        $request->method() === 'POST'
        && $request->data() === [
            'name' => 'Builder Landing Pages',
            'domains' => ['example.com'],
            'mode' => 'managed',
        ]
    );
});

it('returns a normalized failure response from Cloudflare errors', function () {
    Http::fake([
        'https://api.cloudflare.com/client/v4/accounts/account-123/challenges/widgets' => Http::response([
            'success' => false,
            'errors' => [
                ['message' => 'Invalid token'],
            ],
        ], 403),
    ]);

    $result = $this->service->testConnection('account-123', 'bad-token');

    expect($result)->toMatchArray([
        'success' => false,
        'message' => 'Invalid token',
        'status' => 403,
    ]);
});
