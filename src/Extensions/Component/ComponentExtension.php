<?php

namespace Xoshbin\Pertuk\Extensions\Component;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

class ComponentExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new ComponentBlockStartParser, 100);
        $environment->addRenderer(ComponentBlock::class, new ComponentRenderer);
    }
}
