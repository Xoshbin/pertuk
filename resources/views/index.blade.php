<x-pertuk::pertuk-layout :title="'Documentation'" :current-version="$current_version ?? null" :items="$items">
    <x-slot:sidebar>
        @include('pertuk::components.sidebar', ['items' => $items])
    </x-slot:sidebar>

    <div class="docs-prose prose prose-slate dark:prose-invert max-w-none">
        <!-- Hero Section -->
        <div class="mb-12">
            <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl mb-4">
                {{ config('app.name') }} Documentation
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl">
                Comprehensive documentation for {{ config('app.name') }}, a modern Laravel-based accounting system with double-entry bookkeeping, multi-currency support, and advanced financial management features.
            </p>
        </div>

        <!-- Quick Start Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <div class="bg-gradient-to-br from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 rounded-lg p-6 border border-orange-200 dark:border-orange-800">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-500 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Start</h3>
                </div>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Get up and running with {{ config('app.name') }} in minutes.</p>
                <a href="{{ $current_version ? route('pertuk.docs.version.show', ['version' => $current_version, 'locale' => app()->getLocale(), 'slug' => 'getting-started']) : route('pertuk.docs.show', ['locale' => app()->getLocale(), 'slug' => 'getting-started']) }}" class="inline-flex items-center gap-2 text-orange-600 dark:text-orange-400 font-medium hover:text-orange-700 dark:hover:text-orange-300">
                    Get started
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">API Reference</h3>
                </div>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Complete API documentation and examples.</p>
                <a href="{{ $current_version ? route('pertuk.docs.version.show', ['version' => $current_version, 'locale' => app()->getLocale(), 'slug' => 'api']) : route('pertuk.docs.show', ['locale' => app()->getLocale(), 'slug' => 'api']) }}" class="inline-flex items-center gap-2 text-blue-600 dark:text-blue-400 font-medium hover:text-blue-700 dark:hover:text-blue-300">
                    View API docs
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg p-6 border border-green-200 dark:border-green-800">
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-500 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Examples</h3>
                </div>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Real-world examples and use cases.</p>
                <a href="{{ $current_version ? route('pertuk.docs.version.show', ['version' => $current_version, 'locale' => app()->getLocale(), 'slug' => 'examples']) : route('pertuk.docs.show', ['locale' => app()->getLocale(), 'slug' => 'examples']) }}" class="inline-flex items-center gap-2 text-green-600 dark:text-green-400 font-medium hover:text-green-700 dark:hover:text-green-300">
                    Browse examples
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>

        <!-- Documentation Sections (Context Grid) -->
        <div class="mb-16">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">
                {{ __('Browse by Topic') }}
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($groupedItems as $category => $categoryItems)
                    @php
                       $firstItem = $categoryItems->first();
                       $linkUrl = route('pertuk.docs.show', ['locale' => app()->getLocale(), 'slug' => $firstItem['slug']]);
                       if ($current_version) {
                           $linkUrl = route('pertuk.docs.version.show', ['version' => $current_version, 'locale' => app()->getLocale(), 'slug' => $firstItem['slug']]);
                       }
                           
                       $categoryName = str_replace('-', ' ', ucfirst($category));
                       $count = $categoryItems->count();
                    @endphp
                    
                    <a href="{{ $linkUrl }}" class="group block h-full p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl hover:border-orange-500/50 hover:shadow-lg hover:shadow-orange-500/10 transition-all duration-300">
                        <div class="flex flex-col h-full">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">
                                    {{ $categoryName }}
                                </h3>
                                <div class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg group-hover:bg-orange-100 dark:group-hover:bg-orange-900/30 transition-colors">
                                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-orange-600 dark:group-hover:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </div>
                            </div>
                            
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6 flex-grow">
                                {{ __('Explore') }} {{ strtolower($categoryName) }} {{ __('documentation') }}.
                            </p>
                            
                            <div class="flex items-center justify-between mt-auto pt-4 border-t border-gray-100 dark:border-gray-800">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ $count }} {{ Str::plural('article', $count) }}
                                </div>
                                <span class="text-sm font-semibold text-orange-600 dark:text-orange-400 flex items-center gap-1">
                                    {{ __('Read') }}
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Footer CTA -->
        <div class="mt-16 bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 rounded-lg p-8 text-center border border-orange-200 dark:border-orange-800">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                Need help getting started?
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Join our community or check out the GitHub repository for more resources.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @if(config('pertuk.github_url'))
                    <a
                        href="{{ config('pertuk.github_url') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-lg bg-gray-900 dark:bg-white px-4 py-2 text-sm font-medium text-white dark:text-gray-900 transition-colors hover:bg-gray-800 dark:hover:bg-gray-100"
                    >
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                        View on GitHub
                    </a>
                @endif
                @if(config('pertuk.discord_url'))
                    <a
                        href="{{ config('pertuk.discord_url') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                        </svg>
                        Join Discord
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-pertuk::pertuk-layout>

