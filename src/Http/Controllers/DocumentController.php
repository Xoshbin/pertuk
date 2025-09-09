<?php

namespace Xoshbin\Pertuk\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Xoshbin\Pertuk\Services\DocumentationService;

class DocumentController extends Controller
{
    public function index(): \Illuminate\Contracts\View\View
    {
        $docs = DocumentationService::make();
        $items = $docs->list();

        return View::make('pertuk::index', compact('items'));
    }

    public function show(Request $request, string $slug): \Illuminate\Http\Response|\Illuminate\Contracts\View\View
    {
        $docs = DocumentationService::make();
        $data = $docs->get($slug);

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

    public function searchIndex(): \Illuminate\Http\JsonResponse
    {
        $items = DocumentationService::make()->buildIndex();

        return response()->json($items)->header('Content-Type', 'application/json');
    }
}
