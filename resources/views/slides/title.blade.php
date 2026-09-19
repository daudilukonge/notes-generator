@extends('slides.layout', ['slideClass' => 'slide--title'])

@section('slide-header')
    @if ($eyebrow ?? null)
        <p class="slide__eyebrow">{{ $eyebrow }}</p>
    @endif
@endsection

@section('slide-content')
    <div class="slide__title-block">
        <h1 class="slide__title">{{ $title ?? '' }}</h1>

        @if ($subtitle ?? null)
            <p class="slide__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
@endsection
