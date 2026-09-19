@extends('slides.layout', ['slideClass' => 'slide--content'])

@section('slide-header')
    @if ($eyebrow ?? null)
        <p class="slide__eyebrow">{{ $eyebrow }}</p>
    @endif

    <h2 class="slide__heading">{{ $title ?? '' }}</h2>
@endsection

@section('slide-content')
    @php
        $contentItems = is_array($content ?? null) ? $content : [$content ?? null];
    @endphp

    <div class="slide__prose">
        @foreach ($contentItems as $paragraph)
            @if ($paragraph !== null && trim((string) $paragraph) !== '')
                <p>{{ $paragraph }}</p>
            @endif
        @endforeach
    </div>
@endsection
