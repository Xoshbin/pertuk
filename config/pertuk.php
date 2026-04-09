<?php

return [
    // Root folder for documentation files. Designed to be multi-lingual in future.
    // Place markdown files directly under docs/ (e.g., docs/payments.md) or under
    // per-locale folders (e.g., docs/en/payments.md, docs/ar/payments.md).
    'root' => base_path('docs'),

    // Source driver selection. 'local' reads from the filesystem; 'github'
    // syncs a GitHub repo subdirectory into storage and reads from there.
    'source' => env('PERTUK_SOURCE', 'local'),

    // Per-driver configuration.
    // Note: sources.github.* (PERTUK_DOCS_*) controls WHERE docs are fetched from.
    // The github_repo/github_branch keys below (PERTUK_GITHUB_*) drive "Edit on GitHub" links—separate concerns.
    'sources' => [
        'local' => [
            // When null, falls back to the legacy top-level 'root' key above.
            // Set this explicitly (or via PERTUK_DOCS_LOCAL_ROOT env var) to
            // override where LocalSource reads markdown from.
            'root' => env('PERTUK_DOCS_LOCAL_ROOT'),
        ],
        'github' => [
            'repo' => env('PERTUK_DOCS_REPO'),        // e.g. 'Xoshbin/asyar-launcher'
            'branch' => env('PERTUK_DOCS_BRANCH', 'main'),
            'path' => env('PERTUK_DOCS_PATH', 'docs'),
            'token' => env('PERTUK_DOCS_TOKEN'),        // optional PAT for private repos / higher rate limit
            'cache_path' => storage_path('app/pertuk/github'),
        ],
    ],

    // Default sort order when front matter 'order' is missing.
    'default_order' => 1000,

    // Excluded files or folders (relative to root) for file listing
    'exclude_directories' => [
        '.DS_Store',
        'README.md',
        'Developers',
    ],

    // Excluded version directories
    'exclude_versions' => [
        '.DS_Store',
        'README.md',
        'Developers',
    ],

    // Cache TTL (seconds) for parsed HTML & metadata.
    'cache_ttl' => 3600,

    // Enable or disable the documentation system
    'enabled' => true,

    // Route prefix for documentation
    'route_prefix' => 'docs',

    // Route name prefix
    'route_name_prefix' => 'pertuk.docs.',

    // Route middleware
    'middleware' => ['web'],

    // Localization settings
    'supported_locales' => ['en', 'ckb', 'ar'],
    'default_locale' => 'en',
    'rtl_locales' => ['ar', 'ckb'],
    'locale_labels' => [
        'en' => 'English',
        'ckb' => 'کوردی (سۆرانی)',
        'ar' => 'العربية',
    ],

    // Theme settings
    // Options: 'light', 'dark', 'system'
    'theme' => 'system',

    // GitHub Repo & Branch for "Edit on GitHub" links
    'github_repo' => env('PERTUK_GITHUB_REPO', 'username/repo'), // @phpstan-ignore-line
    'github_branch' => env('PERTUK_GITHUB_BRANCH', 'main'), // @phpstan-ignore-line
    'github_path' => null, // Folder path in repo where docs are located (null = use same structure as local)

    // Assets directory (relative to documentation root)
    'assets_path' => 'assets',

    // External Links
    'github_url' => env('PERTUK_GITHUB_URL', 'https://github.com/Xoshbin/kezi'),
    'discord_url' => env('PERTUK_DISCORD_URL', 'https://discord.gg/your-discord'),

];
