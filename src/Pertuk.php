<?php

namespace Xoshbin\Pertuk;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class Pertuk
{
    /**
     * The CSS paths to include.
     *
     * @var list<string|Htmlable>
     */
    protected array $css = [__DIR__.'/../dist/pertuk.css'];

    /**
     * Register or return CSS for Pertuk.
     *
     * @param  string|Htmlable|list<string|Htmlable>|null  $css
     */
    public function css(string|Htmlable|array|null $css = null): string|self
    {
        if (func_num_args() === 1) {
            $this->css = array_values(array_unique(array_merge($this->css, Arr::wrap($css)), SORT_REGULAR));

            return $this;
        }

        return collect($this->css)->reduce(function ($carry, $css) {
            if ($css instanceof Htmlable) {
                return $carry . Str::finish($css->toHtml(), PHP_EOL);
            }

            if (($contents = @file_get_contents($css)) === false) {
                throw new RuntimeException("Unable to load Pertuk CSS path [$css].");
            }

            return $carry . "<style>{$contents}</style>" . PHP_EOL;
        }, '');
    }

    /**
     * Return the compiled JavaScript from the vendor directory.
     */
    public function js(): string
    {
        if (($js = @file_get_contents(__DIR__.'/../dist/pertuk.js')) === false) {
            throw new RuntimeException('Unable to load the Pertuk JavaScript.');
        }

        return "<script>{$js}</script>" . PHP_EOL;
    }
}

