# Changelog

All notable changes to `:package_name` will be documented in this file.

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
