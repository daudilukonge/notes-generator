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
        @include('slides.partials.header', [
            'isCover' => $isCover ?? false,
            'organization' => $organization ?? [],
            'organizationLogoUrl' => $organizationLogoUrl ?? null,
            'organizationLogoAvailable' => $organizationLogoAvailable ?? false,
        ])

        <main class="slide__content">
            @yield('slide-content')
        </main>

        @if ($showFooter)
            @include('slides.partials.footer', [
                'creationYear' => $creationYear ?? null,
                'footer' => $footer,
            ])
        @endif
    </div>
</article>