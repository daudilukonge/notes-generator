<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>

        @vite('resources/css/slides.css')
    </head>
    <body class="slides-document">
        <article class="slide slide--error">
            <div class="slide__surface">
                <header class="slide__header">
                    <p class="slide__eyebrow">Slide preview</p>
                    <h1 class="slide__title">{{ $title }}</h1>
                </header>

                <main class="slide__content">
                    <div class="slide__prose">
                        <p>{{ $message }}</p>
                    </div>
                </main>
            </div>
        </article>
    </body>
</html>
