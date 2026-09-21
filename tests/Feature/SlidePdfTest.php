<?php

namespace Tests\Feature;

use App\Exceptions\SlidePdfGenerationException;
use App\Services\Slides\CourseDocumentLoader;
use App\Services\Slides\SlidePdfGenerator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SlidePdfTest extends TestCase
{
    public function test_a_stored_document_downloads_a_multi_page_pdf_with_local_images(): void
    {
        Storage::fake('slide_documents');
        $document = $this->completeDocument();
        $storage = Storage::disk('slide_documents');
        $storage->put('slide-documents/pdf-document/document.json', json_encode($document, JSON_THROW_ON_ERROR));
        $storage->put('slide-documents/pdf-document/images/skin.png', $this->imageContents());

        $response = $this->get(route('slides.pdf', ['document' => 'pdf-document']));
        $pdf = $response->getContent();

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="pdf-course-module-2-pdf-module.pdf"')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame(strlen($pdf), (int) $response->headers->get('Content-Length'));
        self::assertSame(4, preg_match_all('/\/Type\s+\/Page(?!s)\b/', $pdf));
        self::assertStringContainsString('/Subtype /Image', $pdf);
    }

    public function test_the_preview_exposes_the_pdf_download_action(): void
    {
        $response = $this->get(route('slides.preview', [
            'document' => 'uundaji-wa-bidhaa-za-ngozi-ph-testing',
        ]));

        $response->assertOk()
            ->assertSee('Generate PDF')
            ->assertSee(route('slides.pdf', ['document' => 'uundaji-wa-bidhaa-za-ngozi-ph-testing']), false);
    }

    public function test_a_missing_document_returns_not_found_without_generating_a_pdf(): void
    {
        $this->get(route('slides.pdf', ['document' => 'missing-document']))
            ->assertNotFound()
            ->assertSee('Slide document not found')
            ->assertSee('could not be found');
    }

    public function test_invalid_json_returns_a_safe_validation_error(): void
    {
        $this->app->instance(
            CourseDocumentLoader::class,
            new CourseDocumentLoader(base_path('tests/Fixtures/slide-documents')),
        );

        $this->get(route('slides.pdf', ['document' => 'invalid-json']))
            ->assertStatus(422)
            ->assertSee('contains invalid JSON')
            ->assertDontSee('Stack trace');
    }

    public function test_a_missing_local_image_returns_a_safe_validation_error(): void
    {
        Storage::fake('slide_documents');
        $document = $this->completeDocument();
        $document['slides'][3]['image'] = 'images/missing.png';
        Storage::disk('slide_documents')->put(
            'slide-documents/missing-image/document.json',
            json_encode($document, JSON_THROW_ON_ERROR),
        );

        $this->get(route('slides.pdf', ['document' => 'missing-image']))
            ->assertStatus(422)
            ->assertSee('required by the document could not be found')
            ->assertDontSee('storage/app');
    }

    public function test_pdf_generation_failures_return_a_safe_server_error(): void
    {
        $this->mock(SlidePdfGenerator::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(new SlidePdfGenerationException);
        });

        $response = $this->get(route('slides.pdf', [
            'document' => 'uundaji-wa-bidhaa-za-ngozi-ph-testing',
        ]));

        $response->assertStatus(500)
            ->assertSee('The PDF could not be generated. Please try again.')
            ->assertDontSee('The slide PDF could not be generated.');
    }

    /** @return array<string, mixed> */
    private function completeDocument(): array
    {
        return [
            'course' => ['title' => 'PDF Course'],
            'module' => ['number' => 2, 'title' => 'PDF Module'],
            'theme' => [
                'primary' => '#123456',
                'secondary' => '#abcdef',
                'accent' => '#c77948',
            ],
            'footer' => ['text' => 'PDF Course | Custom footer'],
            'slides' => [
                ['type' => 'title', 'title' => 'PDF title', 'subtitle' => 'Subtitle'],
                ['type' => 'content', 'title' => 'Content', 'content' => ['A paragraph.']],
                ['type' => 'bullet-list', 'title' => 'Bullets', 'items' => ['One', 'Two']],
                [
                    'type' => 'image-text',
                    'title' => 'Image',
                    'text' => ['Image text.'],
                    'image' => 'images/skin.png',
                    'imageAlt' => 'Skin diagram',
                ],
            ],
        ];
    }

    private function imageContents(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
    }

    public function test_pdf_generation_keeps_rendering_when_organization_logo_is_missing(): void
    {
        Storage::fake('slide_documents');
        $document = $this->completeDocument();
        $document['organization'] = [
            'name' => 'PDF organization',
            'logo' => 'images/missing-logo.png',
        ];
        $storage = Storage::disk('slide_documents');
        $storage->put('slide-documents/missing-logo/document.json', json_encode($document, JSON_THROW_ON_ERROR));
        $storage->put('slide-documents/missing-logo/images/skin.png', $this->imageContents());

        $response = $this->get(route('slides.pdf', ['document' => 'missing-logo']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        self::assertSame(4, preg_match_all('#/Type[[:space:]]+/Page(?!s)#', $response->getContent()));
    }

    public function test_structured_document_pdf_keeps_explicit_order_and_local_images(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Structured PDF course'],
            'module' => ['number' => 1, 'title' => 'Structured PDF module'],
            'author' => ['name' => 'PDF author', 'description' => 'PDF description'],
            'organization' => ['name' => 'PDF organization', 'logo' => 'logo.png'],
            'document' => ['year' => 2026],
            'slides' => [
                ['type' => 'cover', 'title' => 'Structured PDF course'],
                ['type' => 'module-title', 'title' => 'Structured PDF module'],
                [
                    'type' => 'image-text',
                    'title' => 'Local image',
                    'text' => ['Image text.'],
                    'image' => 'images/skin.png',
                    'imageAlt' => 'Skin diagram',
                    'imagePosition' => 'right',
                ],
                ['type' => 'end', 'title' => 'ASANTE SANA', 'subtitle' => 'Tukutane katika Moduli 2', 'designation' => 'Mwisho wa Moduli 1'],
            ],
        ];
        $storage = Storage::disk('slide_documents');
        $storage->put('slide-documents/structured-pdf/document.json', json_encode($document, JSON_THROW_ON_ERROR));
        $storage->put('slide-documents/structured-pdf/images/skin.png', $this->imageContents());
        $storage->put('slide-documents/structured-pdf/images/logo.png', $this->imageContents());

        $response = $this->get(route('slides.pdf', ['document' => 'structured-pdf']));
        $pdf = $response->getContent();

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame(4, preg_match_all('/\/Type\s+\/Page(?!s)\b/', $pdf));
        self::assertStringContainsString('/Subtype /Image', $pdf);
    }
}
