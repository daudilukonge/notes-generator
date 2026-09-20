@extends('slides.layout', ['slideClass' => 'slide--end'])

@section('slide-content')
    <div class="slide__end-content">
        <div class="slide__end-main">
            <h1 class="slide__end-title">{{ $title ?? '' }}</h1>

            @if ($subtitle ?? null)
                <p class="slide__end-subtitle">{{ $subtitle }}</p>
            @endif

            @if ($author['name'] ?? null)
                <p class="slide__end-author">{{ $author['name'] }}</p>
            @endif
        </div>

        @if ($designation ?? null)
            <p class="slide__designation slide__end-designation">{{ $designation }}</p>
        @endif
    </div>
@endsection