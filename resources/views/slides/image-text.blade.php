@extends('slides.layout', [
    'slideClass' => 'slide--image-text slide--image-'.(($imagePosition ?? 'right') === 'left' ? 'left' : 'right'),
])

@section('slide-header')
    @if ($eyebrow ?? null)
        <p class="slide__eyebrow">{{ $eyebrow }}</p>
    @endif

    <h2 class="slide__heading">{{ $title ?? '' }}</h2>
@endsection

@section('slide-content')
    @php
        $textItems = is_array($text ?? null) ? $text : [$text ?? null];
    @endphp

    <div class="slide__image-text">
        <div class="slide__image-wrap">
            @if ($imageUrl ?? null)
                <img
                    class="slide__image"
                    src="{{ $imageUrl }}"
                    alt="{{ $imageAlt ?? '' }}"
                >
            @endif

            @if ($imageCaption ?? null)
                <p class="slide__image-caption">{{ $imageCaption }}</p>
            @endif
        </div>

        <div class="slide__prose">
            @foreach ($textItems as $paragraph)
                @if ($paragraph !== null && trim((string) $paragraph) !== '')
                    <p>{{ $paragraph }}</p>
                @endif
            @endforeach
        </div>
    </div>
@endsection
