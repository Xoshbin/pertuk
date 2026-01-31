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
    public function show(Request $request, string $locale, ?string $slug = null): \Illuminate\Http\Response|\Illuminate\Contracts\View\View
    {
        // Ensure session is started for CSRF token generation
        if (! Session::isStarted()) {
            Session::start();
        }

        abort_unless(in_array($locale, config('pertuk.supported_locales', ['en'])), 404);

        App::setLocale($locale);
        Session::put('locale', $locale);

        $slug = $slug ?? 'index';

        $docs = DocumentationService::make();

        try {
            $data = $docs->get($locale, $slug);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            // If index definition is missing, list all docs (fallback behavior)
            if ($slug === 'index') {
                $items = $docs->list($locale);

                return View::make('pertuk::index', compact('items'));
            }
            abort(404);
        }

        $response = response()->view('pertuk::show', $data + ['slug' => $slug]);

        // Caching headers
        $lastModified = gmdate('D, d M Y H:i:s', $data['mtime']).' GMT';
        $etag = $data['etag'];

        $ifModifiedSince = $request->header('If-Modified-Since');
        $ifNoneMatch = $request->header('If-None-Match');

        if (($ifModifiedSince && $ifModifiedSince === $lastModified) ||
            ($ifNoneMatch && $ifNoneMatch === $etag)) {
            return response()->noContent(304);
        }

        $response->headers->set('Last-Modified', $lastModified);
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'public, max-age=300');

        return $response;
    }

    public function searchIndex(string $locale): \Illuminate\Http\JsonResponse
    {
        $items = DocumentationService::make()->buildIndex($locale);

        return response()->json($items)->header('Content-Type', 'application/json');
    }
}
