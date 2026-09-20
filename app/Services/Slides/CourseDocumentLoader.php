<?php

namespace App\Services\Slides;

use App\Exceptions\InvalidSlideDocumentException;
use App\Exceptions\SlideDocumentNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use JsonException;

class CourseDocumentLoader
{
    private const UPLOAD_DIRECTORY = 'slide-documents';

    private const THEME_COLOR_PATTERN = '/\\A#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})\\z/';

    /** @var array<int, string> */
    public const SUPPORTED_SLIDE_TYPES = [
        'title',
        'content',
        'bullet-list',
        'image-text',
    ];

    public function __construct(private readonly ?string $contentDirectory = null) {}

    /** @return array<string, mixed> */
    public function load(string $document): array
    {
        $this->assertSafeDocumentName($document);

        return $this->parseAndValidate($this->contentsFor($this->filenameFor($document), $document), $document);
    }

    /** @return array<string, mixed> */
    public function loadContents(string $contents, string $documentName): array
    {
        return $this->parseAndValidate($contents, $documentName);
    }

    /** @param array<string, mixed> $document */
    public function imageReferences(array $document): array
    {
        $references = [];

        foreach ($document['slides'] as $slide) {
            if (! is_array($slide) || ($slide['type'] ?? null) !== 'image-text') {
                continue;
            }

            foreach (['imageUrl', 'image'] as $field) {
                if (isset($slide[$field]) && is_string($slide[$field])) {
                    $references[] = SlideDocumentImageResolver::normalizeReference($slide[$field]);
                }
            }
        }

        return array_values(array_unique($references));
    }

    /** @return array<string, mixed> */
    private function parseAndValidate(string $contents, string $documentName): array
    {
        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidSlideDocumentException(
                "The slide document [{$documentName}] contains invalid JSON.",
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new InvalidSlideDocumentException(
                "The slide document [{$documentName}] must contain a JSON object.",
            );
        }

        $this->validate($payload, $documentName);

        return $payload;
    }

    private function contentsFor(string $filename, string $document): string
    {
        if ($this->contentDirectory !== null) {
            $path = $this->pathFor($filename);

            if (! is_file($path)) {
                throw new SlideDocumentNotFoundException($document);
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new InvalidSlideDocumentException("The slide document [{$document}] could not be read.");
            }

            return $contents;
        }

        $storage = Storage::disk('slide_documents');

        $documentIdentifier = str_ends_with($document, '.json')
            ? pathinfo($document, PATHINFO_FILENAME)
            : $document;
        $storedDocumentPath = self::UPLOAD_DIRECTORY.'/'.$documentIdentifier.'/document.json';

        if ($storage->exists($storedDocumentPath)) {
            return $storage->get($storedDocumentPath);
        }

        $storedPath = self::UPLOAD_DIRECTORY.'/'.$filename;

        if ($storage->exists($storedPath)) {
            return $storage->get($storedPath);
        }

        $samplePath = resource_path('content/courses').DIRECTORY_SEPARATOR.$filename;

        if (! is_file($samplePath)) {
            throw new SlideDocumentNotFoundException($document);
        }

        $contents = file_get_contents($samplePath);

        if ($contents === false) {
            throw new InvalidSlideDocumentException("The slide document [{$document}] could not be read.");
        }

        return $contents;
    }

    /** @param array<string, mixed> $document */
    public function slides(array $document): array
    {
        return $document['slides'];
    }

    private function assertSafeDocumentName(string $document): void
    {
        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $document)) {
            throw new InvalidSlideDocumentException('The requested slide document name is invalid.');
        }
    }

    private function filenameFor(string $document): string
    {
        return str_ends_with($document, '.json') ? $document : $document.'.json';
    }

    private function pathFor(string $filename): string
    {
        return rtrim($this->contentDirectory ?? '', DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
    }

    /** @param array<string, mixed> $document */
    private function validate(array $document, string $documentName): void
    {
        $validator = ValidatorFacade::make($document, [
            'course' => ['required', 'array'],
            'course.title' => ['required', 'string'],
            'module' => ['required', 'array'],
            'module.number' => ['required', 'integer', 'min:1'],
            'module.title' => ['required', 'string'],
            'metadata' => ['sometimes', 'array'],
            'theme' => ['sometimes', 'array'],
            'theme.primary' => ['required_with:theme', 'string', 'regex:'.self::THEME_COLOR_PATTERN],
            'theme.secondary' => ['required_with:theme', 'string', 'regex:'.self::THEME_COLOR_PATTERN],
            'theme.accent' => ['required_with:theme', 'string', 'regex:'.self::THEME_COLOR_PATTERN],
            'footer' => ['sometimes', 'array'],
            'footer.text' => ['required_with:footer', 'string', 'max:255'],
            'slides' => ['required', 'array', 'min:1'],
            'slides.*' => ['required', 'array'],
            'slides.*.type' => ['required', 'string'],
        ]);

        $validator->after(function (Validator $validator) use ($document): void {
            foreach ($document['slides'] ?? [] as $index => $slide) {
                if (! is_array($slide)) {
                    continue;
                }

                $type = $slide['type'] ?? null;

                if (! is_string($type) || ! in_array($type, self::SUPPORTED_SLIDE_TYPES, true)) {
                    $validator->errors()->add(
                        "slides.{$index}.type",
                        'Unsupported slide type. Supported types: '.implode(', ', self::SUPPORTED_SLIDE_TYPES).'.',
                    );

                    continue;
                }

                foreach ($this->requiredFieldsFor($type) as $field) {
                    if (! array_key_exists($field, $slide)) {
                        $validator->errors()->add(
                            "slides.{$index}.{$field}",
                            "The {$field} field is required for {$type} slides.",
                        );
                    }
                }

                $this->validateSlideFields($validator, $slide, $type, (int) $index);
            }
        });

        if ($validator->fails()) {
            throw new InvalidSlideDocumentException(
                "The slide document [{$documentName}] is invalid: ".implode(' ', $validator->errors()->all()),
            );
        }
    }

    /** @return array<int, string> */
    private function requiredFieldsFor(string $type): array
    {
        return array_merge(match ($type) {
            'title', 'content', 'bullet-list', 'image-text' => ['title'],
            default => [],
        }, match ($type) {
            'content' => ['content'],
            'bullet-list' => ['items'],
            'image-text' => ['text'],
            default => [],
        });
    }

    /** @param array<string, mixed> $slide */
    private function validateSlideFields(Validator $validator, array $slide, string $type, int $index): void
    {
        if (array_key_exists('title', $slide) && (! is_string($slide['title']) || trim($slide['title']) === '')) {
            $validator->errors()->add("slides.{$index}.title", 'The title field must be a non-empty string.');
        }

        foreach (['eyebrow', 'subtitle', 'intro', 'imageUrl', 'image', 'imageAlt', 'imageCaption'] as $field) {
            if (array_key_exists($field, $slide) && ! is_string($slide[$field])) {
                $validator->errors()->add("slides.{$index}.{$field}", "The {$field} field must be a string.");
            }
        }

        if ($type === 'image-text') {
            if (! array_key_exists('imageUrl', $slide) && ! array_key_exists('image', $slide)) {
                $validator->errors()->add(
                    "slides.{$index}.imageUrl",
                    'An imageUrl or image field is required for image-text slides.',
                );
            }

            foreach (['imageUrl', 'image'] as $field) {
                if (array_key_exists($field, $slide) && ! SlideDocumentImageResolver::isSafeReference($slide[$field])) {
                    $validator->errors()->add(
                        "slides.{$index}.{$field}",
                        "The {$field} field must reference a local image filename inside the document images directory.",
                    );
                }
            }
        }

        if ($type === 'content' || $type === 'image-text') {
            $field = $type === 'content' ? 'content' : 'text';

            if (! $this->isNonEmptyStringList($slide[$field] ?? null)) {
                $validator->errors()->add(
                    "slides.{$index}.{$field}",
                    "The {$field} field must be a non-empty array of strings.",
                );
            }
        }

        if ($type === 'bullet-list' && ! $this->isNonEmptyStringList($slide['items'] ?? null)) {
            $validator->errors()->add("slides.{$index}.items", 'The items field must be a non-empty array of strings.');
        }

        if (array_key_exists('imagePosition', $slide) && ! in_array($slide['imagePosition'], ['left', 'right'], true)) {
            $validator->errors()->add(
                "slides.{$index}.imagePosition",
                'The imagePosition field must be either left or right.',
            );
        }
    }

    private function isNonEmptyStringList(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                return false;
            }
        }

        return true;
    }
}
