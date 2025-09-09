---
applyTo: "**"
---

make sure to write tests for each new feature and bug fixes.

before finishing each task run both full test vendor/bin/pest --parallel and ./vendor/bin/phpstan analyse if there are any errors fix them then finish the task

After each task you complete, output:
Commit: <imperative message, ≤72 chars, specific, no punctuation, ref issue if any>
Branch: <type>/<short-kebab-description>
Types: feature, bugfix, hotfix, release
Branch rules: lowercase, hyphens, ≤6 words
