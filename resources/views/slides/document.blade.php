<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $documentTitle }}</title>

        @if ($pdf ?? false)
            <style>{!! $styles !!}</style>
        @else
            @vite('resources/css/slides.css')
        @endif
    </head>
    <body class="slides-document{{ ($pdf ?? false) ? ' slides-document--pdf' : '' }}">
        @if (! ($pdf ?? false))
            <nav class="slides-preview__toolbar" aria-label="Slide actions">
                <a href="{{ route('slides.pdf', ['document' => $documentName]) }}">Generate PDF</a>
            </nav>
        @endif

        <main class="slides-preview" aria-label="Slide preview">
            @foreach ($slides as $slide)
                {!! $slide !!}
            @endforeach
        </main>
    </body>
</html>
