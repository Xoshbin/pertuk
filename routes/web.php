<?php

use Illuminate\Support\Facades\Route;
use Xoshbin\Pertuk\Http\Controllers\DocumentController;
use Xoshbin\Pertuk\Http\Controllers\LocaleController;

// Locale switching route (global, not prefixed) - supports both GET and POST
Route::match(['GET', 'POST'], '/locale/{locale}', [LocaleController::class, 'setLocale'])
    ->name('locale.set')
    ->where('locale', 'en|ckb|ar');

$routePrefix = config('pertuk.route_prefix', 'docs');
$middleware = config('pertuk.middleware', []);

Route::prefix($routePrefix)
    ->middleware($middleware)
    ->name($routePrefix.'.')
    ->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');

        // JSON search index (must be before slug route)
        Route::get('/index.json', [DocumentController::class, 'searchIndex'])->name('index.json');

        // Must be last: catch-all slug route (allows slashes for nested docs)
        Route::get('/{slug}', [DocumentController::class, 'show'])
            ->where('slug', '.*')
            ->name('show');
    });
