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
-   🌍 **Multi-Language Support**: Built-in support for English, Kurdish, and Arabic 
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

    // Excluded files or folders (relative to root)
    'exclude' => [
        '.DS_Store',
        'README.md',
        'Developers'
    ],

    // Cache TTL (seconds) for parsed HTML & metadata
    'cache_ttl' => 3600,

    // Enable or disable the documentation system
    'enabled' => true,

    // Route prefix for documentation
    'route_prefix' => 'docs',

    // Route middleware
    'middleware' => [],
];
```

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

### Document Structure

Files must be organized by locale subdirectory.

```
docs/
├── en/                      # English (default)
│   ├── getting-started.md
│   ├── User Guide/
│   │   ├── installation.md
│   │   └── configuration.md
│   └── advanced.md
├── ckb/                     # Kurdish
│   ├── getting-started.md
│   └── User Guide/
│       ├── installation.md
│       └── configuration.md
└── ar/                      # Arabic
    └── getting-started.md
```

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

Docs must be placed in a subdirectory matching the locale code defined in `config/pertuk.php`. The structure is strict: `docs/{locale}/{slug}.md`.

```
docs/
├── en/
│   └── intro.md
├── ckb/
│   └── intro.md
└── ar/
    └── intro.md
```

### Performance & Deployment

To ensure maximum performance in production, you can pre-render all documentation files into the cache. This eliminates the need for parsing Markdown on the first request.

Run the following command during your deployment process:

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
