# Changelog

All notable changes to `:package_name` will be documented in this file.

## [Unreleased]

### Added
- **Root locale (flat) support — VitePress/Starlight style.** One locale can now be designated `root` in the `locales` config. Its files live directly at the docs root with no subdirectory or URL prefix. Secondary locales keep their `/{code}/` URL prefix. See README for directory layout examples.
- **`Xoshbin\Pertuk\Support\LocaleConfig`** — centralised accessor for all locale config. Replaces scattered `config('pertuk.supported_locales')`, `config('pertuk.rtl_locales')`, etc. calls throughout views and services.
- **`Xoshbin\Pertuk\Support\PathResolver`** — single resolution point that parses any incoming URL path into `[locale, version, slug]` using the Starlight routing algorithm (root locale = path not starting with a secondary locale prefix).
- **`Xoshbin\Pertuk\Support\PertukUrl`** — URL generation helper. Root locale gets no locale prefix; secondary locales get `/{code}/`. Use `PertukUrl::doc($slug)` in views instead of `route('pertuk.docs.show', [...])`.
- **`versions` config key.** Declare the versions you want to expose as an explicit array (`['v1.0', 'v2.0']`). Empty array disables versioning.
- **GitHub documentation source.** Pertuk can now render markdown from a GitHub repository instead of a local directory. Set `PERTUK_SOURCE=github` and configure `PERTUK_DOCS_REPO`, `PERTUK_DOCS_BRANCH`, `PERTUK_DOCS_PATH`, and optionally `PERTUK_DOCS_TOKEN`. See README for details.
- New `Xoshbin\Pertuk\Services\Source\SourceDriver` interface with `LocalSource` (default) and `GitHubSource` implementations. Registered as a singleton in the container.

### Changed
- **`locales` config key replaces the old locale keys** (see Breaking Changes).
- Routing simplified to a single catch-all route `/{path?}` delegating to `PathResolver`. Explicit per-locale/per-version routes removed.
- `DocumentationService::getFiles()` now correctly excludes secondary locale subdirectories from root locale listings, and vice versa.
- `DocumentationService` now reads its root path from the bound `SourceDriver` instead of `config('pertuk.root')` directly. `DocumentationService::getAvailableVersions()` is now a thin shim that delegates to the driver.
- `DocumentController::asset()` delegates asset resolution to `SourceDriver::ensureAsset()` so the GitHub driver can download and cache missing assets on demand.
- `php artisan pertuk:build` now calls `SourceDriver::warmAll()` before pre-rendering — a no-op for the local driver, a full tree sync for the GitHub driver.

### Removed
- `Xoshbin\Pertuk\Services\Source\ScansVersions` trait — replaced by the explicit `versions` config array.

### Deprecated
- The top-level `pertuk.root` config key. Use `pertuk.sources.local.root` instead. The legacy key continues to work as a fallback and will be removed in a future major release.

### Breaking Changes
- **`locales` map replaces four separate config keys.** Replace:
  ```php
  // Before
  'supported_locales' => ['en', 'ar'],
  'default_locale'    => 'en',
  'rtl_locales'       => ['ar'],
  'locale_labels'     => ['en' => 'English', 'ar' => 'العربية'],

  // After (classic prefix mode — no file moves required)
  'locales' => [
      'en' => ['label' => 'English', 'lang' => 'en', 'dir' => 'ltr'],
      'ar' => ['label' => 'العربية', 'lang' => 'ar', 'dir' => 'rtl'],
  ],
  ```
  To enable root locale (flat layout), use the `root` key instead of a locale code for the primary language:
  ```php
  'locales' => [
      'root' => ['label' => 'English', 'lang' => 'en', 'dir' => 'ltr'],
      'ar'   => ['label' => 'العربية', 'lang' => 'ar', 'dir' => 'rtl'],
  ],
  ```
- **`exclude_versions` config key removed.** Replace with an explicit `versions` array listing only the versions you want to expose.
- **Published views** using `route('pertuk.docs.show', ['locale' => ..., 'slug' => ...])` must be updated to use `\Xoshbin\Pertuk\Support\PertukUrl::doc($slug)`. Re-publish with `php artisan vendor:publish --tag="pertuk-views" --force` or update manually.

## v0.1.6 - 2026-03-31

### What's Changed

* feat: add theme configuration and support for system-based color scheme switching by @Xoshbin in https://github.com/Xoshbin/pertuk/pull/15
* feat: add AsciiExtension to render ascii-art code blocks with custom styling by @Xoshbin in https://github.com/Xoshbin/pertuk/pull/16
* feat: improve code block styling and add markdown rendering feature tests by @Xoshbin in https://github.com/Xoshbin/pertuk/pull/17
* chore: update Tailwind CSS distribution and source files to v4.1.13 by @Xoshbin in https://github.com/Xoshbin/pertuk/pull/18

**Full Changelog**: https://github.com/Xoshbin/pertuk/compare/0.1.5...v0.1.6

## v0.1.5 - 2026-02-07

1. make package links dynamic by moving them to
2. feat implement documentation asset management and serving

**Full Changelog**: https://github.com/Xoshbin/pertuk/compare/0.1.4...0.1.5

## v0.1.4 - 2026-02-07

feat: Introduce configurable route name prefix and add asset conflict warnings to the README.
**Full Changelog**: https://github.com/Xoshbin/pertuk/compare/0.1.3...0.1.4

## v0.1.3 - 2026-02-06

fixed the issue by adding a github_path configuration to the pertuk package and setting it to an empty string in the kezi application configuration. This ensures that the "Edit on GitHub" link points to the correct location in the kezi-docs repository, removing the incorrect docs/ prefix.
**Full Changelog**: https://github.com/Xoshbin/pertuk/compare/0.1.2...0.1.3

## v0.1.2 - 2026-01-31

### What's Changed

* feat: redesign documentation layout with scoped navigation and fluid grid by @Xoshbin in https://github.com/Xoshbin/pertuk/pull/8
* fix: dynamic search paths for localized and versioned docs by @Xoshbin in https://github.com/Xoshbin/pertuk/pull/9
* Develop by @Xoshbin in https://github.com/Xoshbin/pertuk/pull/10

**Full Changelog**: https://github.com/Xoshbin/pertuk/compare/0.1.1...0.1.2

## v0.1.1 - 2026-01-31

This update focuses on architectural excellence, long-term maintainability, and the introduction of a complete documentation versioning system.

### 🏗 Major Architectural Refactoring

We have significantly refactored the core engine to align with the highest Laravel coding standards.

- **Service Splitting**: The monolithic `DocumentationService` has been decomposed into smaller, single-responsibility services:
  
  - `MarkdownRenderer`: Handles all Markdown to HTML conversion logic.
  - `ContentProcessor`: Manages file discovery, path resolution, and metadata extraction.
  
- **Strict Typing**: Applied `declare(strict_types=1);` across all controllers and services to ensure type safety and code quality.
  
- **Controller Cleanup**: Controllers have been refactored to be "lean," moving business logic into the service layer and utilizing modern Laravel idioms.
  
- **Logic-Free Views**: Refactored Blade components and views to remove inline logic, relying on named routes and pre-processed data.
  

### 🚀 New Features

- **Documentation Versioning**:
  - Full support for multiple versions of documentation.
  - Automatic version discovery from the filesystem.
  - UI Version Selector component for easy switching.
  - Version-aware routing and locale preservation during version switches.
  

### 🛠 Improvements

- **Named Routes**: Replaced hardcoded URIs with a robust named route system (`pertuk.docs.*`), making the package more flexible for integration.
- **Optimized Routing**: Streamlined [routes/web.php](file:///Users/khoshbin/PhpstormProjects/pertuk/routes/web.php) by removing closures and utilizing controller groups for better performance and readability.

### 🐞 Bug Fixes & Maintenance

- **Version Selector Fix**: Resolved an issue where the version selector would "stick" to the latest version on index pages.
- **PHPStan & Code Quality**: Fixed several static analysis warnings and resolved deprecated usage of the `Request::get()` method.
- **CI Test Robustness**: Improved the `FileReadingTest` to be more resilient to environment-specific file permission issues in CI environments.
- **Test Suite Health**: All 59 tests are currently passing, ensuring a stable release.


---

**Full Changelog**: https://github.com/Xoshbin/pertuk/compare/0.1.0...0.1.1

## v0.1.0 - 2026-01-31

### Release 0.1.0

This release introduces a major overhaul to the documentation engine, focusing on multi-language support, performance, and developer experience.

#### 🚀 Key Enhancements

* **Strict Multi-Language Architecture**: Pertuk now enforces a strict subdirectory structure for documentation (`docs/{locale}/...`). This ensures cleaner separation of languages and robust locale detection.
* **Shiki Syntax Highlighting**: Replaced `highlight.js` with **Shiki** for superior, high-fidelity server-side syntax highlighting. Your code blocks now look exactly like they do in your IDE.
* **Admonition Blocks**: Added native support for GitHub-flavored alerts and custom admonitions. You can now easily add `Note`, `Tip`, `Warning`, and `Danger` blocks to your markdown.
* **Versioning Support**: Introduced full support for documentation versioning with dynamic discovery and fallback for flat structures.
* **Static Build Command**: Introduced the `php artisan pertuk:build` command. This pre-renders your documentation to static HTML/Cache, significantly improving load times for end-users.

#### 🛠 Improvements

* **Robust Test Suite**: The test suite has been completely refactored to align with the new strict locale logic, ensuring higher stability and preventing regressions.
* **Asset Management**: Improved handling of static assets and images within localized documentation folders.

#### ⚠️ Breaking Changes

* **Directory Structure**: Documentation files must now be placed inside locale-specific subdirectories (e.g., `docs/en/index.md` instead of `docs/index.md`). Existing projects will need to move their markdown files into the appropriate language folder (defaults to [en].

**Full Changelog**: https://github.com/Xoshbin/pertuk/compare/0.0.4...0.1.0
