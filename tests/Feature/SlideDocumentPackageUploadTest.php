<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SlideDocumentPackageUploadTest extends TestCase
{
    public function test_a_valid_zip_package_is_uploaded_and_stored_in_a_document_directory(): void
    {
        Storage::fake('slide_documents');
        $document = $this->imageDocument();
        $documentJson = json_encode($document, JSON_THROW_ON_ERROR);

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'document.json' => $documentJson,
                'images/skin.png' => $this->imageContents(),
            ]),
        ]);

        $response->assertRedirect();
        $documentIdentifier = $this->documentIdentifierFrom($response->getTargetUrl());
        $storage = Storage::disk('slide_documents');

        self::assertSame(
            $documentJson,
            $storage->get("slide-documents/{$documentIdentifier}/document.json"),
        );
        self::assertSame(
            $this->imageContents(),
            $storage->get("slide-documents/{$documentIdentifier}/images/skin.png"),
        );
    }

    public function test_a_non_module_package_with_module_number_zero_is_uploaded(): void
    {
        Storage::fake('slide_documents');
        $document = $this->titleDocument();
        $document['course']['title'] = 'Hitimisho';
        $document['module'] = ['number' => 0, 'title' => 'Hitimisho'];

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'document.json' => json_encode($document, JSON_THROW_ON_ERROR),
            ], 'conclusion.zip'),
        ]);

        $response->assertRedirect();
        $documentIdentifier = $this->documentIdentifierFrom($response->getTargetUrl());

        self::assertSame(
            $document,
            json_decode(
                Storage::disk('slide_documents')->get("slide-documents/{$documentIdentifier}/document.json"),
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    public function test_an_uploaded_package_can_be_previewed_and_its_referenced_image_renders(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'course.json' => json_encode($this->imageDocument(), JSON_THROW_ON_ERROR),
                'images/skin.png' => $this->imageContents(),
            ], 'course-package.zip'),
        ]);
        $documentIdentifier = $this->documentIdentifierFrom($response->getTargetUrl());

        $this->get(route('slides.preview', ['document' => $documentIdentifier]))
            ->assertOk()
            ->assertSee('Package course')
            ->assertSee('images/skin.png', false)
            ->assertSee('Skin structure', false);

        $this->get(route('slides.image', [
            'document' => $documentIdentifier,
            'image' => 'skin.png',
        ]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_package_with_a_missing_referenced_image_is_rejected_without_storage(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'document.json' => json_encode($this->imageDocument(), JSON_THROW_ON_ERROR),
            ]),
        ]);

        $this->assertUploadErrorContains($response, 'missing the referenced image');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_a_package_with_malformed_json_is_rejected(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload(['document.json' => '{ "course": ']),
        ]);

        $this->assertUploadErrorContains($response, 'malformed JSON');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_a_package_with_an_invalid_document_structure_is_rejected(): void
    {
        Storage::fake('slide_documents');
        $invalidDocument = [
            'course' => ['title' => 'Invalid course'],
            'module' => ['number' => 1, 'title' => 'Invalid module'],
            'slides' => [['type' => 'content', 'title' => 'Missing content']],
        ];

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'document.json' => json_encode($invalidDocument, JSON_THROW_ON_ERROR),
            ]),
        ]);

        $this->assertUploadErrorContains($response, 'invalid structure');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_a_package_without_json_is_rejected(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload(['images/skin.png' => $this->imageContents()]),
        ]);

        $this->assertUploadErrorContains($response, 'exactly one JSON document file');
    }

    public function test_a_package_with_multiple_json_documents_is_rejected(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'document.json' => json_encode($this->titleDocument(), JSON_THROW_ON_ERROR),
                'other.json' => json_encode($this->titleDocument(), JSON_THROW_ON_ERROR),
            ]),
        ]);

        $this->assertUploadErrorContains($response, 'exactly one JSON document file');
    }

    public function test_a_zip_with_a_path_traversal_entry_is_rejected_without_escape(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'document.json' => json_encode($this->titleDocument(), JSON_THROW_ON_ERROR),
                '../outside.txt' => 'do not extract me',
            ]),
        ]);

        $this->assertUploadErrorContains($response, 'unsafe path');
        self::assertFalse(Storage::disk('slide_documents')->exists('outside.txt'));
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_a_zip_with_an_absolute_entry_path_is_rejected(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                '/document.json' => json_encode($this->titleDocument(), JSON_THROW_ON_ERROR),
            ]),
        ]);

        $this->assertUploadErrorContains($response, 'unsafe path');
    }

    public function test_a_package_with_an_absolute_image_reference_is_rejected(): void
    {
        Storage::fake('slide_documents');
        $document = $this->imageDocument();
        $document['slides'][0]['image'] = 'C:/outside/skin.png';

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'document.json' => json_encode($document, JSON_THROW_ON_ERROR),
                'images/skin.png' => $this->imageContents(),
            ]),
        ]);

        $this->assertUploadErrorContains($response, 'invalid structure');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_a_file_with_a_zip_extension_that_cannot_be_opened_is_rejected(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => UploadedFile::fake()
                ->createWithContent('broken.zip', 'not a ZIP archive')
                ->mimeType('application/zip'),
        ]);

        $this->assertUploadErrorContains($response, 'could not be opened as a ZIP package');
    }

    public function test_a_zip_with_an_unsupported_script_entry_is_rejected(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->zipUpload([
                'document.json' => json_encode($this->titleDocument(), JSON_THROW_ON_ERROR),
                'images/evil.php' => '<?php echo "no";',
            ]),
        ]);

        $this->assertUploadErrorContains($response, 'unsupported file');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    /** @param array<string, mixed> $entries */
    private function zipUpload(array $entries, string $filename = 'course-package.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'notes-generator-');

        self::assertIsString($path);

        $zip = new ZipArchive;
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, (string) $contents);
        }

        self::assertTrue($zip->close());
        $contents = file_get_contents($path);
        unlink($path);

        self::assertIsString($contents);

        return UploadedFile::fake()
            ->createWithContent($filename, $contents)
            ->mimeType('application/zip');
    }

    /** @return array<string, mixed> */
    private function imageDocument(): array
    {
        return [
            'course' => ['title' => 'Package course'],
            'module' => ['number' => 1, 'title' => 'Package module'],
            'slides' => [[
                'type' => 'image-text',
                'title' => 'Skin structure',
                'text' => ['Skin structure'],
                'image' => 'images/skin.png',
                'imageAlt' => 'Skin structure',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function titleDocument(): array
    {
        return [
            'course' => ['title' => 'Package course'],
            'module' => ['number' => 1, 'title' => 'Package module'],
            'slides' => [['type' => 'title', 'title' => 'Package course']],
        ];
    }

    private function imageContents(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
    }

    private function documentIdentifierFrom(string $url): string
    {
        return basename((string) parse_url($url, PHP_URL_PATH));
    }

    private function assertUploadErrorContains($response, string $message): void
    {
        $response->assertRedirect()
            ->assertSessionHasErrors('file');

        self::assertStringContainsString($message, (string) session('errors')->first('file'));
    }
}
