# Copilot Instructions for Pertuk

## Project Overview

Pertuk is a Laravel package for building multi-language documentation sites with markdown processing, search, caching, and a responsive UI. It is designed for easy integration and customization within Laravel projects.

## Technologies & Frameworks

-   **Laravel**: v12
-   **Tailwind CSS**: v4
-   **Pest**: v4
-   **Pint**: v1

## Architecture & Key Components

-   **DocumentationService** (`src/Services/DocumentationService.php`): Core logic for listing, retrieving, and indexing documentation files. Handles markdown parsing, front matter, multi-language, and caching.
-   **Controllers** (`src/Http/Controllers/`): Route requests for documentation pages, search, and rendering views.
-   **Views** (`resources/views/`): Blade templates for UI, including index, show, payments, and components.
-   **Config** (`config/pertuk.php`): Controls root docs folder, cache TTL, route prefix, exclusions, and middleware.
-   **Routes** (`routes/web.php`): Registers documentation routes, typically under `/docs`.
-   **Assets** (`resources/css/pertuk.css`, `resources/js/pertuk.js`): Custom styles and scripts for the documentation UI.

## Developer Workflows

-   **Install**: `composer require xoshbin/pertuk`
-   **Config Publish**: `php artisan vendor:publish --tag="pertuk-config"`
-   **Views Publish**: `php artisan vendor:publish --tag="pertuk-views"`
-   **Testing**: `composer test` (uses Pest, see `tests/`)
-   **Code Style**: Run Pint or PHPStan via vendor binaries for linting and static analysis.

## Patterns & Conventions

-   **Docs Location**: Markdown files live in `/docs` (outside package, in host app root).
-   **Multi-Language**: Use `.ar.md` (Arabic), `.ckb.md` (Kurdish) suffixes for translations.
-   **Front Matter**: YAML metadata at top of markdown files (`title`, `order`, etc.).
-   **Caching**: HTML and metadata are cached for performance; TTL set in config.
-   **Exclusions**: Files/folders listed in `exclude` config are ignored.
-   **Search**: JSON index built from docs for fast searching.
-   **UI**: Blade templates use responsive design and dark mode.

## Integration Points

-   **Laravel**: Relies on Laravel's service provider, config, routing, and view systems.
-   **Composer**: Managed as a Composer package.
-   **Testing**: Pest for tests, PHPStan for static analysis.

## Examples

-   To list docs: `DocumentationService::make()->list()`
-   To get a doc: `DocumentationService::make()->get('getting-started')`
-   To build search index: `DocumentationService::make()->buildIndex()`

## Key Files & Directories

-   `src/Services/DocumentationService.php` (core logic)
-   `config/pertuk.php` (settings)
-   `resources/views/` (UI)
-   `routes/web.php` (routes)
-   `tests/` (Pest tests)

## Tips for AI Agents

-   Tailwind css v4 does not user tailwind.config.js anymore.
-   Always check `config/pertuk.php` for project-specific settings.
-   Respect language suffixes and front matter in markdown files.
-   Use service methods for doc operations; avoid direct file access.
-   Follow Blade and Laravel conventions for UI and routing.
-   Use published config/views for customization.

---

If any section is unclear or missing, please provide feedback for improvement.
