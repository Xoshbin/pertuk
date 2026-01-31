<?php

it('renders the docs index with at least one document', function () {
    // Create a test document
    $this->createTestMarkdownFile('payments.md', "---\ntitle: Payments\norder: 1\n---\n\n# Payments\n\nThis is a guide about payments.");

    $response = $this->get('/docs/en/index');

    $response->assertOk();
    // Expect the Payments guide to be displayed
    $response->assertSee('Payments', false);
});

it('discovers available versions correctly', function () {
    // Set up supported locales
    config(['pertuk.supported_locales' => ['en', 'ckb']]);

    // Create versioned directories
    $this->createTestMarkdownFile('test.md', '# Test', '', 'en', 'v1.0');
    $this->createTestMarkdownFile('test.md', '# Test', '', 'ckb', 'v2.0');

    $versions = \Xoshbin\Pertuk\Services\DocumentationService::getAvailableVersions();

    expect($versions)->toBeArray();
    expect($versions)->toContain('v1.0');
    expect($versions)->toContain('v2.0');
    expect($versions[0])->toBe('v2.0'); // Latest first
});

it('renders a doc page with TOC and breadcrumbs', function () {
    // Create a test document with multiple headings for TOC
    $content = "---\ntitle: Receipt and Payment Vouchers\norder: 2\n---\n\n# Receipt and Payment Vouchers\n\n## Overview\n\nThis section covers vouchers.\n\n### Types of Vouchers\n\nDifferent types available.\n\n```php\n\$voucher = new Voucher();\n```\n\n## Processing\n\nHow to process vouchers.";

    $this->createTestMarkdownFile('receipt-payment-vouchers.md', $content, 'User Guide');

    $response = $this->get('/docs/en/User Guide/receipt-payment-vouchers');

    $response->assertOk();
    $response->assertSee('<h1', false);
    $response->assertSee('Receipt and Payment Vouchers', false);

    // TOC should include a link to a section
    $response->assertSee('href="#', false);

    // Breadcrumbs should include Docs and page title
    $response->assertSee('Docs', false);
    $response->assertSee('Receipt and Payment Vouchers', false);

    // Code blocks should be present
    $response->assertSee('class="language-php"', false);
});

it('returns 304 Not Modified when If-Modified-Since matches', function () {
    // Create a test document
    $this->createTestMarkdownFile('receipt-payment-vouchers.md', "---\ntitle: Receipt and Payment Vouchers\n---\n\n# Receipt and Payment Vouchers\n\nContent here.", 'User Guide');

    $first = $this->get('/docs/en/User Guide/receipt-payment-vouchers');
    $first->assertOk();

    $lastModified = $first->headers->get('Last-Modified');
    expect($lastModified)->not->toBeNull();

    $second = $this->withHeaders([
        'If-Modified-Since' => $lastModified,
    ])->get('/docs/en/User Guide/receipt-payment-vouchers');

    $second->assertStatus(304);
});

it('returns 304 Not Modified when If-None-Match (ETag) matches', function () {
    // Create a test document
    $this->createTestMarkdownFile('receipt-payment-vouchers.md', "---\ntitle: Receipt and Payment Vouchers\n---\n\n# Receipt and Payment Vouchers\n\nContent here.", 'User Guide');

    $first = $this->get('/docs/en/User Guide/receipt-payment-vouchers');
    $first->assertOk();

    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $second = $this->withHeaders([
        'If-None-Match' => $etag,
    ])->get('/docs/en/User Guide/receipt-payment-vouchers');

    $second->assertStatus(304);
});

it('shows documentation search index', function () {
    // Create a test document for the search index
    $this->createTestMarkdownFile('test-doc.md', "---\ntitle: Test Document\n---\n\n# Test Document\n\nThis is a test document for search.");

    $response = $this->get('/docs/en/index.json');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');

    $data = $response->json();
    expect($data)->toBeArray();
    expect($data)->toHaveCount(1);
    expect($data[0])->toHaveKeys(['id', 'slug', 'title', 'heading', 'content', 'anchor']);
});

it('shows versioned documentation search index', function () {
    // Create a test document in a versioned directory
    $this->createTestMarkdownFile('test-v1.md', "# V1 Document\n\n## Subheading\n\nContent", '', 'en', 'v1');

    // Attempt to access versioned search index via route
    $response = $this->get('/docs/v1/en/index.json');

    $response->assertStatus(200);
    $response->assertJsonFragment(['slug' => 'test-v1']);
});

it('renders search input with dynamic data attributes', function () {
    // Set up test doc
    $this->createTestMarkdownFile('welcome.md', '# Welcome');

    // Get a page with the header
    $response = $this->get('/docs/en/welcome');
    $response->assertOk();

    $indexUrl = route('pertuk.docs.search.json', ['locale' => 'en']);
    $baseUrl = url('/docs/en');

    $response->assertSee('data-index-url="'.$indexUrl.'"', false);
    $response->assertSee('data-base-url="'.$baseUrl.'"', false);
});

it('renders versioned search input with dynamic data attributes', function () {
    // Set up versioned test doc
    $this->createTestMarkdownFile('v10-welcome.md', '# V10 Welcome', '', 'en', 'v10.0');

    // Get a versioned page
    $response = $this->get('/docs/v10.0/en/v10-welcome');
    $response->assertOk();

    $indexUrl = route('pertuk.docs.version.search.json', ['version' => 'v10.0', 'locale' => 'en']);
    $baseUrl = url('/docs/v10.0/en');

    $response->assertSee('data-index-url="'.$indexUrl.'"', false);
    $response->assertSee('data-base-url="'.$baseUrl.'"', false);
});
