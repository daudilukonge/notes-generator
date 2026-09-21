# Notes Generator

## What the project does

Notes Generator turns a validated course document into presentation-style educational slides. Authors provide one JSON document, either directly or inside a ZIP package with local images. The application validates the document, stores the approved files in private storage, renders a reusable 16:9 Blade/CSS presentation for browser preview, and generates a downloadable PDF with one slide per page.

The current MVP is intentionally focused on a secure, dependency-light authoring and export workflow. It does not include a course catalog, database-backed authoring interface, authentication, or background processing.

## Project architecture

The application is a Laravel 13.32 project running on PHP 8.4 with Blade templates, centralized CSS, and vanilla JavaScript.

- `CourseDocumentLoader` decodes and validates the course document and supplies normalized data to the renderer.
- `SlideDocumentPackageImporter` validates ZIP structure, approved image references, and package contents before storing only approved files.
- `SlideDocumentUploadController` handles JSON and ZIP uploads.
- `SlideRenderer` selects the shared Blade slide templates and applies document-level theme, footer, organization, and image data.
- `SlideDocumentImageResolver` resolves approved private image references for browser and PDF rendering.
- `SlidePreviewController` renders the multi-slide browser preview.
- `SlidePdfController` generates the downloadable PDF through `SlidePdfGenerator`.
- Blade templates in `resources/views/slides` and styles in `resources/css/slides.css` are shared by preview and PDF generation, so both outputs use the same slide architecture.
- Uploaded documents are stored without a database under the private `slide-documents` disk.

## How a course package is structured

A course package is a ZIP file containing exactly one root-level JSON document, normally named `document.json`, plus optional images under the `images/` directory.

~~~arduino
course-package.zip
├── document.json
└── images/
    ├── logo.png
    ├── image-1.jpg
    └── image-2.jpg
~~~

The archive may contain nested image filenames under `images/`, but it must not contain path traversal, absolute paths, duplicate entries, symlinks, scripts, unexpected non-image files, or more than one root-level JSON document. The importer also enforces upload, entry-count, and extracted-size limits.

## Complete JSON structure

The complete working example below is a valid starting point for a new module. Replace the titles, text, image names, organization details, and module metadata while keeping the field names and types intact. The repository also contains a larger real-world example at `resources/content/courses/uundaji-wa-bidhaa-za-ngozi-ph-testing.json`.

~~~json
{
  "course": {
    "title": "Uundaji wa Bidhaa za Ngozi"
  },
  "module": {
    "number": 1,
    "title": "Sayansi ya Ngozi",
    "designation": "Moduli 1"
  },
  "theme": {
    "primary": "#4c2b08",
    "secondary": "#cdd4b1",
    "accent": "#ab7743"
  },
  "footer": {
    "text": "AsiliSpot Formulation Organization | www.asilispot.org | +255 768 078 727"
  },
  "metadata": {
    "description": "Moduli ya 1: Utambulisho wa Sayansi ya Ngozi"
  },
  "slides": [
    {
      "type": "cover",
      "title": "Uundaji wa Bidhaa za Ngozi",
      "subtitle": "Sayansi ya Ngozi",
      "designation": "MODULI 1"
    },
    {
      "type": "module-title",
      "title": "Sayansi ya Ngozi",
      "subtitle": "Misingi ya kuelewa ngozi kabla ya kuunda bidhaa"
    },
    {
      "type": "title",
      "title": "Karibu kwenye moduli",
      "subtitle": "Tutaanza kwa kuelewa muundo na kazi za ngozi."
    },
    {
      "type": "content",
      "title": "Kwa nini kujifunza sayansi ya ngozi?",
      "content": [
        "Uelewa wa ngozi husaidia kuchagua viambato na kutengeneza bidhaa zinazofaa.",
        "Misingi hii itaongoza maamuzi ya uundaji katika moduli zinazofuata."
      ]
    },
    {
      "type": "bullet-list",
      "title": "Malengo ya moduli",
      "intro": "Baada ya moduli hii, mwanafunzi ataweza:",
      "items": [
        "Kueleza tabaka kuu za ngozi.",
        "Kutambua kazi muhimu za ngozi.",
        "Kuhusisha mahitaji ya ngozi na uchaguzi wa bidhaa."
      ]
    },
    {
      "type": "image-text",
      "title": "Muundo wa ngozi",
      "text": [
        "Ngozi ina tabaka zenye kazi tofauti na zinazohusiana.",
        "Tumia picha za ndani ya package kwa vielelezo vya somo."
      ],
      "image": "images/image-1.jpg",
      "imageAlt": "Mchoro wa muundo wa ngozi",
      "imagePosition": "right",
      "imageCaption": "Mfano wa muundo wa ngozi"
    },
    {
      "type": "image-text",
      "title": "Mfano wa picha ya pili",
      "text": [
        "The imageUrl field is also supported for a relative package image reference."
      ],
      "imageUrl": "images/image-2.jpg",
      "imageAlt": "Mfano wa picha ya bidhaa",
      "imagePosition": "left"
    },
    {
      "type": "end",
      "title": "ASANTE SANA",
      "subtitle": "Tukutane katika Moduli ya 2",
      "designation": "MWISHO WA MODULI 1"
    }
  ],
  "author": {
    "name": "AsiliSpot Formulation Organization",
    "description": "Mafunzo ya uundaji wa bidhaa za ngozi."
  },
  "document": {
    "year": 2026
  },
  "next": {
    "type": "module",
    "number": 2
  },
  "is_final": false,
  "organization": {
    "name": "AsiliSpot Formulation Organization",
    "logo": "images/logo.png",
    "website": "www.asilispot.org",
    "phone": "+255 768 078 727"
  }
}
~~~

Top-level fields used by the current implementation are:

- `course.title`: required course name.
- `module.number` and `module.title`: module identity shown by structured templates and available to the footer. Use `module.number: 0` for non-module documents such as an introduction or conclusion.
- `slides`: required non-empty array of slide objects.
- `theme`: optional color configuration.
- `footer.text`: optional footer text; the organization/contact footer is used as the fallback when this is omitted.
- `metadata`: optional document metadata such as `description`.
- `author`: optional author name and description.
- `document.year`: optional year shown in the shared footer.
- `next`: optional metadata describing the next module.
- `is_final`: optional boolean that marks the document as the final course module.
- `organization`: optional organization name, logo, website, and phone details.
- `slides[*].type`: one of the supported slide types described below.

The structured module order is normally a `cover` first, a `module-title` second, lesson slides in the middle, and exactly one `end` slide last. Legacy lesson slide types can still be used in the same document.

## Supported slide types

The current supported slide types are:

- `cover`: themed course/module opening slide. It uses `title`, optional `subtitle`, and optional `designation`.
- `module-title`: section opener for a module. It uses `title` and optional `subtitle` and `designation`.
- `title`: a general title slide. It uses `title` and optional `subtitle`.
- `content`: text slide. It requires a non-empty `content` array of strings and uses `title`.
- `bullet-list`: list slide. It requires a non-empty `items` array of strings, with optional `intro` and `title`.
- `image-text`: text beside an image. It requires a non-empty `text` array of strings, either `image` or `imageUrl`, and optional `imageAlt`, `imageCaption`, and `imagePosition`.
- `end`: module/course closing slide. It uses `title`, optional `subtitle`, and optional `designation`.

For `image-text`, `imagePosition` accepts `left` or `right`. If it is omitted, the existing default layout is used. Images preserve their complete aspect ratio and are contained and centered within the available image area.

## Theme configuration

The optional `theme` object accepts hexadecimal color strings:

~~~json
{
  "theme": {
    "primary": "#4c2b08",
    "secondary": "#cdd4b1",
    "accent": "#ab7743"
  }
}
~~~

The values feed centralized CSS variables used by the shared slide templates. If `theme` is omitted, the application defaults are used. Invalid color values are rejected during document validation.

## Organization, footer, and logo configuration

Use `organization` for branding and contact details, `footer.text` for an explicit footer line, and `document.year` for the year:

~~~json
{
  "organization": {
    "name": "AsiliSpot Formulation Organization",
    "logo": "images/logo.png",
    "website": "www.asilispot.org",
    "phone": "+255 768 078 727"
  },
  "footer": {
    "text": "AsiliSpot Formulation Organization | www.asilispot.org | +255 768 078 727"
  },
  "document": {
    "year": 2026
  }
}
~~~

The organization name and approved local logo are rendered in the shared presentation header when present. The footer appears on each slide. If `footer.text` is omitted, the renderer falls back to the available organization and contact details. A missing optional organization logo does not invalidate the document; the remaining branding is retained.

## Image references

Package images must be placed under `images/` and referenced with a relative package path, for example:

~~~json
{
  "type": "image-text",
  "title": "Example",
  "text": [
    "The complete image is displayed beside this text."
  ],
  "image": "images/image-1.jpg",
  "imageAlt": "Example image",
  "imagePosition": "right"
}
~~~

Both `image` and `imageUrl` are supported for local package image references. They are field names, not remote URL support: values must be relative filenames such as `images/image-1.jpg`. Absolute paths, URLs, traversal segments, unsafe extensions, and references outside the package are rejected. SVG is allowed only within the controlled document directory.

Images are resolved through the secure private image resolver and served through the controlled document-image route. They are not copied to public storage. During PDF generation, approved local images are embedded for the isolated browser renderer, so local package images continue to work without exposing the whole storage filesystem.

## Module-to-module transition

A module closes with an explicit `end` slide. The next module’s number can be recorded in `next.number`, but the application does not invent or inject a transition sentence automatically. Authors should write the desired transition in the end slide subtitle:

~~~json
{
  "type": "end",
  "title": "ASANTE SANA",
  "subtitle": "Tukutane katika Moduli ya 2",
  "designation": "MWISHO WA MODULI 1"
}
~~~

Set `next` to the next module when that metadata is useful to downstream authoring or presentation logic:

~~~json
{
  "next": {
    "type": "module",
    "number": 2
  },
  "is_final": false
}
~~~

## Final-course behavior

For the final course module, set `is_final` to `true` and make the final `end` slide explicit. The application does not infer a final-course message from the module number:

~~~json
{
  "is_final": true,
  "slides": [
    {
      "type": "end",
      "title": "ASANTE SANA",
      "subtitle": "Karibu tena katika mafunzo yajayo.",
      "designation": "MWISHO WA KOZI"
    }
  ]
}
~~~

A complete document still uses one final `end` slide. If `is_final` is false or omitted, the document is treated as an intermediate module and the author should provide the next-module transition text.

## Upload and preview workflow

1. Prepare `document.json` and any referenced files under `images/`.
2. Upload the JSON file directly or upload a ZIP package.
3. The importer validates the document, package paths, image references, and image files before storage.
4. The approved document is stored privately in a document-specific directory:

~~~text
storage/app/slide-documents/{document-id}/
├── document.json
└── images/
    ├── logo.png
    └── image-1.jpg
~~~

5. The browser preview renders the slides through the shared Blade templates. Images are available only through the controlled private image route.

For a ZIP upload, the archive must contain exactly one structurally valid root-level JSON document and only approved entries. The package is not unpacked wholesale into public storage.

## PDF generation

The PDF uses the same Blade templates and CSS foundation as the browser preview. `SlidePdfGenerator` renders one slide per page through Browsershot/Puppeteer with the fixed 16:9 slide dimensions.

PDF generation:

- preserves the slide order and page count;
- includes the shared theme, header, footer, and page numbering;
- embeds approved local images so private package images work in the PDF;
- keeps image-text images contained, centered, proportional, and uncropped;
- uses document data for the generated filename;
- runs synchronously as part of the download request.

The server environment must have a working Chromium/Puppeteer installation and the required fonts for consistent output.

## Development and setup requirements

Requirements:

- PHP 8.4 or a compatible PHP 8.3+ runtime.
- Composer.
- Node.js and npm; the current project uses Node 22+.
- A Chromium/Puppeteer runtime for PDF generation.
- The PHP and Node dependencies installed for the project.

Typical local setup:

~~~bash
composer install
npm install
npm run build
php artisan migrate
php artisan serve
~~~

This MVP does not require application tables for course documents. Use the project’s configured storage and cache permissions, and install the browser dependencies required by Browsershot/Puppeteer before testing PDF downloads.

## Testing commands

Run the focused slide/upload tests first, then the complete suite:

~~~bash
php artisan test --compact tests/Feature/SlideDocumentUploadTest.php
php artisan test --compact tests/Feature/SlideDocumentPackageTest.php
php artisan test --compact tests/Feature/SlidePreviewTest.php
php artisan test --compact tests/Feature/SlidePdfTest.php
php artisan test --compact
~~~

Useful project checks:

~~~bash
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
~~~

The exact test filenames may be expanded as the suite grows; use `rg --files tests` and `php artisan test --list-tests` to inspect the current test inventory.

## Important limitations and current MVP scope

- The MVP supports only the slide types listed in this README.
- PDF generation is synchronous and depends on a working Chromium/Puppeteer runtime.
- Browser/PDF output can vary slightly with installed fonts and browser versions.
- Package images must be local approved image files under `images/`; remote URLs are not supported.
- The secure resolver intentionally rejects unsafe paths, unsupported file types, and files outside the document directory.
- The application uses private local storage and controlled image delivery; the entire storage filesystem is never public.
- There is no database-backed course catalog, web authoring editor, authentication/authorization layer, queue, analytics, or multi-user publishing workflow in the current MVP.
- The frontend remains framework-free and uses shared Blade/CSS templates for preview and PDF rendering.
