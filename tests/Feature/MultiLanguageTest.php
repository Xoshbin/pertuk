<?php

use Xoshbin\Pertuk\Services\DocumentationService;

it('detects document locale from filename suffix', function () {
    // Create documents in different languages
    $this->createTestMarkdownFile('guide.md', "---\ntitle: English Guide\n---\n\n# English Guide");
    $this->createTestMarkdownFile('guide.ar.md', "---\ntitle: Arabic Guide\n---\n\n# Arabic Guide");
    $this->createTestMarkdownFile('guide.ckb.md', "---\ntitle: Kurdish Guide\n---\n\n# Kurdish Guide");

    $service = DocumentationService::make();

    // Test English (default)
    $enDoc = $service->get('guide');
    expect($enDoc['current_locale'])->toBe('en');
    expect($enDoc['title'])->toBe('English Guide');

    // Test Arabic
    $arDoc = $service->get('guide.ar');
    expect($arDoc['current_locale'])->toBe('ar');
    expect($arDoc['title'])->toBe('Arabic Guide');

    // Test Kurdish
    $ckbDoc = $service->get('guide.ckb');
    expect($ckbDoc['current_locale'])->toBe('ckb');
    expect($ckbDoc['title'])->toBe('Kurdish Guide');
});

it('prioritizes current app locale in document listing', function () {
    // Create documents in different languages
    $this->createTestMarkdownFile('payments.md', "---\ntitle: Payments (English)\norder: 1\n---\n\n# Payments");
    $this->createTestMarkdownFile('payments.ar.md', "---\ntitle: Payments (Arabic)\norder: 1\n---\n\n# Payments");

    $service = DocumentationService::make();

    // Test with English locale (default)
    app()->setLocale('en');
    $list = $service->list();
    expect($list)->toHaveKey('payments');
    expect($list['payments']['title'])->toBe('Payments (English)');

    // Test with Arabic locale
    app()->setLocale('ar');
    $list = $service->list();
    expect($list)->toHaveKey('payments.ar');
    expect($list['payments.ar']['title'])->toBe('Payments (Arabic)');
});

it('builds language alternates for documents', function () {
    // Create documents in multiple languages
    $this->createTestMarkdownFile('tutorial.md', "---\ntitle: Tutorial (EN)\n---\n\n# Tutorial");
    $this->createTestMarkdownFile('tutorial.ar.md', "---\ntitle: Tutorial (AR)\n---\n\n# Tutorial");
    $this->createTestMarkdownFile('tutorial.ckb.md', "---\ntitle: Tutorial (CKB)\n---\n\n# Tutorial");

    $service = DocumentationService::make();

    // Get English version
    $enDoc = $service->get('tutorial');
    expect($enDoc['alternates'])->toHaveCount(3);

    $locales = collect($enDoc['alternates'])->pluck('locale')->toArray();
    expect($locales)->toContain('en', 'ar', 'ckb');

    // Check that English is marked as active
    $activeAlternate = collect($enDoc['alternates'])->firstWhere('active', true);
    expect($activeAlternate['locale'])->toBe('en');

    // Get Arabic version
    $arDoc = $service->get('tutorial.ar');
    $activeAlternate = collect($arDoc['alternates'])->firstWhere('active', true);
    expect($activeAlternate['locale'])->toBe('ar');
});

it('works with custom locale suffixes beyond en/ar/ckb', function () {
    // Create documents with custom locale suffixes
    $this->createTestMarkdownFile('custom.md', "---\ntitle: Custom (Default)\n---\n\n# Custom");
    $this->createTestMarkdownFile('custom.fr.md', "---\ntitle: Custom (French)\n---\n\n# Custom");
    $this->createTestMarkdownFile('custom.de.md', "---\ntitle: Custom (German)\n---\n\n# Custom");

    $service = DocumentationService::make();

    // The service should handle these gracefully, treating them as English by default
    // since they don't match the hardcoded ar/ckb patterns
    $frDoc = $service->get('custom.fr');
    expect($frDoc['current_locale'])->toBe('en'); // Falls back to default
    expect($frDoc['title'])->toBe('Custom (French)');

    $deDoc = $service->get('custom.de');
    expect($deDoc['current_locale'])->toBe('en'); // Falls back to default
    expect($deDoc['title'])->toBe('Custom (German)');
});

it('falls back to base document when locale-specific version is missing', function () {
    // Create only English version
    $this->createTestMarkdownFile('fallback-test.md', "---\ntitle: Fallback Test\n---\n\n# Fallback Test");

    $service = DocumentationService::make();

    // Try to get Arabic version (should fall back to English)
    $doc = $service->get('fallback-test.ar');
    expect($doc['title'])->toBe('Fallback Test');
    expect($doc['current_locale'])->toBe('en'); // Should detect as English since it's the base file
});

it('handles nested directories with locale suffixes', function () {
    // Create nested documents with locale suffixes
    $this->createTestMarkdownFile('advanced.md', "---\ntitle: Advanced (EN)\n---\n\n# Advanced", 'User Guide');
    $this->createTestMarkdownFile('advanced.ar.md', "---\ntitle: Advanced (AR)\n---\n\n# Advanced", 'User Guide');

    $service = DocumentationService::make();

    $enDoc = $service->get('User Guide/advanced');
    expect($enDoc['title'])->toBe('Advanced (EN)');
    expect($enDoc['current_locale'])->toBe('en');

    $arDoc = $service->get('User Guide/advanced.ar');
    expect($arDoc['title'])->toBe('Advanced (AR)');
    expect($arDoc['current_locale'])->toBe('ar');
});

it('excludes locale-specific files from listing when base version exists', function () {
    // Create documents in multiple languages
    $this->createTestMarkdownFile('multi-lang.md', "---\ntitle: Multi Lang (EN)\norder: 1\n---\n\n# Multi Lang");
    $this->createTestMarkdownFile('multi-lang.ar.md', "---\ntitle: Multi Lang (AR)\norder: 1\n---\n\n# Multi Lang");
    $this->createTestMarkdownFile('multi-lang.ckb.md', "---\ntitle: Multi Lang (CKB)\norder: 1\n---\n\n# Multi Lang");

    $service = DocumentationService::make();

    // With English locale, should show English version
    app()->setLocale('en');
    $list = $service->list();
    expect($list)->toHaveKey('multi-lang');
    expect($list)->not->toHaveKey('multi-lang.ar');
    expect($list)->not->toHaveKey('multi-lang.ckb');

    // With Arabic locale, should show Arabic version
    app()->setLocale('ar');
    $list = $service->list();
    expect($list)->toHaveKey('multi-lang.ar');
    expect($list)->not->toHaveKey('multi-lang');
    expect($list)->not->toHaveKey('multi-lang.ckb');
});

it('handles RTL detection for Arabic and Kurdish locales', function () {
    // Create Arabic document
    $this->createTestMarkdownFile('rtl-test.ar.md', "---\ntitle: RTL Test\n---\n\n# RTL Test");

    $response = $this->get('/docs/rtl-test.ar');
    $response->assertOk();

    // Should contain RTL direction attribute
    $response->assertSee('dir="rtl"', false);
});

it('generates correct URLs for language alternates', function () {
    // Create documents in multiple languages
    $this->createTestMarkdownFile('url-test.md', "---\ntitle: URL Test (EN)\n---\n\n# URL Test");
    $this->createTestMarkdownFile('url-test.ar.md', "---\ntitle: URL Test (AR)\n---\n\n# URL Test");

    $service = DocumentationService::make();

    $enDoc = $service->get('url-test');
    $alternates = $enDoc['alternates'];

    $enAlternate = collect($alternates)->firstWhere('locale', 'en');
    $arAlternate = collect($alternates)->firstWhere('locale', 'ar');

    expect($enAlternate['url'])->toContain('/docs/url-test');
    expect($arAlternate['url'])->toContain('/docs/url-test.ar');
});
