@extends('slides.layout', ['slideClass' => 'slide--module-title'])

@section('slide-header')
    @if ($eyebrow ?? null)
        <p class="slide__eyebrow">{{ $eyebrow }}</p>
    @endif
@endsection

@section('slide-content')
    <div class="slide__module-title">
        <h1 class="slide__title">{{ $title ?? '' }}</h1>
    </div>
@endsection