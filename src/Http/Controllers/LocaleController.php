<?php

namespace Xoshbin\Pertuk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    /**
     * Set the application locale and store it in session.
     */
    /**
     * Set the application locale and store it in session.
     */
    public function setLocale(Request $request, string $locale): Response|JsonResponse|RedirectResponse
    {
        $supportedLocales = (array) config('pertuk.supported_locales', ['en']);

        if (! in_array($locale, $supportedLocales, true)) {
            return response('Invalid locale', 400);
        }

        Session::put('locale', $locale);
        App::setLocale($locale);

        // If AJAX, we might want to return the new URL for client-side redirection
        if ($request->ajax()) {
            $response = ['status' => 'success'];
            $referer = $request->header('referer');

            if ($referer && $this->isDocsUrl($referer)) {
                $response['redirect_url'] = $this->getLocaleEquivalentUrl($referer, $locale);
            }

            return response()->json($response);
        }

        // Standard redirection
        $redirectUrl = $request->get('redirect') ?: $request->header('referer');

        if ($redirectUrl && $this->isDocsUrl($redirectUrl)) {
            return redirect($this->getLocaleEquivalentUrl($redirectUrl, $locale));
        }

        // Fallback to default docs index
        return redirect()->route('pertuk.docs.show', ['locale' => $locale]);
    }

    /**
     * Check if the given URL is part of the documentation.
     */
    protected function isDocsUrl(string $url): bool
    {
        $docsPrefix = config('pertuk.route_prefix', 'docs');

        return str_contains($url, "/{$docsPrefix}/");
    }

    /**
     * Get the equivalent URL for a docs page in the specified locale.
     */
    protected function getLocaleEquivalentUrl(string $url, string $locale): string
    {
        $docsPrefix = config('pertuk.route_prefix', 'docs');
        $versions = \Xoshbin\Pertuk\Services\DocumentationService::getAvailableVersions();

        // Parse path from URL
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $path = trim($path, '/');

        $segments = explode('/', $path);

        // Find where the docs prefix ends.
        // We assume the prefix is at the start of the path relative to the domain root.
        // If the app is in a subdirectory, this logic might need adjustment, but for now assuming standard setup.

        $prefixSegments = explode('/', $docsPrefix);
        $remainder = array_slice($segments, count($prefixSegments));

        if (empty($remainder)) {
            return route('pertuk.docs.show', ['locale' => $locale]);
        }

        // Check structure: [version, locale, slug...] or [locale, slug...]
        $first = $remainder[0];

        if (in_array($first, $versions)) {
            // Versioned URL: /docs/{version}/{locale}/{slug}
            // Replace the second segment (locale)
            if (isset($remainder[1])) {
                $remainder[1] = $locale;
            }
        } else {
            // Unversioned URL: /docs/{locale}/{slug}
            // Replace first segment
            $remainder[0] = $locale;
        }

        return url($docsPrefix.'/'.implode('/', $remainder));
    }
}
