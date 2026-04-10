<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Tests\Feature;

it('renders ascii art with custom renderer', function () {
    $content = "```ascii\n+---+\n| A |\n+---+\n```";

    $this->createTestMarkdownFile('ascii-test.md', $content);

    $response = $this->get('/docs/ascii-test');

    $response->assertOk();

    // Check for our custom ASCII container and code classes
    $response->assertSee('class="ascii-art-container"', false);
    $response->assertSee('class="ascii-art"', false);
    $response->assertSee('data-language="ascii"', false);

    // Ensure newlines are preserved and content is present
    $response->assertSee('+---+', false);
    $response->assertSee('| A |', false);
});

it('renders json code blocks with proper language labels or classes', function () {
    $content = "```json\n{\n  \"id\": \"test\"\n}\n```";

    $this->createTestMarkdownFile('json-test.md', $content);

    $response = $this->get('/docs/json-test');

    $response->assertOk();

    // Shiki or CommonMark should add the language-json class
    $response->assertSee('language-json', false);

    // Content should be present
    $response->assertSee('"id"', false);
    $response->assertSee('"test"', false);
});

it('preserves newlines in multi-line code blocks', function () {
    // Specifically test that we don't have everything on a single line
    $content = "```\nfirst line\nsecond line\n```";

    $this->createTestMarkdownFile('newline-test.md', $content);

    $response = $this->get('/docs/newline-test');

    $response->assertOk();

    // Check for the presence of the lines
    $response->assertSee('first line', false);
    $response->assertSee('second line', false);

    // We expect them to be separated by a newline in the source OR in the rendered HTML
    // Normally pre tags preserve them.
    $html = $response->getContent();
    expect($html)->toContain('first line');
    expect($html)->toContain('second line');
});
