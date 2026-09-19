@extends('slides.layout', ['slideClass' => 'slide--bullet-list'])

@section('slide-header')
    @if ($eyebrow ?? null)
        <p class="slide__eyebrow">{{ $eyebrow }}</p>
    @endif

    <h2 class="slide__heading">{{ $title ?? '' }}</h2>

    @if ($intro ?? null)
        <p class="slide__intro">{{ $intro }}</p>
    @endif
@endsection

@section('slide-content')
    <ul class="slide__list">
        @foreach ($items ?? [] as $item)
            <li>{{ is_array($item) ? ($item['text'] ?? '') : $item }}</li>
        @endforeach
    </ul>
@endsection
