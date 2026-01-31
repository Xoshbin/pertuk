<?php

namespace Xoshbin\Pertuk\Extensions\Admonition;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

class AdmonitionExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new AdmonitionParser);
        $environment->addRenderer(Admonition::class, new AdmonitionRenderer);
    }
}
