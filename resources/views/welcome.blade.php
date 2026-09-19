<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Upload a slide document</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 px-6 py-12 text-slate-900">
        <main class="mx-auto flex min-h-[calc(100vh-6rem)] max-w-3xl items-center justify-center">
            <section class="w-full rounded-3xl bg-white p-8 shadow-xl shadow-slate-900/10 sm:p-12">
                <div class="flex flex-col gap-8">
                    <header class="flex flex-col gap-3">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-700">Notes Generator</p>
                        <h1 class="text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl">Upload a slide document</h1>
                        <p class="max-w-2xl text-lg leading-8 text-slate-600">Upload a JSON course document or a ZIP package containing the document and its local images, then preview the generated slides in your browser.</p>
                    </header>
                    @if (session('success'))
                        <div class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950" role="status">
                            <p class="font-semibold">{{ session('success') }}</p>
                            @if (session('original_filename')) <p class="text-sm">{{ session('original_filename') }}</p> @endif
                            @if (session('preview_url'))
                                <a class="w-fit rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800" href="{{ session('preview_url') }}">Preview slides</a>
                            @endif
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-950" role="alert">
                            <p class="font-semibold">The document could not be uploaded.</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif
                    <form class="flex flex-col gap-5" action="{{ route('slides.upload.store') }}" enctype="multipart/form-data" method="POST">
                        @csrf
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-800" for="file">JSON document or ZIP course package</label>
                            <input class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-700 shadow-sm outline-none file:mr-4 file:rounded-lg file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:font-semibold file:text-sky-700 focus:border-sky-500 focus:ring-4 focus:ring-sky-100" accept=".json,.zip,application/json,application/zip" id="file" name="file" required type="file">
                            <p class="text-sm text-slate-500">JSON files may be up to 5 MB. ZIP packages may be up to 20 MB and must contain exactly one document JSON file plus images inside <code>images/</code>.</p>
                            <p class="rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600"><strong>ZIP structure:</strong> <code>course-package.zip</code> &rarr; <code>document.json</code> and <code>images/skin-structure.png</code>. In an <code>image-text</code> slide, reference it as <code>"image": "images/skin-structure.png"</code>.</p>
                        </div>
                        <button class="w-fit rounded-xl bg-sky-700 px-5 py-3 font-semibold text-white hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-200" type="submit">Upload and validate</button>
                    </form>
                    <p class="border-t border-slate-200 pt-6 text-sm text-slate-600">
                        Development sample:
                        <a class="font-semibold text-sky-700 underline decoration-sky-300 underline-offset-4 hover:text-sky-900" href="{{ route('slides.preview', ['document' => 'uundaji-wa-bidhaa-za-ngozi-ph-testing']) }}">preview the sample course</a>
                    </p>
                </div>
            </section>
        </main>
    </body>
</html>
