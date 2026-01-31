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
    public function setLocale(Request $request, string $locale): Response|JsonResponse|RedirectResponse
    {
        // Validate locale against supported locales from config
        $supportedLocales = (array) config('pertuk.supported_locales', ['en']);

        if (! in_array($locale, $supportedLocales, true)) {
            return response('Invalid locale', 400);
        }

        // Store locale in session
        Session::put('locale', $locale);

        // Set locale for current request
        App::setLocale($locale);

        // Get dynamic docs route prefix from config
        $docsPrefix = '/'.config('pertuk.route_prefix', 'docs').'/';

        // If this is an AJAX request, return success with redirect URL if on docs page
        if ($request->ajax()) {
            $response = ['status' => 'success'];

            // If the referer is a docs page, provide the equivalent URL in the new locale
            $referer = $request->header('referer');
            if ($referer && str_contains($referer, $docsPrefix)) {
                $response['redirect_url'] = $this->getLocaleEquivalentUrl($referer, $locale);
            }

            return response()->json($response, 200);
        }

        // For regular requests, redirect back to the specified URL or referer
        $defaultDocsUrl = url(config('pertuk.route_prefix', 'docs'));
        $redirectUrl = $request->get('redirect') ?: $request->header('referer') ?: $defaultDocsUrl;

        // If redirect URL is a docs page, get the locale-specific version
        if (str_contains($redirectUrl, $docsPrefix)) {
            $redirectUrl = $this->getLocaleEquivalentUrl($redirectUrl, $locale);
        }

        return redirect($redirectUrl);
    }

    /**
     * Get the equivalent URL for a docs page in the specified locale.
     */
    /**
     * Get the equivalent URL for a docs page in the specified locale.
     */
    private function getLocaleEquivalentUrl(string $url, string $locale): string
    {
        // Get dynamic docs route prefix from config
        $docsPrefix = config('pertuk.route_prefix', 'docs');
        $supportedLocales = (array) config('pertuk.supported_locales', ['en']);
        // Use auto-discovered versions instead of config
        $versions = \Xoshbin\Pertuk\Services\DocumentationService::getAvailableVersions();

        // Parse current path
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $path = trim($path, '/'); // e.g. "docs/en/slug" or "docs/v1.0/en/slug"

        $prefixSegments = explode('/', $docsPrefix);
        $pathSegments = explode('/', $path);

        // Check if path starts with valid prefix
        foreach ($prefixSegments as $i => $segment) {
            if (($pathSegments[$i] ?? '') !== $segment) {
                // Not a docs url
                return url($docsPrefix.'/'.$locale);
            }
        }

        // Remove prefix segments
        $remainder = array_slice($pathSegments, count($prefixSegments));

        if (empty($remainder)) {
            return url($docsPrefix.'/'.$locale);
        }

        // Try to identify if the first segment of remainder is a version or a locale
        $first = $remainder[0];

        // If it's a version, then the locale should be the second segment
        if (in_array($first, $versions) && isset($remainder[1])) {
            $remainder[1] = $locale;
        } elseif (in_array($first, $supportedLocales)) {
            // It's a locale
            $remainder[0] = $locale;
        } else {
            // Fallback: if we can't tell, just use the locale at the first position
            $remainder[0] = $locale;
        }

        // Rebuild path
        return url($docsPrefix.'/'.implode('/', $remainder));
    }
}
