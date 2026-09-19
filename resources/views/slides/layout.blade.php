@php
    $documentTitle = $documentTitle ?? 'Presentation slide';
    $pageNumber = $pageNumber ?? null;
    $pageCount = $pageCount ?? null;
    $footer = $footer ?? null;
    $showFooter = $showFooter ?? true;
    $slideClass = trim('slide '.($slideClass ?? ''));
@endphp

<article
    class="{{ $slideClass }}"
    @if ($slideId ?? null) id="{{ $slideId }}" @endif
    @if ($pageNumber !== null) data-slide-number="{{ $pageNumber }}" @endif
    @if ($pageCount !== null) data-slide-count="{{ $pageCount }}" @endif
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
