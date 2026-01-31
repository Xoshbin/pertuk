<?php

namespace Xoshbin\Pertuk\Extensions\Admonition;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

class AdmonitionParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== ':') {
            return BlockStart::none();
        }

        $match = $cursor->match('/^:::\s*([a-z]+)/i');
        if ($match === null) {
            return BlockStart::none();
        }

        $type = strtolower(trim(substr($match, 3)));

        return BlockStart::of(new AdmonitionContainerParser($type))->at($cursor);
    }
}
