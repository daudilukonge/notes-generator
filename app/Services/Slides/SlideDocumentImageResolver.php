<?php

namespace App\Services\Slides;

use App\Exceptions\InvalidSlideDocumentException;
use Illuminate\Support\Facades\Storage;

class SlideDocumentImageResolver
{
    private const STORAGE_DIRECTORY = 'slide-documents';

    private const IMAGES_DIRECTORY = 'images';

    /** @var array<int, string> */
    private const ALLOWED_EXTENSIONS = ['gif', 'jpeg', 'jpg', 'png', 'svg', 'webp'];

    public function urlFor(string $document, string $imageReference): string
    {
        $imageReference = self::normalizeReference($imageReference);

        return route('slides.image', [
            'document' => $document,
            'image' => $imageReference,
        ]);
    }

    public function existingPathFor(string $document, string $imageReference): ?string
    {
        $relativePath = $this->relativePathFor($document, $imageReference);
        $storage = Storage::disk('slide_documents');

        if (! $storage->exists($relativePath)) {
            return null;
        }

        $absolutePath = realpath($storage->path($relativePath));
        $allowedDirectory = realpath($storage->path($this->imagesDirectoryFor($document)));

        if ($absolutePath === false || $allowedDirectory === false || ! $this->isWithinDirectory($absolutePath, $allowedDirectory)) {
            return null;
        }

        return is_file($absolutePath) ? $absolutePath : null;
    }

    public function dataUriFor(string $document, string $imageReference): ?string
    {
        $path = $this->existingPathFor($document, $imageReference);

        if ($path === null) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'gif' => 'image/gif',
            'jpeg', 'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => null,
        };

        if ($mimeType === null) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    public static function isSafeReference(mixed $imageReference): bool
    {
        if (! is_string($imageReference) || $imageReference === '' || str_contains($imageReference, "\0")) {
            return false;
        }

        if (str_contains($imageReference, '\\') || str_starts_with($imageReference, '/') || str_contains($imageReference, ':')) {
            return false;
        }

        if (filter_var($imageReference, FILTER_VALIDATE_URL) !== false || str_contains($imageReference, '://')) {
            return false;
        }

        $normalizedReference = str_starts_with($imageReference, 'images/')
            ? substr($imageReference, strlen('images/'))
            : $imageReference;

        if ($normalizedReference === '' || str_starts_with($normalizedReference, '/')) {
            return false;
        }

        foreach (explode('/', $normalizedReference) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        $extension = strtolower(pathinfo($normalizedReference, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    public static function normalizeReference(string $imageReference): string
    {
        if (! self::isSafeReference($imageReference)) {
            throw new InvalidSlideDocumentException(
                'Image references must be relative image filenames inside the document images directory.',
            );
        }

        return str_starts_with($imageReference, 'images/')
            ? substr($imageReference, strlen('images/'))
            : $imageReference;
    }

    private function relativePathFor(string $document, string $imageReference): string
    {
        $this->assertSafeDocumentName($document);

        $imageReference = self::normalizeReference($imageReference);

        return $this->imagesDirectoryFor($document).'/'.$imageReference;
    }

    private function imagesDirectoryFor(string $document): string
    {
        $filename = str_ends_with($document, '.json') ? $document : $document.'.json';
        $documentId = pathinfo($filename, PATHINFO_FILENAME);

        return self::STORAGE_DIRECTORY.'/'.$documentId.'/'.self::IMAGES_DIRECTORY;
    }

    private function assertSafeDocumentName(string $document): void
    {
        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $document)) {
            throw new InvalidSlideDocumentException('The requested slide document name is invalid.');
        }
    }

    private function isWithinDirectory(string $path, string $directory): bool
    {
        $normalizedPath = strtolower(str_replace('/', DIRECTORY_SEPARATOR, $path));
        $normalizedDirectory = rtrim(strtolower(str_replace('/', DIRECTORY_SEPARATOR, $directory)), DIRECTORY_SEPARATOR);

        return str_starts_with($normalizedPath, $normalizedDirectory.DIRECTORY_SEPARATOR);
    }
}
