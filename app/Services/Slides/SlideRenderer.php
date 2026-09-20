<?php

namespace App\Services\Slides;

use App\Exceptions\InvalidSlideDocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;

class SlideRenderer
{
    public function __construct(private readonly SlideDocumentImageResolver $imageResolver) {}

    /** @var array<string, string> */
    private const TEMPLATES = [
        'title' => 'slides.title',
        'content' => 'slides.content',
        'bullet-list' => 'slides.bullet-list',
        'image-text' => 'slides.image-text',
    ];

    /** @var array<string, array<int, string>> */
    private const SLIDE_FIELDS = [
        'title' => ['eyebrow', 'title', 'subtitle'],
        'content' => ['eyebrow', 'title', 'content'],
        'bullet-list' => ['eyebrow', 'title', 'intro', 'items'],
        'image-text' => ['eyebrow', 'title', 'text', 'image', 'imageUrl', 'imageAlt', 'imageCaption', 'imagePosition'],
    ];

    /** @param array<string, mixed> $document */
    public function render(array $document, string $documentName = 'document', bool $forPdf = false): View
    {
        $slides = $document['slides'];
        $pageCount = count($slides);
        $courseTitle = $document['course']['title'];
        $module = $document['module'];
        $documentTitle = $courseTitle.' — '.$module['title'];
        $footer = $this->footerFor($document, $courseTitle, $module['number']);
        $themeStyle = $this->themeStyle($document['theme'] ?? null);
        $renderedSlides = [];

        foreach ($slides as $index => $slide) {
            $renderedSlides[] = $this->renderSlide(
                $slide,
                (int) $index + 1,
                $pageCount,
                $documentTitle,
                $footer,
                $documentName,
                $forPdf,
            );
        }

        $styles = null;

        if ($forPdf) {
            $styles = file_get_contents(resource_path('css/slides.css'));

            if ($styles === false) {
                throw new InvalidSlideDocumentException('The slide stylesheet could not be read.');
            }
        }

        return view('slides.document', [
            'documentTitle' => $documentTitle,
            'documentName' => $documentName,
            'course' => $document['course'],
            'module' => $module,
            'metadata' => $document['metadata'] ?? [],
            'themeStyle' => $themeStyle,
            'slides' => $renderedSlides,
            'pdf' => $forPdf,
            'styles' => $styles,
        ]);
    }

    /** @param array<string, mixed> $document */
    private function footerFor(array $document, string $courseTitle, int $moduleNumber): string
    {
        $footerText = $document['footer']['text'] ?? null;

        return is_string($footerText) ? $footerText : $courseTitle.' · Module '.$moduleNumber;
    }

    private function themeStyle(mixed $theme): ?string
    {
        if (! is_array($theme)) {
            return null;
        }

        foreach (['primary', 'secondary', 'accent'] as $colorName) {
            if (! is_string($theme[$colorName] ?? null)) {
                return null;
            }
        }

        return '--slide-primary: '.$theme['primary'].'; '
            .'--slide-secondary: '.$theme['secondary'].'; '
            .'--slide-accent: '.$theme['accent'].';';
    }

    /** @param array<string, mixed> $slide */
    private function renderSlide(
        array $slide,
        int $pageNumber,
        int $pageCount,
        string $documentTitle,
        string $footer,
        string $documentName,
        bool $forPdf,
    ): string {
        $type = $slide['type'] ?? null;
        $template = is_string($type) ? (self::TEMPLATES[$type] ?? null) : null;

        if ($template === null || ! is_string($type)) {
            throw new InvalidSlideDocumentException('The document contains an unsupported slide type.');
        }

        $slideData = Arr::only($slide, self::SLIDE_FIELDS[$type]);

        if ($type === 'image-text') {
            $imageReference = $slideData['imageUrl'] ?? $slideData['image'] ?? null;

            if (! is_string($imageReference)) {
                throw new InvalidSlideDocumentException('The image-text slide does not contain an image reference.');
            }

            if ($forPdf) {
                $imageData = $this->imageResolver->dataUriFor($documentName, $imageReference);

                if ($imageData === null) {
                    throw new InvalidSlideDocumentException(
                        "The image [{$imageReference}] required by the document could not be found.",
                    );
                }

                $slideData['imageUrl'] = $imageData;
                $slideData['imageAvailable'] = true;
            } else {
                $slideData['imageUrl'] = $this->imageResolver->urlFor($documentName, $imageReference);
                $slideData['imageAvailable'] = $this->imageResolver->existingPathFor($documentName, $imageReference) !== null;
            }

            unset($slideData['image']);
        }

        return view($template, array_merge($slideData, [
            'documentTitle' => $documentTitle,
            'pageNumber' => $pageNumber,
            'pageCount' => $pageCount,
            'footer' => $footer,
        ]))->render();
    }
}
