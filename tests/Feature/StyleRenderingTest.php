<?php

use Xoshbin\Pertuk\Services\DocumentationService;

it('injects hljs class into code blocks', function () {
    // Create a document with code blocks
    $content = "---\ntitle: Code Test\n---\n\n# Code Test\n\n```php\n<?php\necho 'Hello';\n```\n\n```javascript\nconsole.log('Hello');\n```\n\n```\nPlain code block\n```";
    $this->createTestMarkdownFile('code-test.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('code-test');

    // Should contain hljs class for syntax highlighting
    expect($doc['html'])->toContain('class=\"language-php hljs\"');
    expect($doc['html'])->toContain('class=\"language-javascript hljs\"');
    expect($doc['html'])->toContain('class=\"hljs\"'); // For plain code blocks
});

it('generates table of contents from headings', function () {
    // Create a document with multiple heading levels
    $content = "---\ntitle: TOC Test\n---\n\n# Main Title\n\n## Section 1\n\nContent here.\n\n### Subsection 1.1\n\nMore content.\n\n### Subsection 1.2\n\nEven more content.\n\n## Section 2\n\nFinal content.";
    $this->createTestMarkdownFile('toc-test.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('toc-test');

    expect($doc['toc'])->toHaveCount(4);

    // Check TOC structure
    expect($doc['toc'][0])->toEqual([
        'level' => 2,
        'id' => 'section-1',
        'text' => 'Section 1',
    ]);

    expect($doc['toc'][1])->toEqual([
        'level' => 3,
        'id' => 'subsection-11',
        'text' => 'Subsection 1.1',
    ]);

    // Verify IDs are injected into HTML
    expect($doc['html'])->toContain('id="section-1"');
    expect($doc['html'])->toContain('id="subsection-11"');
    expect($doc['html'])->toContain('id="subsection-12"');
    expect($doc['html'])->toContain('id="section-2"');
});

it('handles duplicate heading text with unique IDs', function () {
    // Create a document with duplicate heading text
    $content = "---\ntitle: Duplicate Headings\n---\n\n# Main Title\n\n## Introduction\n\nFirst intro.\n\n## Introduction\n\nSecond intro.\n\n## Introduction\n\nThird intro.";
    $this->createTestMarkdownFile('duplicate-headings.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('duplicate-headings');

    expect($doc['toc'])->toHaveCount(3);

    // Should have unique IDs
    expect($doc['toc'][0]['id'])->toBe('introduction');
    expect($doc['toc'][1]['id'])->toBe('introduction-1');
    expect($doc['toc'][2]['id'])->toBe('introduction-2');

    // Verify unique IDs in HTML
    expect($doc['html'])->toContain('id="introduction"');
    expect($doc['html'])->toContain('id="introduction-1"');
    expect($doc['html'])->toContain('id="introduction-2"');
});

it('removes first H1 from content to avoid duplication', function () {
    // Create a document with H1 that should be removed from content (no title in front matter)
    $content = "---\norder: 1\n---\n\n# This H1 Should Be Removed\n\nContent after H1.\n\n## This H2 Should Remain\n\nMore content.";
    $this->createTestMarkdownFile('h1-removal.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('h1-removal');

    // H1 should be removed from HTML content
    expect($doc['html'])->not->toContain('<h1');
    expect($doc['html'])->not->toContain('This H1 Should Be Removed');

    // But H2 should remain
    expect($doc['html'])->toContain('<h2');
    expect($doc['html'])->toContain('This H2 Should Remain');

    // Title should still be extracted correctly
    expect($doc['title'])->toBe('This H1 Should Be Removed');
});

it('processes external links with proper attributes', function () {
    // Create a document with external links
    $content = "---\ntitle: Link Test\n---\n\n# Link Test\n\n[External Link](https://example.com)\n\n[Another External](http://test.com)\n\n[Internal Link](./internal-doc)";
    $this->createTestMarkdownFile('link-test.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('link-test');

    // External links should have rel and target attributes
    expect($doc['html'])->toContain('rel="noopener noreferrer"');
    expect($doc['html'])->toContain('target="_blank"');
    expect($doc['html'])->toContain('href="https://example.com"');
    expect($doc['html'])->toContain('href="http://test.com"');

    // Internal links should not have these attributes
    $internalLinkPattern = '/<a[^>]*href="\.\/internal-doc"[^>]*>/';
    preg_match($internalLinkPattern, $doc['html'], $matches);
    if (! empty($matches)) {
        expect($matches[0])->not->toContain('target="_blank"');
        expect($matches[0])->not->toContain('rel="noopener noreferrer"');
    }
});

it('generates proper breadcrumbs structure', function () {
    // Create a nested document
    $this->createTestMarkdownFile('advanced-guide.md', "---\ntitle: Advanced Guide\n---\n\n# Advanced Guide", 'User Guide');

    $service = DocumentationService::make();
    $doc = $service->get('User Guide/advanced-guide');

    expect($doc['breadcrumbs'])->toHaveCount(1);
    expect($doc['breadcrumbs'][0])->toEqual([
        'title' => 'Documentation',
        'slug' => null,
    ]);
});

it('renders breadcrumbs correctly in view', function () {
    // Create a nested document
    $this->createTestMarkdownFile('user-manual.md', "---\ntitle: User Manual\n---\n\n# User Manual", 'Guides');

    $response = $this->get('/docs/Guides/user-manual');
    $response->assertOk();

    // Should contain breadcrumb navigation
    $response->assertSee('Breadcrumb', false);
    $response->assertSee('Documentation', false);
    $response->assertSee('User Manual', false);
});

it('applies RTL styling for Arabic and Kurdish content', function () {
    // Create Arabic document
    $this->createTestMarkdownFile('arabic-doc.ar.md', "---\ntitle: Arabic Document\n---\n\n# Arabic Document\n\nArabic content here.");

    $response = $this->get('/docs/arabic-doc.ar');
    $response->assertOk();

    // Should contain RTL direction
    $response->assertSee('dir="rtl"', false);
    $response->assertSee('text-right', false);
});

it('applies LTR styling for English content', function () {
    // Create English document
    $this->createTestMarkdownFile('english-doc.md', "---\ntitle: English Document\n---\n\n# English Document\n\nEnglish content here.");

    $response = $this->get('/docs/english-doc');
    $response->assertOk();

    // Should contain LTR direction (or no explicit direction)
    $response->assertSee('dir="ltr"', false);
});

it('includes proper CSS classes for prose styling', function () {
    // Create a document
    $this->createTestMarkdownFile('prose-test.md', "---\ntitle: Prose Test\n---\n\n# Prose Test\n\nContent for prose styling.");

    $response = $this->get('/docs/prose-test');
    $response->assertOk();

    // Should contain prose classes for styling
    $response->assertSee('docs-prose', false);
    $response->assertSee('prose', false);
    $response->assertSee('prose-slate', false);
    $response->assertSee('dark:prose-invert', false);
});

it('renders table of contents in sidebar', function () {
    // Create a document with headings for TOC
    $content = "---\ntitle: TOC Sidebar Test\n---\n\n# Main Title\n\n## Section A\n\nContent A.\n\n## Section B\n\nContent B.";
    $this->createTestMarkdownFile('toc-sidebar.md', $content);

    $response = $this->get('/docs/toc-sidebar');
    $response->assertOk();

    // Should contain TOC links
    $response->assertSee('href="#section-a"', false);
    $response->assertSee('href="#section-b"', false);
    $response->assertSee('Section A', false);
    $response->assertSee('Section B', false);
});

it('handles images in markdown content', function () {
    // Create a document with images
    $content = "---\ntitle: Image Test\n---\n\n# Image Test\n\n![Alt text](image.png)\n\n![Another image](./assets/logo.svg)";
    $this->createTestMarkdownFile('image-test.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('image-test');

    // Should contain img tags
    expect($doc['html'])->toContain('<img');
    expect($doc['html'])->toContain('alt="Alt text"');
    expect($doc['html'])->toContain('src="image.png"');
    expect($doc['html'])->toContain('alt="Another image"');
});

it('preserves markdown formatting in complex structures', function () {
    // Create a document with complex markdown
    $content = "---\ntitle: Complex Structure\n---\n\n# Complex Structure\n\n## Lists\n\n1. First item\n   - Nested bullet\n   - Another bullet\n2. Second item\n\n## Tables\n\n| Header 1 | Header 2 |\n|----------|----------|\n| Cell 1   | Cell 2   |\n\n## Emphasis\n\n**Bold text** and *italic text* and ~~strikethrough~~.";

    $this->createTestMarkdownFile('complex-structure.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('complex-structure');

    // Should contain proper HTML structures
    expect($doc['html'])->toContain('<ol>');
    expect($doc['html'])->toContain('<ul>');
    expect($doc['html'])->toContain('<table>');
    expect($doc['html'])->toContain('<strong>');
    expect($doc['html'])->toContain('<em>');
    expect($doc['html'])->toContain('<del>');
});
