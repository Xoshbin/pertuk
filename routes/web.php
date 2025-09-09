<?php

use Illuminate\Support\Facades\Route;
use Xoshbin\Pertuk\Http\Controllers\DocumentController;

$routePrefix = config('pertuk.route_prefix', 'docs');
$middleware = config('pertuk.middleware', []);

Route::prefix($routePrefix)
    ->middleware($middleware)
    ->name($routePrefix . '.')
    ->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');

        // JSON search index (must be before slug route)
        Route::get('/index.json', [DocumentController::class, 'searchIndex'])->name('index.json');

        // Must be last: catch-all slug route (allows slashes for nested docs)
        Route::get('/{slug}', [DocumentController::class, 'show'])
            ->where('slug', '.*')
            ->name('show');
    });
