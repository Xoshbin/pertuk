import hljs from "highlight.js/lib/core";

// Import only the languages you need to keep bundle size small
import javascript from "highlight.js/lib/languages/javascript";
import php from "highlight.js/lib/languages/php";
import sql from "highlight.js/lib/languages/sql";
import bash from "highlight.js/lib/languages/bash";
import json from "highlight.js/lib/languages/json";
import xml from "highlight.js/lib/languages/xml"; // For HTML/Blade
import css from "highlight.js/lib/languages/css";
import yaml from "highlight.js/lib/languages/yaml";
import markdown from "highlight.js/lib/languages/markdown";

// Register languages
hljs.registerLanguage("javascript", javascript);
hljs.registerLanguage("php", php);
hljs.registerLanguage("sql", sql);
hljs.registerLanguage("bash", bash);
hljs.registerLanguage("shell", bash); // Alias for bash
hljs.registerLanguage("json", json);
hljs.registerLanguage("html", xml);
hljs.registerLanguage("xml", xml);
hljs.registerLanguage("blade", xml); // Use XML highlighting for Blade
hljs.registerLanguage("css", css);
hljs.registerLanguage("yaml", yaml);
hljs.registerLanguage("yml", yaml); // Alias for yaml
hljs.registerLanguage("markdown", markdown);
hljs.registerLanguage("md", markdown); // Alias for markdown

/**
 * Documentation JavaScript functionality
 */
class DocsManager {
    constructor() {
        this.init();
    }

    init() {
        this.initThemeToggle();
        this.initSyntaxHighlighting();
        this.initSearch();
        this.initTableOfContents();
        this.initKeyboardShortcuts();
        this.initGlobalLanguageSelector();
    }

    /**
     * Theme toggle functionality
     */
    initThemeToggle() {
        const themeToggle = document.getElementById("theme-toggle");
        const html = document.documentElement;

        if (!themeToggle) return;

        // Check for saved theme preference or default to 'light'
        const savedTheme = localStorage.getItem("theme") || "light";

        // Apply theme immediately
        this.applyTheme(savedTheme);

        themeToggle.addEventListener("click", () => {
            const currentTheme = localStorage.getItem("theme") || "light";
            const newTheme = currentTheme === "dark" ? "light" : "dark";

            // Apply the new theme
            this.applyTheme(newTheme);
            localStorage.setItem("theme", newTheme);
        });
    }

    /**
     * Apply theme to document
     */
    applyTheme(theme) {
        const html = document.documentElement;

        if (theme === "dark") {
            html.classList.add("dark");
        } else {
            html.classList.remove("dark");
        }

        // Force a repaint to ensure styles are applied
        html.offsetHeight;
    }

    /**
     * Initialize syntax highlighting
     */
    initSyntaxHighlighting() {
        // Highlight all code blocks
        document.querySelectorAll("pre code").forEach((block) => {
            hljs.highlightElement(block);
        });

        // Add copy buttons to code blocks
        this.addCopyButtons();
    }

    /**
     * Add copy buttons to code blocks
     */
    addCopyButtons() {
        document.querySelectorAll("pre").forEach((pre) => {
            const button = document.createElement("button");
            button.className =
                "absolute top-2 right-2 p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors rounded-md hover:bg-gray-100 dark:hover:bg-gray-800";
            button.innerHTML = `
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            `;
            button.title = "Copy code";

            // Make pre relative for absolute positioning
            pre.style.position = "relative";
            pre.appendChild(button);

            button.addEventListener("click", () => {
                const code = pre.querySelector("code");
                if (code) {
                    // Try modern clipboard API first, fallback to legacy method
                    const copyText = async () => {
                        try {
                            if (
                                navigator.clipboard &&
                                navigator.clipboard.writeText
                            ) {
                                await navigator.clipboard.writeText(
                                    code.textContent
                                );
                            } else {
                                // Fallback for older browsers or insecure contexts
                                const textArea =
                                    document.createElement("textarea");
                                textArea.value = code.textContent;
                                textArea.style.position = "fixed";
                                textArea.style.left = "-999999px";
                                textArea.style.top = "-999999px";
                                document.body.appendChild(textArea);
                                textArea.focus();
                                textArea.select();
                                document.execCommand("copy");
                                textArea.remove();
                            }

                            // Show success feedback
                            button.innerHTML = `
                                <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            `;
                            setTimeout(() => {
                                button.innerHTML = `
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                `;
                            }, 2000);
                        } catch (err) {
                            console.warn("Failed to copy text: ", err);
                        }
                    };

                    copyText();
                }
            });
        });
    }

    /**
     * Enhanced search functionality
     */
    initSearch() {
        const input = document.getElementById("docs-search-input");
        const results = document.getElementById("docs-search-results");

        if (!input || !results) return;

        let index = null;
        let searchTimeout = null;

        const ensureIndex = async () => {
            if (index) return index;
            try {
                const res = await fetch("/docs/index.json", {
                    headers: { Accept: "application/json" },
                });
                index = await res.json();
            } catch (e) {
                console.warn("Failed to load search index:", e);
                index = [];
            }
            return index;
        };

        const escapeHtml = (text) => {
            const div = document.createElement("div");
            div.textContent = text;
            return div.innerHTML;
        };

        const render = (items) => {
            if (!items.length) {
                results.classList.add("hidden");
                results.innerHTML = "";
                return;
            }

            results.innerHTML = items
                .slice(0, 8)
                .map(
                    (it) => `
                <a class="block rounded-md px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-800" href="/docs/${
                    it.slug
                }">
                    <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(
                        it.title
                    )}</div>
                    <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">${escapeHtml(
                        it.excerpt
                    )}</div>
                </a>
            `
                )
                .join("");
            results.classList.remove("hidden");
        };

        const search = (query) => {
            if (!query.trim()) {
                render([]);
                return;
            }

            ensureIndex().then((idx) => {
                const q = query.toLowerCase();
                const matches = idx
                    .filter(
                        (it) =>
                            it.title.toLowerCase().includes(q) ||
                            (it.headings || []).some((h) =>
                                (h || "").toLowerCase().includes(q)
                            ) ||
                            (it.excerpt || "").toLowerCase().includes(q)
                    )
                    .sort((a, b) => {
                        // Prioritize title matches
                        const aTitle = a.title.toLowerCase().includes(q);
                        const bTitle = b.title.toLowerCase().includes(q);
                        if (aTitle && !bTitle) return -1;
                        if (!aTitle && bTitle) return 1;
                        return 0;
                    });
                render(matches);
            });
        };

        // Debounced search
        input.addEventListener("input", (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => search(e.target.value), 150);
        });

        input.addEventListener("focus", (e) => search(e.target.value));

        // Close search results when clicking outside
        document.addEventListener("click", (e) => {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.classList.add("hidden");
            }
        });
    }

    /**
     * Table of Contents active section highlighting
     */
    initTableOfContents() {
        const tocLinks = document.querySelectorAll("[data-toc-link]");
        if (!tocLinks.length) return;

        const headings = Array.from(
            document.querySelectorAll("h2[id], h3[id]")
        );
        if (!headings.length) return;

        let isScrolling = false;
        let scrollTimeout;

        const updateActiveTocLink = () => {
            if (isScrolling) return;

            const scrollY = window.scrollY;
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;

            let activeHeading = null;

            // If we're at the bottom of the page, highlight the last heading
            if (scrollY + windowHeight >= documentHeight - 10) {
                activeHeading = headings[headings.length - 1];
            } else {
                // Find the heading that's currently in view
                for (let i = headings.length - 1; i >= 0; i--) {
                    const heading = headings[i];
                    const rect = heading.getBoundingClientRect();

                    if (rect.top <= 100) {
                        activeHeading = heading;
                        break;
                    }
                }
            }

            // Update TOC link styles
            tocLinks.forEach((link) => {
                const isActive =
                    activeHeading &&
                    link.getAttribute("data-toc-link") === activeHeading.id;

                if (isActive) {
                    link.classList.remove(
                        "text-gray-600",
                        "dark:text-gray-400"
                    );
                    link.classList.add(
                        "bg-orange-50",
                        "text-orange-700",
                        "dark:bg-orange-900/20",
                        "dark:text-orange-400"
                    );
                } else {
                    link.classList.remove(
                        "bg-orange-50",
                        "text-orange-700",
                        "dark:bg-orange-900/20",
                        "dark:text-orange-400"
                    );
                    link.classList.add("text-gray-600", "dark:text-gray-400");
                }
            });
        };

        // Smooth scroll to heading when TOC link is clicked
        tocLinks.forEach((link) => {
            link.addEventListener("click", function (e) {
                e.preventDefault();
                const targetId = this.getAttribute("data-toc-link");
                const targetElement = document.getElementById(targetId);

                if (targetElement) {
                    isScrolling = true;

                    targetElement.scrollIntoView({
                        behavior: "smooth",
                        block: "start",
                    });

                    // Update URL hash
                    history.pushState(null, null, `#${targetId}`);

                    // Reset scrolling flag after animation
                    setTimeout(() => {
                        isScrolling = false;
                        updateActiveTocLink();
                    }, 1000);
                }
            });
        });

        // Throttled scroll listener
        window.addEventListener(
            "scroll",
            function () {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(updateActiveTocLink, 50);
            },
            { passive: true }
        );

        // Initial call
        updateActiveTocLink();
    }

    /**
     * Keyboard shortcuts
     */
    initKeyboardShortcuts() {
        document.addEventListener("keydown", (e) => {
            // Search shortcut (Cmd/Ctrl + K)
            if ((e.metaKey || e.ctrlKey) && e.key === "k") {
                e.preventDefault();
                const searchInput =
                    document.getElementById("docs-search-input");
                if (searchInput) {
                    searchInput.focus();
                }
            }

            // Escape to close search
            if (e.key === "Escape") {
                const results = document.getElementById("docs-search-results");
                const searchInput =
                    document.getElementById("docs-search-input");
                if (results) {
                    results.classList.add("hidden");
                }
                if (searchInput) {
                    searchInput.blur();
                }
            }
        });
    }

    /**
     * Initialize global language selector
     */
    initGlobalLanguageSelector() {
        const globalLangSelect = document.getElementById("global-lang-select");
        if (!globalLangSelect) return;

        // Set the current locale in the selector
        this.setCurrentLocaleInSelector(globalLangSelect);

        globalLangSelect.addEventListener("change", async (e) => {
            const selectedLocale = e.target.value;

            try {
                // Get CSRF token from meta tag or XSRF cookie
                let csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content");

                // If meta tag is empty, try to get from XSRF-TOKEN cookie
                if (!csrfToken) {
                    const xsrfCookie = document.cookie
                        .split(";")
                        .find((cookie) =>
                            cookie.trim().startsWith("XSRF-TOKEN=")
                        );

                    if (xsrfCookie) {
                        csrfToken = decodeURIComponent(
                            xsrfCookie.split("=")[1]
                        );
                        // Parse the Laravel encrypted cookie value
                        try {
                            const parsed = JSON.parse(atob(csrfToken));
                            csrfToken = parsed.value;
                        } catch (e) {
                            console.warn("Could not parse XSRF token:", e);
                        }
                    }
                }

                // Use GET request to avoid CSRF issues
                // This will redirect to the locale route and then back to the docs
                window.location.href = `/locale/${selectedLocale}?redirect=${encodeURIComponent(
                    window.location.pathname
                )}`;
            } catch (error) {
                console.error("Error setting locale:", error);
                // Reset the select to the previous value
                e.target.value = document.documentElement.lang.replace(
                    "-",
                    "_"
                );
            }
        });
    }

    /**
     * Set the current locale in the language selector based on HTML lang attribute
     */
    setCurrentLocaleInSelector(selectElement) {
        const currentLang = document.documentElement.lang || "en";

        // Convert language codes if needed (e.g., 'en-US' to 'en')
        const locale = currentLang.split("-")[0];

        // Set the select value to match current locale
        if (selectElement.querySelector(`option[value="${locale}"]`)) {
            selectElement.value = locale;
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => new DocsManager());
} else {
    new DocsManager();
}

export default DocsManager;
