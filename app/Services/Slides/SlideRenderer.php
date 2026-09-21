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
        'cover' => 'slides.title',
        'module-title' => 'slides.module-title',
        'content' => 'slides.content',
        'bullet-list' => 'slides.bullet-list',
        'image-text' => 'slides.image-text',
        'end' => 'slides.end',
    ];

    /** @var array<string, array<int, string>> */
    private const SLIDE_FIELDS = [
        'title' => ['eyebrow', 'title', 'subtitle', 'designation'],
        'cover' => ['eyebrow', 'title', 'subtitle', 'designation'],
        'module-title' => ['eyebrow', 'title'],
        'content' => ['eyebrow', 'title', 'content'],
        'bullet-list' => ['eyebrow', 'title', 'intro', 'items'],
        'image-text' => ['eyebrow', 'title', 'text', 'image', 'imageUrl', 'imageAlt', 'imageCaption', 'imagePosition'],
        'end' => ['title', 'subtitle', 'designation'],
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
        $organization = is_array($document['organization'] ?? null) ? $document['organization'] : [];
        $organizationLogoUrl = null;
        $organizationLogoAvailable = false;

        if (is_string($organization['logo'] ?? null)) {
            $organizationLogo = $organization['logo'];

            if ($forPdf) {
                $organizationLogoUrl = $this->imageResolver->dataUriFor($documentName, $organizationLogo);
                $organizationLogoAvailable = $organizationLogoUrl !== null;
            } else {
                $organizationLogoUrl = $this->imageResolver->urlFor($documentName, $organizationLogo);
                $organizationLogoAvailable = $this->imageResolver->existingPathFor($documentName, $organizationLogo) !== null;
            }
        }

        $shared = [
            'course' => $document['course'],
            'module' => $module,
            'author' => is_array($document['author'] ?? null) ? $document['author'] : [],
            'organization' => $organization,
            'documentInfo' => is_array($document['document'] ?? null) ? $document['document'] : [],
            'next' => is_array($document['next'] ?? null) ? $document['next'] : [],
            'isFinal' => (bool) ($document['is_final'] ?? false),
            'organizationLogoUrl' => $organizationLogoUrl,
            'organizationLogoAvailable' => $organizationLogoAvailable,
        ];
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
                $shared,
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

        if (is_string($footerText)) {
            return $footerText;
        }

        $organization = is_array($document['organization'] ?? null) ? $document['organization'] : [];
        $organizationDetails = array_values(array_filter([
            $organization['name'] ?? null,
            $organization['website'] ?? null,
            $organization['phone'] ?? null,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));

        if ($organizationDetails !== []) {
            return implode(' · ', $organizationDetails);
        }

        return $moduleNumber > 0
            ? $courseTitle.' · Module '.$moduleNumber
            : $courseTitle;
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

    /**
     * @param  array<string, mixed>  $slide
     * @param  array<string, mixed>  $shared
     */
    private function renderSlide(
        array $slide,
        int $pageNumber,
        int $pageCount,
        string $documentTitle,
        string $footer,
        string $documentName,
        bool $forPdf,
        array $shared,
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

        return view($template, array_merge($slideData, $shared, [
            'documentTitle' => $documentTitle,
            'pageNumber' => $pageNumber,
            'pageCount' => $pageCount,
            'footer' => $footer,
            'creationYear' => $shared['documentInfo']['year'] ?? null,
            'isCover' => in_array($type, ['cover', 'title'], true),
        ]))->render();
    }
}
