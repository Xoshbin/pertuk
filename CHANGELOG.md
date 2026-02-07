# Changelog

All notable changes to `:package_name` will be documented in this file.

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
