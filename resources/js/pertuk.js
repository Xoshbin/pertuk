

import Alpine from "alpinejs";
import collapse from "@alpinejs/collapse";
import MiniSearch from "minisearch";

window.Alpine = Alpine;
Alpine.plugin(collapse);

/**
 * Documentation JavaScript functionality
 */
class DocsManager {
    constructor() {
        this.init();
    }

    init() {
        this.initAlpine();
        this.initThemeToggle();
        this.initSyntaxHighlighting();
        this.initSearch();
        this.initTableOfContents();
        this.initKeyboardShortcuts();
        this.initGlobalLanguageSelector();
    }

    /**
     * Initialize Alpine.js components for interactive elements
     */
    initAlpine() {
        Alpine.data("pertukTabs", () => ({
            activeTab: 0,
            tabs: [],
            init() {
                // Collect tabs from the DOM
                const tabElements = Array.from(
                    this.$el.querySelectorAll("x-pertuk-tab")
                );
                this.tabs = tabElements.map((el, i) => ({
                    name: el.getAttribute("name") || `Tab ${i + 1}`,
                    index: i,
                    el: el,
                }));

                // Injected header
                if (
                    !this.$el.querySelector(".pertuk-tabs-header") &&
                    this.tabs.length > 0
                ) {
                    const header = document.createElement("div");
                    header.className = "pertuk-tabs-header";
                    this.tabs.forEach((tab, i) => {
                        const btn = document.createElement("button");
                        btn.type = "button";
                        btn.innerText = tab.name;
                        btn.className = i === 0 ? "active" : "";
                        btn.onclick = () => (this.activeTab = i);
                        header.appendChild(btn);
                        tab.btn = btn;
                    });
                    this.$el.insertBefore(header, this.$el.firstChild);
                }

                this.$watch("activeTab", (val) => {
                    this.tabs.forEach((tab, i) => {
                        tab.el.style.display = i === val ? "block" : "none";
                        if (tab.btn) {
                            tab.btn.classList.toggle("active", i === val);
                        }
                    });
                });

                // Set initial state
                this.tabs.forEach((tab, i) => {
                    tab.el.style.display =
                        i === this.activeTab ? "block" : "none";
                });
            },
        }));

        Alpine.data("pertukAccordion", () => ({
            activeItem: null,
            init() {
                const items = Array.from(
                    this.$el.querySelectorAll("x-pertuk-accordion-item")
                );
                items.forEach((item, i) => {
                    const title =
                        item.getAttribute("title") || `Item ${i + 1}`;
                    const content = item.innerHTML;

                    // Wrap content and add header
                    item.innerHTML = `
                        <button type="button" class="pertuk-accordion-item-header" @click="toggle(${i})">
                            <span>${title}</span>
                            <svg class="pertuk-accordion-icon" :class="isOpen(${i}) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div class="pertuk-accordion-item-content" x-show="isOpen(${i})" x-collapse>
                            <div class="pertuk-accordion-body">${content}</div>
                        </div>
                    `;
                    item.classList.add("pertuk-accordion-item");
                });
            },
            toggle(id) {
                this.activeItem = this.activeItem === id ? null : id;
            },
            isOpen(id) {
                return this.activeItem === id;
            },
        }));

        if (!window.AlpineStarted) {
            Alpine.start();
            window.AlpineStarted = true;
        }
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
        // Syntax highlighting is now handled server-side by Shiki

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
                const data = await res.json();

                index = new MiniSearch({
                    fields: ["title", "heading", "content"], // fields to index for full-text search
                    storeFields: [
                        "slug",
                        "title",
                        "heading",
                        "content",
                        "anchor",
                    ], // fields to return with search results
                    searchOptions: {
                        boost: { title: 2, heading: 1.5, content: 1 },
                        fuzzy: 0.2,
                        prefix: true,
                    },
                });

                index.addAll(data);
            } catch (e) {
                console.warn("Failed to load search index:", e);
                index = null;
            }
            return index;
        };

        const escapeHtml = (text) => {
            if (!text) return "";
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
                .map((it) => {
                    const href = it.anchor
                        ? `/docs/${it.slug}#${it.anchor}`
                        : `/docs/${it.slug}`;
                    const displayTitle = it.heading
                        ? `${it.title} > ${it.heading}`
                        : it.title;
                    const excerpt = it.content
                        ? it.content.substring(0, 100) + "..."
                        : "";

                    return `
                <a class="block rounded-md px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-800" href="${href}">
                    <div class="font-medium text-gray-900 dark:text-white">${escapeHtml(
                        displayTitle
                    )}</div>
                    <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">${escapeHtml(
                        excerpt
                    )}</div>
                </a>
            `;
                })
                .join("");
            results.classList.remove("hidden");
        };

        const search = (query) => {
            if (!query.trim()) {
                render([]);
                return;
            }

            ensureIndex().then((idx) => {
                if (!idx) return;
                const matches = idx.search(query);
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
     * Table of Contents active section highlighting using Intersection Observer
     */
    initTableOfContents() {
        const tocLinks = document.querySelectorAll("[data-toc-link]");
        if (!tocLinks.length) return;

        const headings = Array.from(
            document.querySelectorAll("h2[id], h3[id]")
        );
        if (!headings.length) return;

        // Use Intersection Observer to detect which heading is in view
        const observerOptions = {
            root: null,
            rootMargin: "-100px 0px -70% 0px", // Trigger when heading is in the top 30% of the viewport
            threshold: 0,
        };

        const headingStates = new Map();

        const updateActiveLink = () => {
            // Find headings that are "active" (intersecting)
            const activeHeadings = headings.filter((h) =>
                headingStates.get(h.id)
            );

            let currentId = null;

            if (activeHeadings.length > 0) {
                // If there are intersecting headings, pick the first one (highest in DOM)
                currentId = activeHeadings[0].id;
            } else {
                // If no headings are intersecting, it might be because we're between headings
                // Find the last heading that is above the viewport
                const topHeadings = headings.filter(
                    (h) => h.getBoundingClientRect().top < 100
                );
                if (topHeadings.length > 0) {
                    currentId = topHeadings[topHeadings.length - 1].id;
                }
            }

            if (!currentId) return;

            tocLinks.forEach((link) => {
                const id = link.getAttribute("data-toc-link");
                if (id === currentId) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                headingStates.set(entry.target.id, entry.isIntersecting);
            });
            updateActiveLink();
        }, observerOptions);

        headings.forEach((heading) => observer.observe(heading));

        // Smooth scroll to heading when TOC link is clicked
        tocLinks.forEach((link) => {
            link.addEventListener("click", (e) => {
                e.preventDefault();
                const targetId = link.getAttribute("data-toc-link");
                const targetElement = document.getElementById(targetId);

                if (targetElement) {
                    // Temporarily disconnect observer to prevent jumping highlights during smooth scroll
                    // but actually most people prefer the highlight following the content.
                    // We'll just rely on the smooth scroll and the observer.

                    const offset = 80; // Offset for fixed header if any
                    const bodyRect = document.body.getBoundingClientRect().top;
                    const elementRect =
                        targetElement.getBoundingClientRect().top;
                    const elementPosition = elementRect - bodyRect;
                    const offsetPosition = elementPosition - offset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: "smooth",
                    });

                    // Update URL hash
                    history.pushState(null, null, `#${targetId}`);
                }
            });
        });

        // Initial check in case we start at a hash
        updateActiveLink();
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
