<?php

use Illuminate\Support\Facades\Route;
use Xoshbin\Pertuk\Http\Controllers\DocumentController;
use Xoshbin\Pertuk\Http\Controllers\LocaleController;

// Locale switching route (global, not prefixed) - supports both GET and POST
Route::match(['GET', 'POST'], '/locale/{locale}', [LocaleController::class, 'setLocale'])
    ->name('locale.set')
    ->where('locale', implode('|', config('pertuk.supported_locales', ['en'])));

$routePrefix = config('pertuk.route_prefix', 'docs');
$middleware = config('pertuk.middleware', []);

Route::prefix($routePrefix)
    ->middleware($middleware)
    ->name($routePrefix.'.')
    ->group(function () {
        // Redirect root /docs to default locale
        Route::get('/', function () {
            $default = config('pertuk.default_locale', 'en');

            return redirect()->route(config('pertuk.route_prefix', 'docs').'.show', ['locale' => $default, 'slug' => 'index']);
        })->name('index');

        // Search index per locale
        $supportedLocales = (array) config('pertuk.supported_locales', ['en']);
        $localeConstraint = implode('|', $supportedLocales);

        // Version constraint: Match anything that isn't a supported locale.
        $versionConstraint = '(?!('.$localeConstraint.')$)[a-zA-Z0-9\.]+';

        // Search index per locale
        Route::get('/{locale}/index.json', [DocumentController::class, 'searchIndex'])
            ->where('locale', $localeConstraint)
            ->name('index.json');

        Route::get('/{version}/{locale}/index.json', [DocumentController::class, 'searchIndex'])
            ->where('version', $versionConstraint)
            ->where('locale', $localeConstraint)
            ->name('version.index.json');

        // Main docs route (versioned)
        Route::get('/{version}/{locale}/{slug?}', [DocumentController::class, 'show'])
            ->where('version', $versionConstraint)
            ->where('locale', $localeConstraint)
            ->where('slug', '.*')
            ->name('version.show');

        Route::get('/{locale}/{slug?}', [DocumentController::class, 'show'])
            ->where('locale', $localeConstraint)
            ->where('slug', '.*')
            ->name('show');
    });
