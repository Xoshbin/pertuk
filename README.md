# Pertuk - Laravel Documentation Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/xoshbin/pertuk.svg?style=flat-square)](https://packagist.org/packages/xoshbin/pertuk)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/xoshbin/pertuk/run-tests?label=tests)](https://github.com/xoshbin/pertuk/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/xoshbin/pertuk/fix-php-code-style-issues?label=code%20style)](https://github.com/xoshbin/pertuk/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/xoshbin/pertuk.svg?style=flat-square)](https://packagist.org/packages/xoshbin/pertuk)

Pertuk is a powerful Laravel documentation package that provides a complete documentation system with multi-language support, markdown processing, search functionality, and a beautiful, responsive UI.

![img.png](assets/screenshot.jpeg)

## Features

-   📖 **Premium Markdown**: Full CommonMark and GitHub Flavored Markdown support
-   🎨 **Shiki Syntax Highlighting**: Server-side, VS-Code quality syntax highlighting
-   🌍 **Multi-Language Support**: Built-in support for multiple locales with RTL handling
-   🪴 **Root Locale**: Serve a primary language at the docs root with no URL prefix (VitePress/Starlight style)
-   🔢 **Versioning Support**: Explicit version configuration with a built-in version picker
-   🐙 **GitHub Source**: Render docs directly from any GitHub repository
-   🔍 **Deep Local Search**: Full-content indexing via MiniSearch with relevancy scoring
-   🧩 **Interactive Components**: Built-in support for Tabs and Accordions in Markdown
-   🎨 **Modern UI**: Responsive design with interactive sidebar and dark mode
-   📱 **Mobile Friendly**: Optimized for all device sizes
-   🗂️ **Auto Table of Contents**: Automatic TOC generation from headings
-   💾 **Intelligent Caching**: High-performance document rendering and caching
-   🧭 **Breadcrumbs**: Automatic breadcrumb navigation
-   🏷️ **Front Matter Support**: YAML front matter for metadata
-   💡 **Admonitions**: Support for tip, warning, and danger callouts
-   🚀 **Pre-rendering**: Artisan command to pre-render documentation for maximum speed

## Quick Start

1. Install the package:

```bash
composer require xoshbin/pertuk
```

2. Publish the assets (JS and CSS):

```bash
php artisan vendor:publish --tag="pertuk-assets"
```

3. (Optional) Publish the config:

```bash
php artisan vendor:publish --tag="pertuk-config"
```

4. Create your docs directory and add a markdown file.

5. Visit your docs at `/docs`.

- **Customization**: Publish the views to customize the layout and markup:

```bash
php artisan vendor:publish --tag="pertuk-views"
```

6. (Optional) Pre-render documentation for performance:

```bash
php artisan pertuk:build
```

## Configuration

```php
return [
    // Root folder for documentation files (local source).
    'root' => base_path('docs'),

    // Source driver: 'local' (default) or 'github'.
    'source' => env('PERTUK_SOURCE', 'local'),

    // Per-driver configuration.
    'sources' => [
        'local' => [
            'root' => env('PERTUK_DOCS_LOCAL_ROOT'),
        ],
        'github' => [
            'repo'       => env('PERTUK_DOCS_REPO'),
            'branch'     => env('PERTUK_DOCS_BRANCH', 'main'),
            'path'       => env('PERTUK_DOCS_PATH', 'docs'),
            'token'      => env('PERTUK_DOCS_TOKEN'),
            'cache_path' => storage_path('app/pertuk/github'),
        ],
    ],

    // Locale configuration — mirrors VitePress/Starlight conventions.
    // The 'root' key designates the primary locale whose files live flat
    // at the docs root with no URL prefix. Secondary locales keep /{code}/.
    // Omit 'root' to use classic locale-prefixed mode (docs/en/, docs/ar/).
    'locales' => [
        'root' => ['label' => 'English', 'lang' => 'en', 'dir' => 'ltr'],
        'ar'   => ['label' => 'العربية', 'lang' => 'ar', 'dir' => 'rtl'],
        'ckb'  => ['label' => 'کوردی (سۆرانی)', 'lang' => 'ckb', 'dir' => 'rtl'],
    ],

    // Explicit version list. Empty = no versioning.
    // e.g. ['v1.0', 'v2.0']
    'versions' => [],

    // Default sort order when front matter 'order' is missing.
    'default_order' => 1000,

    // Excluded files or folders (relative to root) for file listing.
    'exclude_directories' => [
        '.DS_Store',
        'README.md',
    ],

    // Cache TTL (seconds) for parsed HTML & metadata.
    'cache_ttl' => 3600,

    // Enable or disable the documentation system.
    'enabled' => true,

    // Route prefix for documentation.
    'route_prefix' => 'docs',

    // Route name prefix.
    'route_name_prefix' => 'pertuk.docs.',

    // Route middleware.
    'middleware' => ['web'],

    // GitHub Repo & Branch for "Edit on GitHub" links.
    'github_repo'   => env('PERTUK_GITHUB_REPO', 'username/repo'),
    'github_branch' => env('PERTUK_GITHUB_BRANCH', 'main'),
    'github_path'   => null,

    // Assets directory (relative to documentation root).
    'assets_path' => 'assets',

    // External links.
    'github_url'  => env('PERTUK_GITHUB_URL', ''),
    'discord_url' => env('PERTUK_DISCORD_URL', ''),
];
```

## Directory Structures

### Root locale (flat) — recommended for single-language or GitHub-sourced repos

Inspired by VitePress and Starlight. One locale is designated `root` in config — its files live directly at the docs root with no subdirectory or URL prefix. Secondary locales keep their `/{code}/` prefix.

```text
docs/
├── explanation/
│   └── introduction.md     → /docs/explanation/introduction
├── how-to/
│   └── publishing.md       → /docs/how-to/publishing
├── tutorials/
│   └── quick-start.md      → /docs/tutorials/quick-start
└── ar/
    └── explanation/
        └── introduction.md → /docs/ar/explanation/introduction
```

Config:

```php
'locales' => [
    'root' => ['label' => 'English', 'lang' => 'en', 'dir' => 'ltr'],
    'ar'   => ['label' => 'العربية', 'lang' => 'ar', 'dir' => 'rtl'],
],
```

### Classic locale-prefixed — all locales in subdirectories

Omit the `root` key. Every locale, including the primary one, lives in its own subdirectory. This is the legacy behaviour and continues to work unchanged.

```text
docs/
├── en/
│   └── getting-started.md  → /docs/en/getting-started
└── ar/
    └── getting-started.md  → /docs/ar/getting-started
```

Config:

```php
'locales' => [
    'en' => ['label' => 'English', 'lang' => 'en', 'dir' => 'ltr'],
    'ar' => ['label' => 'العربية', 'lang' => 'ar', 'dir' => 'rtl'],
],
```

### Versioned structure

Add version directories above the locale layout. Declare versions explicitly in config — no automatic directory scanning.

```text
docs/
├── v1.0/
│   ├── getting-started.md          → /docs/v1.0/getting-started  (root locale)
│   └── ar/
│       └── getting-started.md      → /docs/v1.0/ar/getting-started
└── v2.0/
    └── getting-started.md          → /docs/v2.0/getting-started
```

Config:

```php
'locales'  => [
    'root' => ['label' => 'English', 'lang' => 'en', 'dir' => 'ltr'],
    'ar'   => ['label' => 'العربية', 'lang' => 'ar', 'dir' => 'rtl'],
],
'versions' => ['v1.0', 'v2.0'],
```

### Monolingual (single language, no locale UI)

Configure only a `root` locale with no secondary entries. The language picker is hidden automatically.

```text
docs/
├── getting-started.md   → /docs/getting-started
└── reference/
    └── api.md           → /docs/reference/api
```

Config:

```php
'locales' => [
    'root' => ['label' => 'English', 'lang' => 'en', 'dir' => 'ltr'],
],
'versions' => [],
```

## GitHub Source

Pertuk can render markdown stored in a GitHub repository instead of the local filesystem. Set `PERTUK_SOURCE=github` and configure the repo, branch, and path. The package syncs the full directory tree into `storage/app/pertuk/github/` during `pertuk:build` and falls back to on-demand single-file fetches at runtime.

```env
PERTUK_SOURCE=github
PERTUK_DOCS_REPO=Xoshbin/asyar-launcher
PERTUK_DOCS_BRANCH=main
PERTUK_DOCS_PATH=docs
# Optional — required for private repos, recommended to avoid the 60/hr anonymous rate limit.
PERTUK_DOCS_TOKEN=ghp_xxx
```

The GitHub source works with all directory structures above — including root locale (flat) layouts. Run `php artisan pertuk:build` as part of your deploy to sync changes.

## Migrating from the old locale config

If you are upgrading from a version that used `supported_locales`, `default_locale`, `rtl_locales`, and `locale_labels`, replace them with the unified `locales` map. No files need to move if you omit the `root` key.

```php
// Before
'supported_locales' => ['en', 'ar'],
'default_locale'    => 'en',
'rtl_locales'       => ['ar'],
'locale_labels'     => ['en' => 'English', 'ar' => 'العربية'],

// After (classic prefix mode — no files to move)
'locales' => [
    'en' => ['label' => 'English', 'lang' => 'en', 'dir' => 'ltr'],
    'ar' => ['label' => 'العربية', 'lang' => 'ar', 'dir' => 'rtl'],
],
'versions' => [],
```

Also replace `exclude_versions` with an explicit `versions` array — list only the versions you want to expose.

If you published the vendor views (`php artisan vendor:publish --tag="pertuk-views"`), re-publish them to get the updated templates, or replace any `route('pertuk.docs.show', ['locale' => ..., 'slug' => ...])` calls with `\Xoshbin\Pertuk\Support\PertukUrl::doc($slug)`.

## Front Matter

```yaml
---
title: "Getting Started"
order: 1
---
# Getting Started

Your content here...
```

## Interactive Components (Alpine.js)

### Tabs

```html
<x-pertuk-tabs>
<x-pertuk-tab name="PHP">

```php
echo "Hello World";
```

</x-pertuk-tab>
<x-pertuk-tab name="JS">

```javascript
console.log("Hello World");
```

</x-pertuk-tab>
</x-pertuk-tabs>
```

### Accordion

```html
<x-pertuk-accordion>
<x-pertuk-accordion-item title="Can I customize the design?">

Yes! Publish the views to match your brand.

</x-pertuk-accordion-item>
</x-pertuk-accordion>
```

## Admonitions

```markdown
::: tip
This is a helpful tip.
:::

::: warning
Be careful with this setting.
:::

::: danger
This action cannot be undone.
:::
```

## Assets & Images

Place images in an `assets/` directory at the docs root and reference them with relative paths:

```markdown
![My Feature](../assets/my-feature.png)
```

Pertuk rewrites these paths to `/docs/assets/filename.png` automatically.

**Directory conflict warning:** Do not create a physical `public/docs` directory. In Nginx, physical directories take precedence over Laravel routes, causing `403 Forbidden` errors.

## Performance

Pre-render all documentation to cache during deployment:

```bash
php artisan pertuk:build
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Xoshbin](https://github.com/xoshbin)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
