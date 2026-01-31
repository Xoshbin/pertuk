<?php

it('renders documentation from a specific version', function () {
    // Create test documents in different versions
    $this->createTestMarkdownFile('test.md', "# Version 1.0\nContent for v1.0", '', 'en', 'v1.0');
    $this->createTestMarkdownFile('test.md', "# Version 0.9\nContent for v0.9", '', 'en', 'v0.9');

    // Test v1.0
    $response = $this->get('/docs/v1.0/en/test');
    $response->assertOk();
    $response->assertSee('Version 1.0');

    // Test v0.9
    $response = $this->get('/docs/v0.9/en/test');
    $response->assertOk();
    $response->assertSee('Version 0.9');
});

it('falls back to default version if no version is provided in URL', function () {
    $this->createTestMarkdownFile('test.md', "# Version 1.0\nContent for v1.0", '', 'en', 'v1.0');

    $response = $this->get('/docs/en/test');
    $response->assertOk();
    $response->assertSee('Version 1.0');
});

it('handles version switching in the UI', function () {
    $this->createTestMarkdownFile('test.md', '# Version 1.0', '', 'en', 'v1.0');
    $this->createTestMarkdownFile('test.md', '# Version 0.9', '', 'en', 'v0.9');

    $response = $this->get('/docs/v1.0/en/test');
    $response->assertOk();

    // Check if version picker has both versions and the v0.9 option points to the correct URL
    $response->assertSee('v1.0');
    $response->assertSee('v0.9');
    $response->assertSee('docs/v0.9/en/test');
});

it('preserves version when switching language', function () {
    config(['pertuk.supported_locales' => ['en', 'ar']]);

    $this->createTestMarkdownFile('test.md', '# Version 1.0 EN', '', 'en', 'v1.0');
    $this->createTestMarkdownFile('test.md', '# Version 1.0 AR', '', 'ar', 'v1.0');

    $url = url('/docs/v1.0/en/test');
    $response = $this->get($url);
    $response->assertOk();

    // Trigger locale switch via LocaleController with referer
    $response = $this->withHeader('referer', $url)->get('/locale/ar');
    $response->assertRedirect();

    // Check if redirect URL contains the version
    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toContain('/docs/v1.0/ar/test');
});

it('discovers available versions from directory structure', function () {
    // Create directories for multiple versions
    $this->createTestMarkdownFile('test.md', '# v2.0', '', 'en', 'v2.0');
    $this->createTestMarkdownFile('test.md', '# v1.0', '', 'en', 'v1.0');

    $versions = \Xoshbin\Pertuk\Services\DocumentationService::getAvailableVersions();

    expect($versions)->toBe(['v2.0', 'v1.0']);
});

it('excludes versions based on configuration', function () {
    config(['pertuk.exclude_versions' => ['v0.8-alpha', 'archived']]);

    $this->createTestMarkdownFile('test.md', '# v1.0', '', 'en', 'v1.0');
    $this->createTestMarkdownFile('test.md', '# v0.8-alpha', '', 'en', 'v0.8-alpha');
    $this->createTestMarkdownFile('test.md', '# archived', '', 'en', 'archived');

    $versions = \Xoshbin\Pertuk\Services\DocumentationService::getAvailableVersions();

    expect($versions)->toContain('v1.0');
    expect($versions)->not->toContain('v0.8-alpha');
    expect($versions)->not->toContain('archived');
});
