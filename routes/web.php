<?php

use Illuminate\Support\Facades\Route;
use Xoshbin\Pertuk\Http\Controllers\DocumentController;
use Xoshbin\Pertuk\Http\Controllers\LocaleController;

// Locale switching route (global, not prefixed) - supports both GET and POST
Route::match(['GET', 'POST'], '/locale/{locale}', [LocaleController::class, 'setLocale'])
    ->name('pertuk.locale.set')
    ->where('locale', implode('|', config('pertuk.supported_locales', ['en'])));

$routePrefix = config('pertuk.route_prefix', 'docs');
$middleware = config('pertuk.middleware', []);

Route::prefix($routePrefix)
    ->middleware($middleware)
    ->name('pertuk.docs.')
    ->group(function () {
        $controller = DocumentController::class;

        // Redirect root /docs to default locale
        Route::get('/', function () {
            $default = config('pertuk.default_locale', 'en');
            // If default version is set, we might want to include it, but let's stick to locale/slug for now
            // or redirect to the most "canonical" starting point.
            // For now, mirroring existing behavior but cleaner.

            return redirect()->route(config('pertuk.route_prefix', 'docs').'.show', [
                'locale' => $default,
                'slug' => 'index',
            ]);
        })->name('index');

        $supportedLocales = (array) config('pertuk.supported_locales', ['en']);
        $localeConstraint = implode('|', $supportedLocales);
        $versionConstraint = '(?!('.$localeConstraint.')$)[a-zA-Z0-9\.]+';

        // Search routes
        Route::controller($controller)->group(function () use ($localeConstraint, $versionConstraint) {
            Route::get('/{locale}/index.json', 'searchIndex')
                ->where('locale', $localeConstraint)
                ->name('search.json');

            Route::get('/{version}/{locale}/index.json', 'searchIndex')
                ->where('version', $versionConstraint)
                ->where('locale', $localeConstraint)
                ->name('version.search.json');

            // Documentation routes
            // Note: Order matters. Versioned route first.
            Route::get('/{version}/{locale}/{slug?}', 'show')
                ->where('version', $versionConstraint)
                ->where('locale', $localeConstraint)
                ->where('slug', '.*')
                ->name('version.show');

            Route::get('/{locale}/{slug?}', 'show')
                ->where('locale', $localeConstraint)
                ->where('slug', '.*')
                ->name('show');
        });
    });
