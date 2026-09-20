<header class="slide__header">
    @if ($isCover && ($organization['name'] ?? null))
        <p class="slide__organization">{{ $organization['name'] }}</p>
    @endif

    @yield('slide-header')

    @if ($organizationLogoAvailable && $organizationLogoUrl)
        <img
            class="slide__logo"
            src="{{ $organizationLogoUrl }}"
            alt="{{ $organization['name'] ?? '' }}"
        >
    @endif
</header>