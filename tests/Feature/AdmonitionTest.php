<?php

it('renders admonitions with correct classes', function () {
    $content = "# Admonition Test\n\n::: tip\nThis is a tip.\n:::\n\n::: warning\nThis is a warning.\n:::\n\n::: danger\nThis is danger.\n:::";

    $this->createTestMarkdownFile('admonition-test.md', $content);

    $response = $this->get('/docs/admonition-test');

    $response->assertOk();

    // Check if admonition classes are present
    $response->assertSee('admonition admonition-tip', false);
    $response->assertSee('admonition admonition-warning', false);
    $response->assertSee('admonition admonition-danger', false);

    // Check if content inside admonitions is rendered
    $response->assertSee('This is a tip.', false);
    $response->assertSee('This is a warning.', false);
    $response->assertSee('This is danger.', false);
});
