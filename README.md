# Notes Generator

Notes Generator turns a validated JSON course document or ZIP package into reusable 16:9 HTML presentation slides and a downloadable PDF.

## MVP status

The core MVP is complete:

```text
ZIP package -> validate -> private storage -> browser preview -> PDF download
```

The application is synchronous, database-free, and intentionally uses Blade, CSS, and vanilla JavaScript for the presentation layer.

## Setup

Requirements:

- PHP 8.3+ and Composer
- Node.js 22+
- npm
- Chromium managed by Puppeteer

Install the application dependencies:

```bash
composer install
npm install
npx puppeteer browsers install chrome
npm run build
php artisan serve
```

Open the application at the URL printed by `php artisan serve`. The upload screen is available at `/` or `/slides/upload`.

On Linux servers where Chromium cannot use its sandbox, set `SLIDES_PDF_NO_SANDBOX=true`. Prefer the sandboxed default whenever the host supports it. Optional binary overrides are available through `SLIDES_PDF_NODE_BINARY`, `SLIDES_PDF_NPM_BINARY`, and `SLIDES_PDF_CHROME_PATH`; `SLIDES_PDF_TIMEOUT` defaults to 60 seconds.

## Package format

A ZIP package must contain exactly one root-level JSON document and optional image files below `images/`:

```text
course-package.zip
├── document.json
└── images/
    └── skin-structure.png
```

The JSON document contains `course.title`, `module.number`, `module.title`, and a non-empty `slides` list. An image-text slide references a package image with either `image` or `imageUrl`, for example:

```json
{
    "course": {"title": "Cosmetic Formulation"},
    "module": {"number": 1, "title": "Skin Structure"},
    "slides": [
        {"type": "title", "title": "Skin Structure"},
        {
            "type": "image-text",
            "title": "The skin layers",
            "text": ["Epidermis", "Dermis", "Hypodermis"],
            "image": "images/skin-structure.png",
            "imageAlt": "Skin structure"
        }
    ]
}
```

Image references must be relative filenames (or safe nested filenames) with an allowed image extension. URLs, filesystem paths, traversal segments, and files outside `images/` are rejected.

## Upload, preview, and PDF workflow

1. Upload a `.json` document or `.zip` package at `/slides/upload`.
2. The importer validates the JSON, slide structure, archive paths, referenced images, file counts, and extracted size.
3. The validated document is stored on the private `slide_documents` disk.
4. The upload redirects to `/slides/{document}` for the browser preview.
5. Select **Generate PDF**. The endpoint `/slides/{document}/pdf` renders the same Blade slide templates and stylesheet through headless Chrome and downloads one slide per PDF page.

The PDF filename is generated from validated course and module information. The original upload filename is never used as a download path.

## Architecture

- `CourseDocumentLoader` loads and validates JSON from private storage or the built-in sample content.
- `SlideDocumentPackageImporter` inspects ZIP entries before streaming only approved JSON and image entries into a document-specific directory.
- `SlideDocumentImageResolver` restricts every image to that document's private `images/` directory. PDF generation embeds only bytes returned by this resolver as data URIs; JSON never supplies a filesystem path to Chrome.
- `SlideRenderer` selects the Blade template for each supported slide and can render the same document as preview HTML or PDF HTML with the existing `resources/css/slides.css` inlined for the browser renderer.
- `SlidePdfGenerator` is the synchronous PDF application service. It uses Spatie Browsershot 5 with Puppeteer/Chromium, sets a 16 × 9 inch page, enables backgrounds, removes margins, and respects the print page size.
- `SlidePdfController` coordinates loading, PDF generation, safe download headers, and user-facing failure responses.

Storage has no database record. Documents are stored privately as:

```text
storage/app/slide-documents/{document-id}/
├── document.json
└── images/
    └── ...
```

The only image delivery endpoint is `/slides/{document}/images/{image}`. The private storage filesystem is not exposed publicly.

## Supported slide types

- `title`
- `content`
- `bullet-list`
- `image-text`

Each slide carries its own footer and page number. The renderer keeps one slide as one fixed 16:9 canvas, prevents page splitting, and removes the trailing page break from the final slide.

## Development and verification

```bash
composer show --direct
php artisan route:list
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

The focused PDF coverage is in `tests/Feature/SlidePdfTest.php`. Existing upload, package-security, image-resolution, validation, and preview tests remain in `tests/Feature/Slide*Test.php`.

## PDF engine requirements

The selected engine is browser-based because the current slide design uses CSS Grid, CSS variables, `clamp()`, `aspect-ratio`, print rules, backgrounds, and local images. A traditional PHP-only engine would require a second layout or would render these features inconsistently. Browsershot delegates to Puppeteer and Chromium, so the PDF uses the same HTML/CSS design as the browser preview.

The runtime dependency is `spatie/browsershot` plus the npm `puppeteer` package and its managed Chrome download. No PHP PDF library or database is used.

## Known MVP limitations

- PDF generation is synchronous; very large packages may take longer than the configured timeout.
- Chromium/Node must be installed wherever PDF downloads are served.
- Only the four listed slide layouts are supported. Advanced layouts such as tables, comparisons, formulas, notes, and summaries are not implemented.
- There is no authentication, account management, database-backed document catalog, queue, analytics, or multi-user permission system.
- Layout fidelity depends on the installed Chromium version and fonts available on the host. The slide stylesheet uses system fallbacks and does not bundle a custom font.