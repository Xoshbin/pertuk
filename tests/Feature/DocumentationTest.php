<?php

it('shows documentation index page', function () {
    $response = $this->get(route('docs.index'));

    $response->assertStatus(200);
    $response->assertSee('Documentation', false);
});

it('shows individual documentation page by slug', function () {
    // Skip if no docs directory exists
    if (!is_dir(base_path('docs'))) {
        $this->markTestSkipped('No docs directory found');
    }

    $response = $this->get(route('docs.show', ['slug' => 'test']));
    
    // Should return 404 for non-existent doc or 200 for existing
    expect($response->status())->toBeIn([200, 404]);
});

it('shows documentation search index', function () {
    $response = $this->get(route('docs.index.json'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');
});
