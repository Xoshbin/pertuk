<?php

use Illuminate\Support\Facades\Route;
use Xoshbin\Pertuk\Http\Controllers\DocumentController;
use Xoshbin\Pertuk\Http\Controllers\LocaleController;

use Xoshbin\Pertuk\Support\LocaleConfig;

// Locale switching route
Route::match(['GET', 'POST'], '/locale/{locale}', [LocaleController::class, 'setLocale'])
    ->name('pertuk.locale.set')
    ->where('locale', implode('|', LocaleConfig::allLangs()));

Route::prefix(config('pertuk.route_prefix', 'docs'))
    ->middleware(config('pertuk.middleware', []))
    ->name(config('pertuk.route_name_prefix', 'pertuk.docs.'))
    ->group(function () {
        Route::controller(DocumentController::class)->group(function () {
            // Assets serving
            Route::get('/assets/{path}', 'asset')
                ->where('path', '.*')
                ->name('asset');

            // Search index
            Route::get('/search.json', 'searchIndex')->name('search.json');
            Route::get('/{path}/search.json', 'searchIndex')
                ->where('path', '.*')
                ->name('search.json.scoped');

            // Documentation pages (catch-all)
            Route::get('/{path?}', 'show')
                ->where('path', '.*')
                ->name('show');
        });
    });
