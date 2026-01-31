<?php

namespace Xoshbin\Pertuk\Extensions\Component;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

class ComponentBlockStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        // Match <x-pertuk-[name] [attributes]>
        $remainder = $cursor->getRemainder();
        if (! preg_match('/^<(x-pertuk-[a-z0-9-]+)([^>]*)>/i', $remainder, $match)) {
            return BlockStart::none();
        }

        $tagName = $match[1];
        $attrString = $match[2];

        // Advance cursor
        $cursor->advanceBy(strlen($match[0]));
        $attributes = [];

        // Parse attributes
        if (preg_match_all('/([a-z0-9-]+)="([^"]*)"/i', $attrString, $attrMatches)) {
            foreach ($attrMatches[1] as $i => $name) {
                $attributes[$name] = $attrMatches[2][$i];
            }
        }

        return BlockStart::of(new ComponentBlockParser($tagName, $attributes))->at($cursor);
    }
}
