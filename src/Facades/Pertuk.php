<?php

namespace Xoshbin\Pertuk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string js()
 * @method static \Xoshbin\Pertuk\Pertuk|string css(string|\Illuminate\Contracts\Support\Htmlable|array|null $css = null)
 *
 * @see \Xoshbin\Pertuk\Pertuk
 */
class Pertuk extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Xoshbin\Pertuk\Pertuk::class;
    }
}

