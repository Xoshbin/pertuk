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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Xoshbin\Pertuk\Services\DocumentationService;
use Xoshbin\Pertuk\Services\Source\SourceDriver;
use Xoshbin\Pertuk\Support\LocaleConfig;
use Xoshbin\Pertuk\Support\PathResolver;

class DocumentController extends Controller
{
    /**
     * Show a documentation page.
     */
    public function show(Request $request): HttpResponse|ViewContract|RedirectResponse
    {
        // Session starting should ideally be in middleware, leaving it for now if middleware is minimal.
        if (! Session::isStarted()) {
            Session::start();
        }

        $resolved = PathResolver::resolve((string) $request->route('path'));
        $locale = $resolved['locale'];
        $version = $resolved['version'];
        $slug = $resolved['slug'];

        // Validation - 404 if locale not supported
        abort_unless($locale && in_array($locale, LocaleConfig::allLangs(), true), 404);

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

        // Build featured cards from the first 3 groups
        $featuredCards = $groupedItems->take(3)->map(function ($groupItems, $groupKey) {
            $first = $groupItems->first();

            return [
                'title' => str_replace('-', ' ', ucfirst($groupKey)),
                'slug' => $first['slug'],
            ];
        })->values();

        return View::make('pertuk::index', [
            'items' => $items, // Plain list for sidebar
            'groupedItems' => $groupedItems, // Grouped for main content
            'featuredCards' => $featuredCards,
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
    public function searchIndex(Request $request): JsonResponse
    {
        $resolved = PathResolver::resolve((string) $request->route('path'));
        $locale = $resolved['locale'];
        $version = $resolved['version'];

        if (! $locale || ! in_array($locale, LocaleConfig::allLangs(), true)) {
            abort(404, 'Documentation locale not found.');
        }

        $items = DocumentationService::make($version)->buildIndex($locale);

        return response()->json($items)->header('Content-Type', 'application/json');
    }

    /**
     * Serve documentation assets.
     */
    public function asset(Request $request): BinaryFileResponse
    {
        $path = (string) $request->route('path');

        $source = app(SourceDriver::class);

        $fullPath = $source->ensureAsset($path);

        if ($fullPath === null) {
            abort(404);
        }

        return response()->file($fullPath);
    }
}
