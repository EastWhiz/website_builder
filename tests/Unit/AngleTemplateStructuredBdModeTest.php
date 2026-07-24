<?php

use App\Models\AngleTemplate;

it('treats existing landing pages without a content mode as legacy', function () {
    $angleTemplate = new AngleTemplate;

    expect($angleTemplate->isLegacyContent())->toBeTrue()
        ->and($angleTemplate->isStructuredBd())->toBeFalse();
});

it('detects structured bd landing pages explicitly', function () {
    $angleTemplate = new AngleTemplate([
        'content_mode' => AngleTemplate::CONTENT_MODE_STRUCTURED_BD,
        'structured_version' => 1,
    ]);

    expect($angleTemplate->isStructuredBd())->toBeTrue()
        ->and($angleTemplate->isLegacyContent())->toBeFalse()
        ->and($angleTemplate->structured_version)->toBe(1);
});
