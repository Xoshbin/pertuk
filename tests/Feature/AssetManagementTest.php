<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Xoshbin\Pertuk\Services\Content\ContentProcessor;

beforeEach(function () {
    $this->docsPath = base_path('tests/docs');
    mkdir($this->docsPath, 0777, true);
    mkdir($this->docsPath.'/assets', 0777, true);
    file_put_contents($this->docsPath.'/assets/test.png', "\x89PNG\r\n\x1a\nfake-image-content");

    Config::set('pertuk.root', $this->docsPath);
});

afterEach(function () {
    File::deleteDirectory($this->docsPath);
});

test('it serves assets from the documentation directory', function () {
    $response = $this->get('/docs/assets/test.png');

    $response->assertStatus(200);
    // Some environments might detect application/octet-stream for fake images
    $contentType = $response->headers->get('Content-Type');
    expect($contentType)->toBeIn(['image/png', 'application/octet-stream']);
});

test('it rewrites relative image paths in markdown content', function () {
    $processor = new ContentProcessor;
    $html = '<p><img src="../assets/test.png" alt="Test"></p>';

    $processed = $processor->postProcessLinksAndImages($html, 'en', 'some-doc');

    expect($processed)->toContain('src="'.url('docs/assets/test.png').'"');
});

test('it handles non-existent assets with 404', function () {
    $this->get('/docs/assets/missing.png')
        ->assertStatus(404);
});
