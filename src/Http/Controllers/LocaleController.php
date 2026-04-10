<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Xoshbin\Pertuk\Support\LocaleConfig;
use Xoshbin\Pertuk\Support\PathResolver;
use Xoshbin\Pertuk\Support\PertukUrl;

class LocaleController extends Controller
{
    /**
     * Set the application locale and store it in session.
     */
    public function setLocale(Request $request, string $locale): Response|JsonResponse|RedirectResponse
    {
        if (! in_array($locale, LocaleConfig::allLangs(), true)) {
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
        $redirectUrl = $request->input('redirect') ?: $request->header('referer');

        if ($redirectUrl && is_string($redirectUrl) && $this->isDocsUrl($redirectUrl)) {
            return redirect($this->getLocaleEquivalentUrl($redirectUrl, $locale));
        }

        // Fallback to default docs index
        return redirect()->to(PertukUrl::doc('index', locale: $locale));
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

        // Parse path from URL
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $path = trim($path, '/');

        $segments = explode('/', $path);

        // Find where the docs prefix ends.
        $prefixSegments = explode('/', (string) $docsPrefix);
        $remainder = array_slice($segments, count($prefixSegments));

        if (empty($remainder)) {
            return PertukUrl::doc('index', locale: $locale);
        }

        $resolved = PathResolver::resolve(implode('/', $remainder));

        return PertukUrl::doc($resolved['slug'], locale: $locale, version: $resolved['version']);
    }
}
