<?php

namespace Xoshbin\Pertuk\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Xoshbin\Pertuk\Services\DocumentationService;

class DocumentController extends Controller
{
    public function show(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        // Session starting should ideally be in middleware, leaving it for now if middleware is minimal.
        if (! Session::isStarted()) {
            Session::start();
        }

        $version = $request->route('version');
        $locale = $request->route('locale');
        $slug = $request->route('slug') ?? 'index';

        // Validation - 404 if locale not supported
        abort_unless(in_array($locale, config('pertuk.supported_locales', ['en'])), 404);

        // Set application locale state
        App::setLocale($locale);
        Session::put('locale', $locale);

        $docs = DocumentationService::make($version);

        // Attempt to retrieve the document
        try {
            $data = $docs->get($locale, $slug);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
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
            $data['mtime'],
            $data['etag']
        );
    }

    /**
     * Render the index page with grouped documentation items.
     */
    protected function showIndex(DocumentationService $docs, string $locale): \Illuminate\Contracts\View\View
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
    protected function responseWithCacheHeaders(\Illuminate\Http\Response $response, int $mtime, string $etag): \Illuminate\Http\Response
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

    public function searchIndex(Request $request, string $locale): \Illuminate\Http\JsonResponse
    {
        $version = $request->route('version');
        $items = DocumentationService::make($version)->buildIndex($locale);

        return response()->json($items)->header('Content-Type', 'application/json');
    }
}
