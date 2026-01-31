@props(['title' => null, 'currentLocale' => null, 'currentVersion' => null, 'slug' => null, 'items' => []])

@php
    $locale = $currentLocale ?? app()->getLocale();
    $isRtl = in_array($locale, config('pertuk.rtl_locales', ['ar', 'ckb']));
    $current_version = $currentVersion; // Pass through to included header
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="h-full antialiased">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' · ' . config('app.name') : __('Docs') . ' · ' . config('app.name') }}</title>
    {!! \Xoshbin\Pertuk\Facades\Pertuk::css() !!}
    {!! \Xoshbin\Pertuk\Facades\Pertuk::js() !!}
</head>

<body class="min-h-screen bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100 transition-colors duration-200">
    <a href="#main"
        class="skip-link bg-orange-600 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-orange-500">Skip
        to content</a>

    @include('pertuk::components.header', ['current_version' => $current_version, 'slug' => $slug, 'items' => $items])

    <!-- Fluid container for ERP-scale documentation -->
    <div class="mx-auto max-w-[1920px] px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-[18rem_1fr] xl:grid-cols-[18rem_1fr_18rem] gap-8 py-8 items-start">
            <!-- Sidebar -->
            <aside class="hidden lg:block lg:sticky top-24 max-h-[calc(100vh-6rem)] overflow-y-auto pr-4 z-40 bg-white dark:bg-gray-950 lg:bg-transparent" aria-label="Navigation">
                {{ $sidebar ?? '' }}
            </aside>

            <!-- Main Content -->
            <main id="main" class="min-w-0">
                <div class="min-w-0">
                    {{ $slot }}
                </div>
            </main>

            <!-- Table of Contents (Right Sidebar) -->
            <aside class="hidden xl:block sticky top-24 max-h-[calc(100vh-6rem)] overflow-y-auto pl-4" aria-label="On this page">
                {{ $toc ?? '' }}
            </aside>
        </div>
    </div>

</body>

</html>
