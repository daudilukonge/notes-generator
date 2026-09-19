<?php

namespace Tests\Feature;

use App\Services\Slides\CourseDocumentLoader;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SlidePreviewTest extends TestCase
{
    public function test_a_json_course_document_renders_every_slide_in_order(): void
    {
        $response = $this->get(route('slides.preview', [
            'document' => 'uundaji-wa-bidhaa-za-ngozi-ph-testing',
        ]));

        $response->assertOk()
            ->assertSee('Uundaji wa Bidhaa za Ngozi')
            ->assertSee('PH Testing in Skincare')
            ->assertSee('1 / 4')
            ->assertSee('4 / 4')
            ->assertSee('slides-', false);

        $content = $response->getContent();

        self::assertLessThan(
            strpos($content, 'Hatua za awali za kipimo'),
            strpos($content, 'Kwa nini pH ni muhimu?'),
        );
        self::assertLessThan(
            strpos($content, 'Pima, rekodi, kisha rekebisha'),
            strpos($content, 'Hatua za awali za kipimo'),
        );
    }

    public function test_the_loader_exposes_the_ordered_slide_data(): void
    {
        $loader = app(CourseDocumentLoader::class);
        $document = $loader->load('uundaji-wa-bidhaa-za-ngozi-ph-testing');

        self::assertSame('Uundaji wa Bidhaa za Ngozi', $document['course']['title']);
        self::assertSame('PH Testing in Skincare', $document['module']['title']);
        self::assertSame(
            ['title', 'content', 'bullet-list', 'image-text'],
            array_column($loader->slides($document), 'type'),
        );
    }

    public function test_an_unsupported_slide_type_is_reported_as_a_validation_error(): void
    {
        $this->app->instance(CourseDocumentLoader::class, new CourseDocumentLoader(
            base_path('tests/Fixtures/slide-documents'),
        ));

        $response = $this->get(route('slides.preview', ['document' => 'unsupported-slide-type']));

        $response->assertStatus(422)
            ->assertSee('Unable to render slide document')
            ->assertSee('Unsupported slide type');
    }

    public function test_a_missing_json_document_returns_a_not_found_response(): void
    {
        $response = $this->get(route('slides.preview', ['document' => 'missing-document']));

        $response->assertNotFound()
            ->assertSee('Slide document not found');
    }

    public function test_invalid_json_is_reported_as_a_validation_error(): void
    {
        $this->app->instance(CourseDocumentLoader::class, new CourseDocumentLoader(
            base_path('tests/Fixtures/slide-documents'),
        ));

        $response = $this->get(route('slides.preview', ['document' => 'invalid-json']));

        $response->assertStatus(422)
            ->assertSee('contains invalid JSON');
    }

    public function test_a_document_image_is_rendered_from_its_private_image_directory(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Local image course'],
            'module' => ['number' => 1, 'title' => 'Local image module'],
            'slides' => [[
                'type' => 'image-text',
                'title' => 'Local image',
                'text' => ['Text beside the image.'],
                'imageUrl' => 'ph.png',
                'imageAlt' => 'A one pixel image',
            ]],
        ];
        $disk = Storage::disk('slide_documents');
        $disk->put('slide-documents/local-image-course.json', json_encode($document, JSON_THROW_ON_ERROR));
        $disk->put(
            'slide-documents/local-image-course/images/ph.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );

        $this->get(route('slides.preview', ['document' => 'local-image-course']))
            ->assertOk()
            ->assertSee(route('slides.image', ['document' => 'local-image-course', 'image' => 'ph.png']), false)
            ->assertSee('data-slide-number="1"', false)
            ->assertSee('data-slide-count="1"', false);

        $this->get(route('slides.image', ['document' => 'local-image-course', 'image' => 'ph.png']))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_document_with_an_unsafe_image_reference_cannot_be_previewed(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Unsafe image course'],
            'module' => ['number' => 1, 'title' => 'Unsafe image module'],
            'slides' => [[
                'type' => 'image-text',
                'title' => 'Unsafe image',
                'text' => ['Text'],
                'imageUrl' => '../../outside.png',
            ]],
        ];
        Storage::disk('slide_documents')->put(
            'slide-documents/unsafe-image-course.json',
            json_encode($document, JSON_THROW_ON_ERROR),
        );

        $this->get(route('slides.preview', ['document' => 'unsafe-image-course']))
            ->assertStatus(422)
            ->assertSee('local image filename');
    }
}
