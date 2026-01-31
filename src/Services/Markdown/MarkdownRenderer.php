<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Services\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Spatie\CommonMarkShikiHighlighter\HighlightCodeExtension;
use Xoshbin\Pertuk\Extensions\Admonition\AdmonitionExtension;
use Xoshbin\Pertuk\Extensions\Component\ComponentExtension;

class MarkdownRenderer
{
    public function getConverter(): MarkdownConverter
    {
        // Create environment without custom config first so extensions can register
        // their schema before we merge extension-specific config. Merging config
        // too early can trigger Nette schema validation for unknown keys.
        $env = new Environment;
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new GithubFlavoredMarkdownExtension);

        // Register Shiki for syntax highlighting
        // TODO: Make theme configurable
        $env->addExtension(new HighlightCodeExtension('github-dark'));

        // Register HeadingPermalinkExtension so its schema is available before
        // merging config. This prevents Nette/League config validation errors
        // when providing heading_permalink options.
        $env->addExtension(new HeadingPermalinkExtension);

        // Register Admonition extension for ::: tip, ::: warning, ::: danger
        $env->addExtension(new AdmonitionExtension);

        // Register custom components (tabs, accordion)
        $env->addExtension(new ComponentExtension);

        // Merge heading_permalink config after extensions have been added so the
        // HeadingPermalinkExtension can register its schema first.
        // Note: Environment::mergeConfig is deprecated but acceptable here to
        // ensure schema-aware merging order.
        // phpcs:ignore SlevomatCodingStandard.Commenting.Todo.CommentFound
        // @phpstan-ignore-next-line -- merging config after extensions are registered to avoid validation errors
        $env->mergeConfig([
            'heading_permalink' => [
                'symbol' => '#',
            ],
        ]);

        return new MarkdownConverter($env);
    }
}
