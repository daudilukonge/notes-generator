<footer class="slide__footer">
    @if ($creationYear)
        <span class="slide__footer-year">{{ $creationYear }}</span>
    @endif

    @if ($footer)
        <span class="slide__footer-text">{{ $footer }}</span>
    @endif
</footer>