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

it('passes resolved version to view for generic routes', function () {
    // Create test content for latest version (v2.0)
    $this->createTestMarkdownFile('test.md', '# v2.0', '', 'en', 'v2.0');
    // Create older version to ensure we have multiple
    $this->createTestMarkdownFile('test.md', '# v1.0', '', 'en', 'v1.0');

    // Visit generic route which should resolve to v2.0
    $response = $this->get('/docs/en/test');

    $response->assertOk();
    $response->assertViewHas('current_version', 'v2.0');
});

it('passes resolved version to view for generic routes even in fallback', function () {
    // Create test content for latest version (v2.0)
    // We create 'other.md' so that version v2.0 is discovered.
    // We do NOT create 'index.md', so hitting /docs/en will trigger the FileNotFoundException -> fallback logic.
    $this->createTestMarkdownFile('other.md', '# v2.0', '', 'en', 'v2.0');

    // We also need another version to verify we are picking the latest
    $this->createTestMarkdownFile('other.md', '# v1.0', '', 'en', 'v1.0');

    // Visit generic route which defaults to index
    $response = $this->get('/docs/en');

    $response->assertOk();
    // This view is returned in the fallback block
    $response->assertViewIs('pertuk::index');
    // This assertion should fail current implementation
    $response->assertViewHas('current_version', 'v2.0');
});

it('marks the correct version as selected in the UI', function () {
    $this->createTestMarkdownFile('test.md', '# v1.0', '', 'en', 'v1.0');
    $this->createTestMarkdownFile('test.md', '# v2.0', '', 'en', 'v2.0'); // Latest

    // Visit v1.0
    $response = $this->get('/docs/v1.0/en/test');

    $response->assertOk();

    // Check v1.0 is selected
    $content = $response->getContent();

    $v1OptionPattern = '/<option[^>]*value="[^"]*\/v1\.0\/[^"]*"[^>]*selected[^>]*>/s';
    $v2OptionPattern = '/<option[^>]*value="[^"]*\/v2\.0\/[^"]*"[^>]*selected[^>]*>/s';

    expect(preg_match($v1OptionPattern, $content))->toBe(1, 'v1.0 should be selected');
    expect(preg_match($v2OptionPattern, $content))->toBe(0, 'v2.0 should NOT be selected');
});
