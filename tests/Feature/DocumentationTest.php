<?php

it('renders the docs index with at least one document', function () {
    // Create a test document
    $this->createTestMarkdownFile('payments.md', "---\ntitle: Payments\norder: 1\n---\n\n# Payments\n\nThis is a guide about payments.");

    $response = $this->get('/docs');

    $response->assertOk();
    // Expect the Payments guide to be displayed
    $response->assertSee('Payments', false);
});

it('renders a doc page with TOC and breadcrumbs', function () {
    // Create a test document with multiple headings for TOC
    $content = "---\ntitle: Receipt and Payment Vouchers\norder: 2\n---\n\n# Receipt and Payment Vouchers\n\n## Overview\n\nThis section covers vouchers.\n\n### Types of Vouchers\n\nDifferent types available.\n\n```php\n\$voucher = new Voucher();\n```\n\n## Processing\n\nHow to process vouchers.";

    $this->createTestMarkdownFile('receipt-payment-vouchers.md', $content, 'User Guide');

    $response = $this->get('/docs/User Guide/receipt-payment-vouchers');

    $response->assertOk();
    $response->assertSee('<h1', false);
    $response->assertSee('Receipt and Payment Vouchers', false);

    // TOC should include a link to a section
    $response->assertSee('href="#', false);

    // Breadcrumbs should include Docs and page title
    $response->assertSee('Docs', false);
    $response->assertSee('Receipt and Payment Vouchers', false);

    // Code blocks should be present
    $response->assertSee('<pre', false);
});

it('returns 304 Not Modified when If-Modified-Since matches', function () {
    // Create a test document
    $this->createTestMarkdownFile('receipt-payment-vouchers.md', "---\ntitle: Receipt and Payment Vouchers\n---\n\n# Receipt and Payment Vouchers\n\nContent here.", 'User Guide');

    $first = $this->get('/docs/User Guide/receipt-payment-vouchers');
    $first->assertOk();

    $lastModified = $first->headers->get('Last-Modified');
    expect($lastModified)->not->toBeNull();

    $second = $this->withHeaders([
        'If-Modified-Since' => $lastModified,
    ])->get('/docs/User Guide/receipt-payment-vouchers');

    $second->assertStatus(304);
});

it('returns 304 Not Modified when If-None-Match (ETag) matches', function () {
    // Create a test document
    $this->createTestMarkdownFile('receipt-payment-vouchers.md', "---\ntitle: Receipt and Payment Vouchers\n---\n\n# Receipt and Payment Vouchers\n\nContent here.", 'User Guide');

    $first = $this->get('/docs/User Guide/receipt-payment-vouchers');
    $first->assertOk();

    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $second = $this->withHeaders([
        'If-None-Match' => $etag,
    ])->get('/docs/User Guide/receipt-payment-vouchers');

    $second->assertStatus(304);
});

it('shows documentation search index', function () {
    // Create a test document for the search index
    $this->createTestMarkdownFile('test-doc.md', "---\ntitle: Test Document\n---\n\n# Test Document\n\nThis is a test document for search.");

    $response = $this->get('/docs/index.json');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');

    $data = $response->json();
    expect($data)->toBeArray();
    expect($data)->toHaveCount(1);
    expect($data[0])->toHaveKeys(['id', 'slug', 'title', 'heading', 'content', 'anchor']);
});
