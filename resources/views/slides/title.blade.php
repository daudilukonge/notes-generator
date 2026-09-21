@extends('slides.layout', ['slideClass' => 'slide--title'])

@section('slide-header')
    @if ($eyebrow ?? null)
        <p class="slide__eyebrow">{{ $eyebrow }}</p>
    @endif
@endsection

@section('slide-content')
    <div class="slide__cover-content">
        <div class="slide__cover-main">
            <div class="slide__title-block">
                <h1 class="slide__title">{{ $title ?? '' }}</h1>

                @if (($subtitle ?? null) && ! ($isCover ?? false))
                    <p class="slide__subtitle">{{ $subtitle }}</p>
                @endif

                @if (($author['name'] ?? null) || ($author['description'] ?? null))
                    <div class="slide__author">
                        @if ($author['name'] ?? null)
                            <p class="slide__author-name">{{ $author['name'] }}</p>
                        @endif

                        @if ($author['description'] ?? null)
                            <p class="slide__author-description">{{ $author['description'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @if ($designation ?? null)
            <p class="slide__designation slide__cover-designation">{{ $designation }}</p>
        @endif
    </div>
@endsection