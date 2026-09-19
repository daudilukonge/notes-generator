<?php

namespace App\Services\Slides;

use App\Exceptions\InvalidSlideDocumentException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class SlideDocumentPackageImporter
{
    private const STORAGE_DISK = 'slide_documents';

    private const STORAGE_DIRECTORY = 'slide-documents';

    private const MAX_ENTRIES = 512;

    private const MAX_EXTRACTED_BYTES = 104857600;

    public function __construct(private readonly CourseDocumentLoader $documentLoader) {}

    public function storeJson(string $contents, string $originalFilename): string
    {
        $this->documentLoader->loadContents($contents, $originalFilename);

        return $this->storeDocument($contents, $originalFilename);
    }

    public function storePackage(UploadedFile $package): string
    {
        $packagePath = $package->getRealPath();

        if (! is_string($packagePath) || ! is_file($packagePath)) {
            throw new InvalidSlideDocumentException('The uploaded ZIP package could not be read.');
        }

        $zip = new ZipArchive;
        $openResult = $zip->open($packagePath);

        if ($openResult !== true) {
            throw new InvalidSlideDocumentException('The uploaded file could not be opened as a ZIP package.');
        }

        try {
            $archive = $this->inspectArchive($zip);
            $jsonContents = $zip->getFromName($archive['json']);

            if ($jsonContents === false) {
                throw new InvalidSlideDocumentException('The ZIP package JSON document could not be read.');
            }

            $document = $this->documentLoader->loadContents($jsonContents, $archive['json']);

            foreach ($this->documentLoader->imageReferences($document) as $imageReference) {
                $archiveImageName = 'images/'.$imageReference;

                if (! isset($archive['images'][$archiveImageName])) {
                    throw new InvalidSlideDocumentException(
                        "The ZIP package is missing the referenced image [{$archiveImageName}].",
                    );
                }
            }

            $documentIdentifier = $this->documentIdentifier($package->getClientOriginalName());

            return $this->storeDocumentFromArchive(
                $zip,
                $jsonContents,
                $documentIdentifier,
                $archive['images'],
            );
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{json: string, images: array<string, int>}
     */
    private function inspectArchive(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new InvalidSlideDocumentException('The ZIP package contains too many files.');
        }

        $jsonFiles = [];
        $imageFiles = [];
        $seenNames = [];
        $extractedBytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name) || $name === '' || str_contains($name, "\0")) {
                throw new InvalidSlideDocumentException('The ZIP package contains an invalid archive path.');
            }

            $this->assertSafeArchivePath($name);

            $normalizedName = strtolower($name);

            if (isset($seenNames[$normalizedName])) {
                throw new InvalidSlideDocumentException("The ZIP package contains a duplicate path [{$name}].");
            }

            $seenNames[$normalizedName] = true;

            if ($this->isSymlink($zip, $index)) {
                throw new InvalidSlideDocumentException('The ZIP package contains a symbolic link.');
            }

            if (str_ends_with($name, '/')) {
                $directory = rtrim($name, '/');

                if ($directory !== 'images' && ! str_starts_with($directory, 'images/')) {
                    throw new InvalidSlideDocumentException(
                        "The ZIP package contains an unexpected directory [{$name}].",
                    );
                }

                continue;
            }

            $entryStats = $zip->statIndex($index);

            if (! is_array($entryStats)) {
                throw new InvalidSlideDocumentException("The ZIP package entry [{$name}] could not be inspected.");
            }

            $entrySize = (int) ($entryStats['size'] ?? 0);
            $extractedBytes += $entrySize;

            if ($extractedBytes > self::MAX_EXTRACTED_BYTES) {
                throw new InvalidSlideDocumentException('The ZIP package expands beyond the allowed size.');
            }

            if (! str_contains($name, '/')) {
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
                    throw new InvalidSlideDocumentException(
                        "The ZIP package contains an unexpected file [{$name}].",
                    );
                }

                $jsonFiles[] = $name;

                continue;
            }

            if (! str_starts_with($name, 'images/')
                || ! SlideDocumentImageResolver::isSafeReference(substr($name, strlen('images/')))) {
                throw new InvalidSlideDocumentException(
                    "The ZIP package contains an unsupported file [{$name}]. Only image files inside images/ are allowed.",
                );
            }

            $imageFiles[$name] = $index;
        }

        if (count($jsonFiles) !== 1) {
            throw new InvalidSlideDocumentException('The ZIP package must contain exactly one JSON document file.');
        }

        return [
            'json' => $jsonFiles[0],
            'images' => $imageFiles,
        ];
    }

    private function assertSafeArchivePath(string $name): void
    {
        if (str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('/\A[A-Za-z]:\//', $name) === 1
            || str_contains($name, ':')) {
            throw new InvalidSlideDocumentException("The ZIP package contains an unsafe path [{$name}].");
        }

        $path = rtrim($name, '/');

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidSlideDocumentException("The ZIP package contains an unsafe path [{$name}].");
            }
        }
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $operatingSystem = 0;
        $externalAttributes = 0;

        if (! $zip->getExternalAttributesIndex(
            $index,
            $operatingSystem,
            $externalAttributes,
            ZipArchive::FL_UNCHANGED,
        )) {
            return false;
        }

        if ($operatingSystem !== ZipArchive::OPSYS_UNIX) {
            return false;
        }

        $fileType = ($externalAttributes >> 16) & 0xF000;

        return $fileType === 0xA000;
    }

    /** @param array<string, int> $imageEntries */
    private function storeDocumentFromArchive(
        ZipArchive $zip,
        string $jsonContents,
        string $documentIdentifier,
        array $imageEntries,
    ): string {
        $storage = Storage::disk(self::STORAGE_DISK);
        $documentDirectory = self::STORAGE_DIRECTORY.'/'.$documentIdentifier;

        try {
            if (! $storage->put($documentDirectory.'/document.json', $jsonContents)) {
                throw new RuntimeException('The document JSON could not be stored.');
            }

            foreach (array_keys($imageEntries) as $imageName) {
                $stream = $zip->getStream($imageName);

                if ($stream === false) {
                    throw new RuntimeException("The image [{$imageName}] could not be read from the ZIP package.");
                }

                try {
                    if (! $storage->put($documentDirectory.'/'.$imageName, $stream)) {
                        throw new RuntimeException("The image [{$imageName}] could not be stored.");
                    }
                } finally {
                    fclose($stream);
                }
            }
        } catch (Throwable $exception) {
            $storage->deleteDirectory($documentDirectory);

            throw $exception;
        }

        return $documentIdentifier;
    }

    private function storeDocument(string $contents, string $originalFilename): string
    {
        $documentIdentifier = $this->documentIdentifier($originalFilename);
        $storedPath = self::STORAGE_DIRECTORY.'/'.$documentIdentifier.'/document.json';

        if (! Storage::disk(self::STORAGE_DISK)->put($storedPath, $contents)) {
            throw new RuntimeException('The document JSON could not be stored.');
        }

        return $documentIdentifier;
    }

    private function documentIdentifier(string $originalFilename): string
    {
        $normalizedName = Str::slug(pathinfo(str_replace('\\', '/', $originalFilename), PATHINFO_FILENAME));
        $normalizedName = $normalizedName !== '' ? Str::limit($normalizedName, 80, '') : 'document';

        return $normalizedName.'-'.Str::uuid();
    }
}
