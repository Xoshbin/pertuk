<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Extensions\Ascii;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\ExtensionInterface;

final class AsciiExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Add with high priority to run BEFORE the Shiki Highlighter renderer
        // Shiki uses default priority (0)
        $environment->addRenderer(FencedCode::class, new AsciiCodeRenderer, 100);
    }
}
