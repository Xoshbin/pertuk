<?php

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Xoshbin\Pertuk\Services\DocumentationService;

it('throws FileNotFoundException for non-existent documents', function () {
    $service = DocumentationService::make();

    expect(fn () => $service->get('non-existent-doc'))
        ->toThrow(FileNotFoundException::class, 'Doc not found for slug: non-existent-doc');
});

it('handles documents without front matter', function () {
    // Create a document without front matter
    $this->createTestMarkdownFile('no-frontmatter.md', "# Simple Document\n\nThis document has no front matter.");

    $service = DocumentationService::make();
    $doc = $service->get('no-frontmatter');

    expect($doc['title'])->toBe('Simple Document'); // Should extract from H1
    expect($doc['html'])->toContain('This document has no front matter');
});

it('handles documents with invalid YAML front matter', function () {
    // Create a document with malformed YAML
    $content = "---\ntitle: Invalid YAML\norder: [invalid yaml structure\n---\n\n# Document with Invalid YAML\n\nContent here.";
    $this->createTestMarkdownFile('invalid-yaml.md', $content);

    // Mock the Log facade to capture warnings
    Log::shouldReceive('warning')
        ->once()
        ->with('Failed to parse front matter', \Mockery::type('array'));

    $service = DocumentationService::make();
    $doc = $service->get('invalid-yaml');

    // Should still work, falling back to content parsing
    expect($doc['title'])->toBe('Document with Invalid YAML');
    expect($doc['html'])->toContain('Content here');
});

it('handles empty markdown files', function () {
    // Create an empty file
    $this->createTestMarkdownFile('empty.md', '');

    $service = DocumentationService::make();
    $doc = $service->get('empty');

    expect($doc['title'])->toBe('Empty'); // Should infer from filename
    expect($doc['html'])->toBe('');
    expect($doc['toc'])->toBeEmpty();
});

it('handles files with only front matter and no content', function () {
    // Create a file with only front matter
    $this->createTestMarkdownFile('only-frontmatter.md', "---\ntitle: Only Front Matter\norder: 1\n---");

    $service = DocumentationService::make();
    $doc = $service->get('only-frontmatter');

    expect($doc['title'])->toBe('Only Front Matter');
    expect($doc['html'])->toBe('');
});

it('handles files with malformed markdown', function () {
    // Create a file with some malformed markdown (though CommonMark is quite forgiving)
    $content = "---\ntitle: Malformed Markdown\n---\n\n# Heading\n\nSome content here.\n\n[Broken link](incomplete\n\n**Unclosed bold\n\n```\nUnclosed code block";
    $this->createTestMarkdownFile('malformed.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('malformed');

    // Should still parse what it can
    expect($doc['title'])->toBe('Malformed Markdown');
    expect($doc['html'])->toContain('Some content here');
});

it('handles very large markdown files', function () {
    // Create a large markdown file
    $content = "---\ntitle: Large Document\n---\n\n# Large Document\n\n";
    $content .= str_repeat('This is a paragraph with some content. ', 1000);
    $content .= "\n\n## Section\n\n";
    $content .= str_repeat('More content here. ', 1000);

    $this->createTestMarkdownFile('large.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('large');

    expect($doc['title'])->toBe('Large Document');
    expect(strlen($doc['html']))->toBeGreaterThan(10000);
});

it('handles files with special characters in content', function () {
    // Create a file with various special characters
    $content = "---\ntitle: Special Characters\n---\n\n# Special Characters\n\nEmojis: 🚀 🎉 💻\n\nUnicode: café, naïve, résumé\n\nSymbols: © ® ™ € £ ¥\n\nMath: α β γ δ ∑ ∫ ∞";
    $this->createTestMarkdownFile('special-chars.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('special-chars');

    expect($doc['title'])->toBe('Special Characters');
    expect($doc['html'])->toContain('🚀');
    expect($doc['html'])->toContain('café');
    expect($doc['html'])->toContain('©');
    expect($doc['html'])->toContain('α');
});

it('handles files with complex markdown features', function () {
    // Create a file with tables, code blocks, lists, etc.
    $content = "---\ntitle: Complex Markdown\n---\n\n# Complex Markdown\n\n## Table\n\n| Column 1 | Column 2 |\n|----------|----------|\n| Cell 1   | Cell 2   |\n\n## Code Block\n\n```php\n<?php\necho 'Hello World';\n```\n\n## List\n\n- Item 1\n  - Nested item\n- Item 2\n\n## Blockquote\n\n> This is a blockquote\n> with multiple lines";

    $this->createTestMarkdownFile('complex.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('complex');

    expect($doc['title'])->toBe('Complex Markdown');
    expect($doc['html'])->toContain('<table>');
    expect($doc['html'])->toContain('<code');
    expect($doc['html'])->toContain('<ul>');
    expect($doc['html'])->toContain('<blockquote>');
});

it('excludes files based on configuration', function () {
    // Create files that should be excluded
    $this->createTestMarkdownFile('.DS_Store', 'This should be excluded');
    $this->createTestMarkdownFile('README.md', '# README\n\nThis should be excluded');
    $this->createTestMarkdownFile('normal.md', '# Normal\n\nThis should be included');

    $service = DocumentationService::make();
    $list = $service->list();

    expect($list)->toHaveKey('normal');
    expect($list)->not->toHaveKey('.DS_Store');
    expect($list)->not->toHaveKey('README');
});

it('handles files in excluded directories', function () {
    // Create files in an excluded directory
    $this->createTestMarkdownFile('internal.md', '# Internal Doc', 'Developers');
    $this->createTestMarkdownFile('public.md', '# Public Doc', 'User Guide');

    $service = DocumentationService::make();
    $list = $service->list();

    expect($list)->toHaveKey('User Guide/public');
    expect($list)->not->toHaveKey('Developers/internal');
});

it('handles file reading errors gracefully', function () {
    // Create a test file
    $filePath = $this->createTestMarkdownFile('test-permissions.md', "# Test\n\nContent");

    // Make file unreadable (this might not work on all systems)
    if (chmod($filePath, 0000)) {
        $service = DocumentationService::make();

        // Should handle the error gracefully
        expect(fn () => $service->get('test-permissions'))
            ->toThrow(\Exception::class);

        // Restore permissions for cleanup
        chmod($filePath, 0644);
    } else {
        // Skip test if we can't change permissions
        $this->markTestSkipped('Cannot change file permissions on this system');
    }
});

it('handles concurrent file access', function () {
    // Create a test document
    $this->createTestMarkdownFile('concurrent.md', "# Concurrent Test\n\nContent");

    $service = DocumentationService::make();

    // Simulate concurrent access
    $results = [];
    for ($i = 0; $i < 5; $i++) {
        $results[] = $service->get('concurrent');
    }

    // All results should be identical
    foreach ($results as $result) {
        expect($result)->toEqual($results[0]);
    }
});

it('handles files with BOM (Byte Order Mark)', function () {
    // Create a file with BOM
    $content = "\xEF\xBB\xBF---\ntitle: BOM Test\n---\n\n# BOM Test\n\nContent with BOM";
    $this->createTestMarkdownFile('bom-test.md', $content);

    $service = DocumentationService::make();
    $doc = $service->get('bom-test');

    expect($doc['title'])->toBe('BOM Test');
    expect($doc['html'])->toContain('Content with BOM');
});
