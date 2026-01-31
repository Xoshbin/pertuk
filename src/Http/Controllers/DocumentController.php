<?php

declare(strict_types=1);

namespace Xoshbin\Pertuk\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Xoshbin\Pertuk\Services\DocumentationService;

class DocumentController extends Controller
{
    /**
     * Redirect root to default locale.
     */
    public function root(): RedirectResponse
    {
        $default = config('pertuk.default_locale', 'en');
        $prefix = config('pertuk.route_prefix', 'docs');

        return redirect()->route($prefix.'.show', [
            'locale' => $default,
            'slug' => 'index',
        ]);
    }

    /**
     * Show a documentation page.
     */
    public function show(Request $request): HttpResponse|ViewContract|RedirectResponse
    {
        // Session starting should ideally be in middleware, leaving it for now if middleware is minimal.
        if (! Session::isStarted()) {
            Session::start();
        }

        $version = $request->route('version');
        $locale = $request->route('locale');
        $slug = $request->route('slug') ?? 'index';

        $locale = is_string($locale) ? $locale : 'en';
        $slug = is_string($slug) ? $slug : 'index';
        $version = is_string($version) ? $version : null;

        // Validation - 404 if locale not supported
        $supportedLocales = (array) config('pertuk.supported_locales', ['en']);
        abort_unless(in_array($locale, $supportedLocales, true), 404);

        // Set application locale state
        App::setLocale($locale);
        Session::put('locale', $locale);

        $docs = DocumentationService::make($version);

        // Attempt to retrieve the document
        try {
            $data = $docs->get($locale, $slug);
        } catch (FileNotFoundException) {
            // Fallback: If 'index' is requested but no physical index.md exists,
            // we render the index view with a list of all documents.
            if ($slug === 'index') {
                return $this->showIndex($docs, $locale);
            }
            abort(404);
        }

        // Standard documentation page view
        $viewData = array_merge($data, [
            'slug' => $slug,
            'current_version' => $docs->getVersion(),
            'items' => $docs->list($locale), // Sidebar items
        ]);

        return $this->responseWithCacheHeaders(
            response()->view('pertuk::show', $viewData),
            (int) $data['mtime'],
            (string) $data['etag']
        );
    }

    /**
     * Helper to show the index page when index.md is missing.
     */
    protected function showIndex(DocumentationService $docs, string $locale): ViewContract
    {
        $items = $docs->list($locale);

        // Group items for the index view
        $groupedItems = collect($items)->groupBy(function ($item) {
            $parts = explode('/', $item['slug']);

            return count($parts) > 1 ? $parts[0] : 'Getting Started';
        });

        return View::make('pertuk::index', [
            'items' => $items, // Plain list for sidebar
            'groupedItems' => $groupedItems, // Grouped for main content
            'current_version' => $docs->getVersion(),
        ]);
    }

    /**
     * Attach HTTP caching headers to the response.
     */
    protected function responseWithCacheHeaders(HttpResponse $response, int $mtime, string $etag): HttpResponse
    {
        $lastModified = gmdate('D, d M Y H:i:s', $mtime).' GMT';

        // Check for conditional requests
        $request = request();
        $ifModifiedSince = $request->header('If-Modified-Since');
        $ifNoneMatch = $request->header('If-None-Match');

        if (($ifModifiedSince && $ifModifiedSince === $lastModified) ||
            ($ifNoneMatch && $ifNoneMatch === $etag)) {
            return response()->noContent(304);
        }

        return $response->withHeaders([
            'Last-Modified' => $lastModified,
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * Search index endpoint.
     */
    public function searchIndex(Request $request, string $locale): JsonResponse
    {
        $version = $request->route('version');
        $version = is_string($version) ? $version : null;

        $items = DocumentationService::make($version)->buildIndex($locale);

        return response()->json($items)->header('Content-Type', 'application/json');
    }
}
