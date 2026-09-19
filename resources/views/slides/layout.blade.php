@php
    $documentTitle = $documentTitle ?? 'Presentation slide';
    $pageNumber = $pageNumber ?? null;
    $pageCount = $pageCount ?? null;
    $footer = $footer ?? null;
    $showFooter = $showFooter ?? true;
    $slideClass = trim('slide '.($slideClass ?? ''));
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $documentTitle }}</title>

        @vite('resources/css/slides.css')
    </head>
    <body class="slides-document">
        <article
            class="{{ $slideClass }}"
            @if ($slideId ?? null) id="{{ $slideId }}" @endif
        >
            <div class="slide__surface">
                <header class="slide__header">
                    @yield('slide-header')
                </header>

                <main class="slide__content">
                    @yield('slide-content')
                </main>

                @if ($showFooter)
                    <footer class="slide__footer">
                        @if ($footer)
                            <span>{{ $footer }}</span>
                        @endif

                        @yield('slide-footer')

                        @if ($pageNumber !== null)
                            <span class="slide__page-number">
                                {{ $pageNumber }}@if ($pageCount !== null) / {{ $pageCount }}@endif
                            </span>
                        @endif
                    </footer>
                @endif
            </div>
        </article>
    </body>
</html>
