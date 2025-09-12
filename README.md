# Pertuk - Laravel Documentation Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/xoshbin/pertuk.svg?style=flat-square)](https://packagist.org/packages/xoshbin/pertuk)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/xoshbin/pertuk/run-tests?label=tests)](https://github.com/xoshbin/pertuk/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/xoshbin/pertuk/fix-php-code-style-issues?label=code%20style)](https://github.com/xoshbin/pertuk/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/xoshbin/pertuk.svg?style=flat-square)](https://packagist.org/packages/xoshbin/pertuk)

Pertuk is a powerful Laravel documentation package that provides a complete documentation system with multi-language support, markdown processing, search functionality, and a beautiful, responsive UI.

![img.png](assets/screenshot.jpeg)

## Features

-   📖 **Markdown Processing**: Full CommonMark and GitHub Flavored Markdown support
-   🌍 **Multi-Language Support**: Built-in support for English, Kurdish, and Arabic 
-   🔍 **Search Functionality**: Built-in search with JSON index
-   🎨 **Beautiful UI**: Responsive design with dark mode support
-   📱 **Mobile Friendly**: Optimized for all device sizes
-   🗂️ **Auto Table of Contents**: Automatic TOC generation from headings
-   💾 **Caching**: Intelligent caching for performance
-   🧭 **Breadcrumbs**: Automatic breadcrumb navigation
-   🏷️ **Front Matter Support**: YAML front matter for metadata

## Configuration

This is the contents of the published config file:

```php
return [
    // Root folder for documentation files
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

2. (Optional) Publish the config:

```bash
php artisan vendor:publish --tag="pertuk-config"
```

3. Create a `docs` directory and add a markdown file, e.g. `docs/getting-started.md`.

4. Visit your docs at `/docs` (or `/{route_prefix}` if you changed `pertuk.route_prefix`).

-   Optional: publish the views to customize the layout and markup:

```bash
php artisan vendor:publish --tag="pertuk-views"
```

## Usage

### Document Structure

```
docs/
├── getting-started.md
├── User Guide/
│   ├── installation.md         # default (en)
│   ├── installation.ckb.md     # Kurdish
│   ├── installation.ar.md      # Arabic
│   └── configuration.md
├── Developer Guide/
│   ├── api.md
│   └── examples.md
└── advanced.md
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

### Multi-Language Support

Create language-specific versions by adding locale suffixes:

```
docs/
├── getting-started.md       # English (default)
├── getting-started.ckb.md   # Kurdish
└── getting-started.ar.md    # Arabic
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
