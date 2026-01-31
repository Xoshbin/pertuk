<?php

use Illuminate\Support\Facades\Route;
use Xoshbin\Pertuk\Http\Controllers\DocumentController;
use Xoshbin\Pertuk\Http\Controllers\LocaleController;

// Locale switching route
Route::match(['GET', 'POST'], '/locale/{locale}', [LocaleController::class, 'setLocale'])
    ->name('pertuk.locale.set')
    ->where('locale', implode('|', config('pertuk.supported_locales', ['en'])));

Route::prefix(config('pertuk.route_prefix', 'docs'))
    ->middleware(config('pertuk.middleware', []))
    ->name('pertuk.docs.')
    ->group(function () {
        $locales = implode('|', (array) config('pertuk.supported_locales', ['en']));
        $version = '(?!('.$locales.')$)[a-zA-Z0-9\.]+';

        Route::controller(DocumentController::class)->group(function () use ($locales, $version) {
            // Root redirect
            Route::get('/', 'root')->name('index');

            // Search index
            Route::get('/{locale}/index.json', 'searchIndex')
                ->where('locale', $locales)
                ->name('search.json');

            Route::get('/{version}/{locale}/index.json', 'searchIndex')
                ->where('version', $version)
                ->where('locale', $locales)
                ->name('version.search.json');

            // Documentation pages
            Route::get('/{version}/{locale}/{slug?}', 'show')
                ->where('version', $version)
                ->where('locale', $locales)
                ->where('slug', '.*')
                ->name('version.show');

            Route::get('/{locale}/{slug?}', 'show')
                ->where('locale', $locales)
                ->where('slug', '.*')
                ->name('show');
        });
    });
