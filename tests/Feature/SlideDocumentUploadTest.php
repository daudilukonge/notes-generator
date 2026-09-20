<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SlideDocumentUploadTest extends TestCase
{
    public function test_a_valid_json_document_is_uploaded_and_stored(): void
    {
        Storage::fake('slide_documents');
        $document = $this->validDocument();

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('course.json', $document),
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('preview_url');

        $files = Storage::disk('slide_documents')->allFiles('slide-documents');

        self::assertCount(1, $files);
        self::assertStringEndsWith('/document.json', $files[0]);
        self::assertSame($document, json_decode(
            Storage::disk('slide_documents')->get($files[0]),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));
    }

    public function test_document_theme_and_footer_are_accepted_and_stored(): void
    {
        Storage::fake('slide_documents');
        $document = $this->validDocument();
        $document['theme'] = [
            'primary' => '#123456',
            'secondary' => '#abcdef',
            'accent' => '#c77948',
        ];
        $document['footer'] = ['text' => 'AsiliSpot | Uundaji wa Bidhaa za Ngozi'];

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('themed-course.json', $document),
        ]);

        $response->assertRedirect();
        $files = Storage::disk('slide_documents')->allFiles('slide-documents');

        self::assertSame($document, json_decode(
            Storage::disk('slide_documents')->get($files[0]),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));
    }

    public function test_an_invalid_theme_color_is_rejected(): void
    {
        Storage::fake('slide_documents');
        $document = $this->validDocument();
        $document['theme'] = [
            'primary' => 'blue',
            'secondary' => '#abcdef',
            'accent' => '#c77948',
        ];

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('invalid-theme.json', $document),
        ]);

        $this->assertUploadErrorContains($response, 'invalid structure');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_an_uploaded_document_can_be_previewed(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('preview-course.json', $this->validDocument()),
        ]);

        $previewUrl = $response->getSession()->get('preview_url');

        self::assertIsString($previewUrl);

        $this->get($previewUrl)
            ->assertOk()
            ->assertSee('Uploaded course')
            ->assertDontSee('slide__page-number', false);
    }

    public function test_malformed_json_is_rejected_with_a_friendly_message(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('malformed.json', '{ "course": '),
        ]);

        $this->assertUploadErrorContains($response, 'malformed JSON');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_invalid_document_structure_is_rejected(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Invalid course'],
            'module' => ['number' => 1, 'title' => 'Invalid module'],
            'slides' => [['type' => 'content', 'title' => 'Missing content']],
        ];

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('invalid-structure.json', $document),
        ]);

        $this->assertUploadErrorContains($response, 'invalid structure');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_unsupported_slide_type_is_rejected(): void
    {
        Storage::fake('slide_documents');
        $document = $this->validDocument();
        $document['slides'][0]['type'] = 'video';

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('unsupported.json', $document),
        ]);

        $this->assertUploadErrorContains($response, 'unsupported slide type');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_non_json_upload_is_rejected(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => UploadedFile::fake()->createWithContent(
                'notes.txt',
                json_encode($this->validDocument(), JSON_THROW_ON_ERROR),
            )->mimeType('text/plain'),
        ]);

        $this->assertUploadErrorContains($response, 'must use the .json or .zip extension');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }

    public function test_a_missing_upload_is_rejected(): void
    {
        Storage::fake('slide_documents');
        $response = $this->post(route('slides.upload.store'));

        $this->assertUploadErrorContains($response, 'Please choose a JSON document');
    }

    public function test_unsafe_original_filenames_cannot_escape_the_storage_directory(): void
    {
        Storage::fake('slide_documents');

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('../../escape.json', $this->validDocument()),
        ]);

        $response->assertRedirect();

        $files = Storage::disk('slide_documents')->allFiles('slide-documents');

        self::assertCount(1, $files);
        self::assertStringStartsWith('slide-documents/', $files[0]);
        self::assertStringNotContainsString('..', $files[0]);
        self::assertFalse(Storage::disk('slide_documents')->exists('escape.json'));
    }

    /** @param array<string, mixed>|string $content */
    private function jsonUpload(string $filename, array|string $content): UploadedFile
    {
        $json = is_array($content) ? json_encode($content, JSON_THROW_ON_ERROR) : $content;

        return UploadedFile::fake()
            ->createWithContent($filename, $json)
            ->mimeType('application/json');
    }

    /** @return array<string, mixed> */
    private function validDocument(): array
    {
        return [
            'course' => ['title' => 'Uploaded course'],
            'module' => ['number' => 1, 'title' => 'Uploaded module'],
            'slides' => [['type' => 'title', 'title' => 'Uploaded course']],
        ];
    }

    private function assertUploadErrorContains($response, string $message): void
    {
        $response->assertSessionHasErrors('file');
        $errors = session('errors');

        self::assertStringContainsString($message, (string) $errors->first('file'));
    }

    public function test_local_image_references_cannot_escape_the_document_image_directory(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Image course'],
            'module' => ['number' => 1, 'title' => 'Image module'],
            'slides' => [[
                'type' => 'image-text',
                'title' => 'Unsafe image',
                'text' => ['Text'],
                'imageUrl' => '../../outside.png',
            ]],
        ];

        $response = $this->post(route('slides.upload.store'), [
            'file' => $this->jsonUpload('unsafe-image.json', $document),
        ]);

        $this->assertUploadErrorContains($response, 'local image filename');
        self::assertSame([], Storage::disk('slide_documents')->allFiles('slide-documents'));
    }
}
