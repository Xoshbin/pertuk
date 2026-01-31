@php
    /** @var array<int,array{slug:string,title:string}> $items */
    /** @var string $active */

    // 1. Determine Current Context
    $activeParts = explode('/', $active ?? '');
    $currentContext = count($activeParts) > 1 ? $activeParts[0] : 'General';

    // 2. Filter Items for Context
    $contextItems = collect($items)->filter(function ($item) use ($currentContext) {
        $parts = explode('/', $item['slug']);
        $itemContext = count($parts) > 1 ? $parts[0] : 'General';
        return $itemContext === $currentContext;
    });

    // 3. Group by Sub-category (if exists) or fallback to 'Main'
    $groupedItems = $contextItems->groupBy(function ($item) use ($currentContext) {
        // Remove context from slug to find sub-category
        $relSlug = $currentContext === 'General' ? $item['slug'] : substr($item['slug'], strlen($currentContext) + 1);
        
        if (empty($relSlug)) {
            return 'Main';
        }

        $parts = explode('/', $relSlug);
        
        return (count($parts) > 1 && !empty($parts[0])) ? $parts[0] : 'Main';
    });

    // Sort groups: 'Main' first, then alphabetical or bespoke order
    $groupedItems = $groupedItems->sortBy(function ($items, $key) {
        return $key === 'Main' ? '000' : $key;
    });

    $locale = app()->getLocale();
    $isRtl = in_array($locale, config('pertuk.rtl_locales', ['ar', 'ckb']));
    $routePrefix = config('pertuk.route_prefix', 'docs');
@endphp

<nav class="space-y-6" x-data="{ search: '' }">
    <!-- Sidebar Search -->
    <div class="mb-4">
         <div class="relative">
            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input type="text" 
                   x-model="search"
                   placeholder="{{ __('Filter navigation...') }}" 
                   class="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 ps-9 text-sm text-gray-900 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:focus:ring-orange-500/50"
            >
         </div>
    </div>

    @foreach ($groupedItems as $category => $categoryItems)
        <div class="space-y-2" x-show="!search || $el.textContent.toLowerCase().includes(search.toLowerCase())">
            @if ($category !== 'Main' || $groupedItems->count() > 1)
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 px-3 truncate" title="{{ str_replace('-', ' ', ucfirst($category)) }}">
                    {{ str_replace('-', ' ', ucfirst($category)) }}
                </h3>
            @endif

            <ul class="space-y-0.5">
                @foreach ($categoryItems as $item)
                    @php
                        $isActive = ($active ?? '') === $item['slug'];
                        $displayTitle = $item['title'];

                        // Cleanup title: remove context/category prefixes for cleaner sidebar
                        if (str_contains($item['title'], ':')) {
                             $parts = explode(':', $item['title']);
                             $displayTitle = trim(end($parts));
                        }
                    @endphp

                    <li x-show="!search || '{{ strtolower($displayTitle) }}'.includes(search.toLowerCase())">
                        <a href="{{ url('/' . $routePrefix . '/' . $locale . '/' . $item['slug']) }}"
                            class="group flex items-center rounded-md px-3 py-1.5 text-sm font-medium transition-colors duration-200 border-l-2 {{ $isActive ? 'border-orange-500 bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400' : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200' }}"
                            @if ($isActive) aria-current="page" @endif>
                            
                            <span class="truncate">{{ $displayTitle }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
    
    <div x-show="search && $el.previousElementSibling.children.length === 0" class="px-3 text-sm text-gray-500 dark:text-gray-400 text-center py-4">
        {{ __('No results found') }}
    </div>
</nav>
