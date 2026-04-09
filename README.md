# Pertuk - Laravel Documentation Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/xoshbin/pertuk.svg?style=flat-square)](https://packagist.org/packages/xoshbin/pertuk)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/xoshbin/pertuk/run-tests?label=tests)](https://github.com/xoshbin/pertuk/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/xoshbin/pertuk/fix-php-code-style-issues?label=code%20style)](https://github.com/xoshbin/pertuk/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/xoshbin/pertuk.svg?style=flat-square)](https://packagist.org/packages/xoshbin/pertuk)

Pertuk is a powerful Laravel documentation package that provides a complete documentation system with multi-language support, markdown processing, search functionality, and a beautiful, responsive UI.

![img.png](assets/screenshot.jpeg)

## Example Usage

You can see a live example of Pertuk in action at [https://kezi.amro.tech/docs/en](https://kezi.amro.tech/docs/en)

## Features

-   📖 **Premium Markdown**: Full CommonMark and GitHub Flavored Markdown support
-   🎨 **Shiki Syntax Highlighting**: Server-side, VS-Code quality syntax highlighting
-   🌍 **Multi-Language Support**: Built-in support for English, Kurdish, and Arabic 
-   🔢 **Versioning Support**: Full support for multiple documentation versions with a built-in version picker
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

## Configuration

This is the contents of the published config file:

```php
return [
    // Root folder for documentation files.
    // Place markdown files under per-locale folders (e.g., docs/en/payments.md).
    'root' => base_path('docs'),

    // Default sort order when front matter 'order' is missing
    'default_order' => 1000,

    // Excluded files or folders (relative to root) for file listing
    'exclude_directories' => [
        '.DS_Store',
        'README.md',
        'Developers',
    ],

    // Excluded version directories (relative to root)
    'exclude_versions' => [
        'v0.x',
        'beta',
    ],

    // Cache TTL (seconds) for parsed HTML & metadata
    'cache_ttl' => 3600,

    // Enable or disable the documentation system
    'enabled' => true,

    // Route prefix for documentation
    'route_prefix' => 'docs',

    // Route name prefix
    'route_name_prefix' => 'pertuk.docs.',

    // Route middleware
    'middleware' => ['web'],

    // GitHub Repo & Branch for "Edit on GitHub" links
    'github_repo' => env('PERTUK_GITHUB_REPO', 'username/repo'),
    'github_branch' => env('PERTUK_GITHUB_BRANCH', 'main'),
    'github_path' => null, // Folder path in repo where docs are located

    // Assets directory (relative to documentation root)
    'assets_path' => 'assets',

    // External Links
    'github_url' => env('PERTUK_GITHUB_URL', ''),
    'discord_url' => env('PERTUK_DISCORD_URL', ''),
];
```

### GitHub source (alternative to local files)

Pertuk can render markdown stored in a GitHub repository instead of the local filesystem. Set `PERTUK_SOURCE=github` and configure the repo, branch, and path. The package syncs the full directory tree into `storage/app/pertuk/github/` during `pertuk:build` and falls back to on-demand single-file fetches at runtime for anything not yet synced.

```env
PERTUK_SOURCE=github
PERTUK_DOCS_REPO=Xoshbin/asyar-launcher
PERTUK_DOCS_BRANCH=main
PERTUK_DOCS_PATH=docs
# Optional — required for private repos, recommended for public repos to avoid the 60/hr anonymous rate limit.
PERTUK_DOCS_TOKEN=ghp_xxx
```

Versioning, multi-locale, assets, and search all work identically to the local driver because the synced tree mirrors the repo's directory structure.

Run `php artisan pertuk:build` as part of your deploy to sync changes. Files added upstream after the last sync are fetched on first request; modified or deleted files require another `pertuk:build`.

## Quick start

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

4. Ensure you have the frontend dependencies installed if you are building assets yourself:

```bash
npm install minisearch alpinejs @alpinejs/collapse
```

5. Create a `docs/en` directory and add a markdown file, e.g. `docs/en/getting-started.md`.

6. Visit your docs at `/docs` (redirects to default locale) or `/docs/en` directly.

-   **Customization**: Publish the views to customize the layout and markup:

```bash
php artisan vendor:publish --tag="pertuk-views"
```

7. (Optional) Pre-render documentation for performance:
```bash
php artisan pertuk:build
```

## Usage

### Versioning & Directory Structure

Pertuk supports both **versioned** and **flat** directory structures. It automatically detects which one you are using.

#### Option 1: Versioned Structure (Recommended)
Files are organized by version and then by locale: `docs/{version}/{locale}/{slug}.md`.

```text
docs/
├── v1.0/                      # Version 1.0
│   ├── en/                    # English
│   │   ├── getting-started.md
│   │   └── sidebar.json       # Optional sidebar config
│   └── ckb/                    # Kurdish (RTL)
│       └── getting-started.md
└── v2.0/                      # Version 2.0
    └── en/
        └── index.md
```

#### Option 2: Flat Structure (Simple)
If you don't need multiple versions, you can place your locale folders directly in the root: `docs/{locale}/{slug}.md`.

```text
docs/
├── en/                        # English
│   └── getting-started.md
└── ckb/                        # Kurdish (Sorani) (RTL)
    └── getting-started.md
```

### Automatic Version Discovery
You don't need to manually list versions in your config file. Pertuk will scan your `root` directory for any subfolders that contain a locale directory (like `en`, `ar`, or `ckb`) and automatically populate the version picker in the UI.

### Front Matter

Add YAML front matter to your markdown files for metadata:

```yaml
---
title: "Getting Started"
order: 1
---
# Getting Started

Your markdown content here...
```

### Interactive Components (Alpine.js)

Pertuk includes built-in interactive components powered by Alpine.js. These can be used directly in your Markdown files.

#### Tabs

Use tabs to group related content, like code examples in different languages.

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

#### Accordion

Use accordions for collapsible sections like FAQs.

```html
<x-pertuk-accordion>
<x-pertuk-accordion-item title="Can I customize the design?">

Yes! You can publish the views and CSS to match your brand's identity.

</x-pertuk-accordion-item>
<x-pertuk-accordion-item title="What about performance?">

Pertuk uses intelligent caching and server-side rendering for lightning-fast speeds.

</x-pertuk-accordion-item>
</x-pertuk-accordion>
```

### Admonitions

Use special blocks for callouts:

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

### Multi-Language Support

Pertuk supports multiple languages within each documentation version. Docs must be placed in a subdirectory matching the locale code defined in `config/pertuk.php` inside the version folder.

```
docs/
├── v1.0/
│   ├── en/
│   │   └── intro.md
│   └── ckb/
│       └── intro.md
└── v0.9/
    └── en/
        └── intro.md
```

### Performance & Deployment

To ensure maximum performance in production, you can pre-render all documentation files into the cache. This eliminates the need for parsing Markdown on the first request.

Run the following command during your deployment process:

```bash
php artisan pertuk:build
```

### Assets & Images

Pertuk comes with a built-in asset serving system. This means you can keep your images and screenshots directly inside your documentation repository instead of the `public/` folder of your main application.

**Best Practice:**
Place your images in an `assets/` directory at the root of your documentation (e.g., `docs/assets/`). You can then reference them in your markdown using a relative path:

```markdown
![My Feature](../assets/my-feature.png)
```

Pertuk will automatically rewrite these relative paths to point to a secure asset route (`/docs/assets/filename.png`), ensuring they render correctly even when the documentation is nested.

**Directory Conflicts:**
When using a route prefix like `/docs`, ensure you **do not** have a physical directory named `public/docs` in your project. In production environments (like Nginx), physical directories take precedence over Laravel routes, which may result in a `403 Forbidden` error.

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
