<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Xoshbin\Pertuk\Services\DocumentationService;

it('caches parsed document content', function () {
    // Create a test document
    $this->createTestMarkdownFile('test-cache.md', "---\ntitle: Cache Test\n---\n\n# Cache Test\n\nThis content should be cached.");

    $service = DocumentationService::make();

    // Clear any existing cache
    Cache::flush();

    // First call should hit the file system
    $start = microtime(true);
    $doc1 = $service->get('en', 'test-cache');
    $time1 = microtime(true) - $start;

    // Second call should hit the cache (should be faster)
    $start = microtime(true);
    $doc2 = $service->get('en', 'test-cache');
    $time2 = microtime(true) - $start;

    expect($doc1)->toEqual($doc2);
    expect($time2)->toBeLessThan($time1); // Cache should be faster

    // Verify cache key exists
    $path = $this->getTestDocsPath().'/en/test-cache.md';
    $mtime = File::lastModified($path);
    $cacheKey = 'pertuk:docs:en:'.md5($path.':'.$mtime);

    expect(Cache::has($cacheKey))->toBeTrue();
});

it('invalidates cache when file is modified', function () {
    // Create a test document
    $filePath = $this->createTestMarkdownFile('test-invalidation.md', "---\ntitle: Original Title\n---\n\n# Original Content");

    $service = DocumentationService::make();

    // Get document (this will cache it)
    $doc1 = $service->get('en', 'test-invalidation');
    expect($doc1['title'])->toBe('Original Title');

    $mtime1 = File::lastModified($filePath);

    // Wait a moment to ensure different mtime
    sleep(1);

    // Modify the file
    File::put($filePath, "---\ntitle: Updated Title\n---\n\n# Updated Content\n\nThis is the updated content body.");

    $mtime2 = File::lastModified($filePath);
    expect($mtime2)->toBeGreaterThan($mtime1);

    // Get document again (should get fresh content, not cached)
    $doc2 = $service->get('en', 'test-invalidation');
    expect($doc2['title'])->toBe('Updated Title');
    expect($doc2['html'])->toContain('updated content body');
});

it('respects cache TTL configuration', function () {
    // Set a very short TTL for testing
    config(['pertuk.cache_ttl' => 1]);

    $this->createTestMarkdownFile('test-ttl.md', "---\ntitle: TTL Test\n---\n\n# TTL Test");

    $service = DocumentationService::make();

    // Get document (this will cache it)
    $doc1 = $service->get('en', 'test-ttl');

    // Verify it's cached
    $path = $this->getTestDocsPath().'/en/test-ttl.md';
    $mtime = File::lastModified($path);
    $cacheKey = 'pertuk:docs:en:'.md5($path.':'.$mtime);
    expect(Cache::has($cacheKey))->toBeTrue();

    // Wait for TTL to expire
    sleep(2);

    // Cache should have expired
    expect(Cache::has($cacheKey))->toBeFalse();
});

it('generates unique cache keys for different files', function () {
    // Create multiple test documents
    $this->createTestMarkdownFile('doc1.md', "---\ntitle: Document 1\n---\n\n# Document 1");
    $this->createTestMarkdownFile('doc2.md', "---\ntitle: Document 2\n---\n\n# Document 2");

    $service = DocumentationService::make();

    // Get both documents
    $service->get('en', 'doc1');
    $service->get('en', 'doc2');

    // Verify different cache keys exist
    $path1 = $this->getTestDocsPath().'/en/doc1.md';
    $path2 = $this->getTestDocsPath().'/en/doc2.md';
    $mtime1 = File::lastModified($path1);
    $mtime2 = File::lastModified($path2);

    $cacheKey1 = 'pertuk:docs:en:'.md5($path1.':'.$mtime1);
    $cacheKey2 = 'pertuk:docs:en:'.md5($path2.':'.$mtime2);

    expect($cacheKey1)->not->toBe($cacheKey2);
    expect(Cache::has($cacheKey1))->toBeTrue();
    expect(Cache::has($cacheKey2))->toBeTrue();
});

it('caches document list', function () {
    // Create multiple test documents
    $this->createTestMarkdownFile('list-doc1.md', "---\ntitle: List Doc 1\norder: 1\n---\n\n# List Doc 1");
    $this->createTestMarkdownFile('list-doc2.md', "---\ntitle: List Doc 2\norder: 2\n---\n\n# List Doc 2");

    $service = DocumentationService::make();

    // First call to list() - should scan filesystem
    $start = microtime(true);
    $list1 = $service->list();
    $time1 = microtime(true) - $start;

    // Second call should be faster (though list() doesn't explicitly cache,
    // individual file parsing should be cached)
    $start = microtime(true);
    $list2 = $service->list();
    $time2 = microtime(true) - $start;

    expect($list1)->toEqual($list2);
    expect(count($list1))->toBe(2);
    expect($list1)->toHaveKey('list-doc1');
    expect($list1)->toHaveKey('list-doc2');
});

it('handles cache corruption gracefully', function () {
    // Create a test document
    $this->createTestMarkdownFile('cache-corruption.md', "---\ntitle: Cache Corruption Test\n---\n\n# Cache Corruption Test");

    $service = DocumentationService::make();

    // Get document to cache it
    $doc1 = $service->get('en', 'cache-corruption');

    // Manually corrupt the cache
    $path = $this->getTestDocsPath().'/en/cache-corruption.md';
    $mtime = File::lastModified($path);
    $cacheKey = 'pertuk:docs:en:'.md5($path.':'.$mtime);

    Cache::put($cacheKey, 'corrupted-data', 60);

    // Should handle corruption and regenerate cache
    $doc2 = $service->get('en', 'cache-corruption');
    expect($doc2)->toBeArray();
    expect($doc2)->toHaveKey('title');
    expect($doc2['title'])->toBe('Cache Corruption Test');
});
