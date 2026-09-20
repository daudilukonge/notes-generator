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
            ->assertSee('Sayansi ya Ngozi')
            ->assertSee('Module 1')
            ->assertSee('Tukutane katika Moduli 2')
            ->assertSee('Mecktilda Mugarula')
            ->assertSee('Mwisho wa Moduli 1')
            ->assertDontSee('Moduli 1: Utambulisho wa Sayansi ya Ngozi')
            ->assertSee('Sayansi ya Ngozi')
            ->assertDontSee('slide__page-number', false)
            ->assertSee('2026')
            ->assertSee('AsiliSpot Formulation Organization | www.asilispot.org | +255 768 078 727')
            ->assertSee('slides-', false);

        $content = $response->getContent();

        self::assertLessThan(
            strpos($content, 'Tabaka Kuu za Ngozi'),
            strpos($content, 'Umuhimu wa Ngozi'),
        );
        self::assertLessThan(
            strpos($content, 'ASANTE SANA'),
            strpos($content, 'Tabaka Kuu za Ngozi'),
        );
    }

    public function test_real_module_one_renders_custom_theme_and_footer_on_every_slide(): void
    {
        Storage::fake('slide_documents');
        $document = app(CourseDocumentLoader::class)->load('uundaji-wa-bidhaa-za-ngozi-ph-testing');
        $document['theme'] = [
            'primary' => '#123456',
            'secondary' => '#abcdef',
            'accent' => '#c77948',
        ];
        $document['footer'] = ['text' => 'AsiliSpot | Uundaji wa Bidhaa za Ngozi'];
        Storage::disk('slide_documents')->put(
            'slide-documents/real-module-one/document.json',
            json_encode($document, JSON_THROW_ON_ERROR),
        );

        $response = $this->get(route('slides.preview', ['document' => 'real-module-one']));

        $response->assertOk()
            ->assertSee('Sayansi ya Ngozi')
            ->assertSee('Umuhimu wa Ngozi')
            ->assertSee('Tabaka Kuu za Ngozi')
            ->assertSee('ASANTE SANA')
            ->assertSee('slide--title', false)
            ->assertSee('style="--slide-primary: #123456; --slide-secondary: #abcdef; --slide-accent: #c77948;"', false);

        self::assertSame(60, substr_count($response->getContent(), 'AsiliSpot | Uundaji wa Bidhaa za Ngozi'));
    }

    public function test_the_loader_exposes_the_ordered_slide_data(): void
    {
        $loader = app(CourseDocumentLoader::class);
        $document = $loader->load('uundaji-wa-bidhaa-za-ngozi-ph-testing');

        self::assertSame('Uundaji wa Bidhaa za Ngozi', $document['course']['title']);
        self::assertSame('Sayansi ya Ngozi', $document['module']['title']);
        $types = array_column($loader->slides($document), 'type');

        self::assertSame(['cover', 'module-title', 'content'], array_slice($types, 0, 3));
        self::assertSame('end', end($types));
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

    public function test_image_text_positions_render_left_right_and_default_right_layouts(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Image position course'],
            'module' => ['number' => 1, 'title' => 'Image position module'],
            'slides' => [
                [
                    'type' => 'image-text',
                    'title' => 'Left image',
                    'text' => ['Left text'],
                    'imageUrl' => 'ph.png',
                    'imagePosition' => 'left',
                ],
                [
                    'type' => 'image-text',
                    'title' => 'Right image',
                    'text' => ['Right text'],
                    'image' => 'images/ph.png',
                    'imagePosition' => 'right',
                ],
                [
                    'type' => 'image-text',
                    'title' => 'Default image',
                    'text' => ['Default text'],
                    'imageUrl' => 'ph.png',
                ],
            ],
        ];
        $disk = Storage::disk('slide_documents');
        $disk->put('slide-documents/image-position-course.json', json_encode($document, JSON_THROW_ON_ERROR));
        $disk->put(
            'slide-documents/image-position-course/images/ph.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );

        $response = $this->get(route('slides.preview', ['document' => 'image-position-course']));

        $response->assertOk();
        $content = $response->getContent();
        self::assertSame(1, substr_count($content, 'slide--image-left'));
        self::assertSame(2, substr_count($content, 'slide--image-right'));
    }

    public function test_image_text_images_use_contained_non_cropping_sizing(): void
    {
        $styles = file_get_contents(resource_path('css/slides.css'));

        self::assertIsString($styles);
        self::assertStringContainsString('object-fit: contain;', $styles);
        self::assertStringContainsString('max-width: 100%;', $styles);
        self::assertStringContainsString('max-height: 100%;', $styles);
        self::assertStringNotContainsString('object-fit: cover;', $styles);
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

    public function test_structured_document_renders_data_driven_shell_and_single_end_slide(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Structured course'],
            'module' => ['number' => 1, 'title' => 'Structured module'],
            'author' => [
                'name' => 'Course author',
                'description' => 'Course author description',
            ],
            'organization' => [
                'name' => 'Example Organization',
                'logo' => 'logo.png',
                'website' => 'example.test',
                'phone' => '+255 700 000 000',
            ],
            'document' => ['year' => 2026],
            'footer' => ['text' => 'Example Organization | example.test | +255 700 000 000'],
            'slides' => [
                ['type' => 'cover', 'title' => 'Structured course'],
                ['type' => 'module-title', 'title' => 'Structured module'],
                ['type' => 'content', 'title' => 'Lesson', 'content' => ['Lesson content.']],
                ['type' => 'end', 'title' => 'ASANTE SANA', 'subtitle' => 'Next module.', 'designation' => 'Closing designation'],
            ],
        ];
        Storage::disk('slide_documents')->put(
            'slide-documents/structured-document/document.json',
            json_encode($document, JSON_THROW_ON_ERROR),
        );
        Storage::disk('slide_documents')->put(
            'slide-documents/structured-document/images/logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );

        $response = $this->get(route('slides.preview', ['document' => 'structured-document']));

        $response->assertOk()
            ->assertSee('Example Organization')
            ->assertSee(route('slides.image', ['document' => 'structured-document', 'image' => 'logo.png']), false)
            ->assertSee('Course author')
            ->assertSee('2026')
            ->assertSee('slide--module-title', false)
            ->assertSee('slide--end', false)
            ->assertSee('Closing designation')
            ->assertSee('slide__end-designation', false)
            ->assertDontSee('slide__page-number', false);

        self::assertSame(1, substr_count($response->getContent(), 'slide__organization'));
    }

    public function test_a_final_course_can_render_an_explicit_course_end_message(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Final course'],
            'module' => ['number' => 1, 'title' => 'Final module'],
            'is_final' => true,
            'author' => ['name' => 'Final author'],
            'slides' => [
                ['type' => 'cover', 'title' => 'Final course', 'designation' => 'Module 1'],
                ['type' => 'module-title', 'title' => 'Final module'],
                ['type' => 'content', 'title' => 'Lesson', 'content' => ['Lesson content.']],
                [
                    'type' => 'end',
                    'title' => 'ASANTE SANA',
                    'subtitle' => 'Karibu tena katika kozi zetu nyingine',
                    'designation' => 'MWISHO WA KOZI',
                ],
            ],
        ];
        Storage::disk('slide_documents')->put(
            'slide-documents/final-course/document.json',
            json_encode($document, JSON_THROW_ON_ERROR),
        );

        $this->get(route('slides.preview', ['document' => 'final-course']))
            ->assertOk()
            ->assertSee('Karibu tena katika kozi zetu nyingine')
            ->assertSee('MWISHO WA KOZI')
            ->assertSee('Final author');
    }

    public function test_structured_documents_require_one_final_end_slide(): void
    {
        Storage::fake('slide_documents');
        $document = [
            'course' => ['title' => 'Incomplete course'],
            'module' => ['number' => 1, 'title' => 'Incomplete module'],
            'slides' => [
                ['type' => 'cover', 'title' => 'Incomplete course'],
                ['type' => 'module-title', 'title' => 'Incomplete module'],
                ['type' => 'content', 'title' => 'Lesson', 'content' => ['Lesson content.']],
            ],
        ];
        Storage::disk('slide_documents')->put(
            'slide-documents/incomplete-structured/document.json',
            json_encode($document, JSON_THROW_ON_ERROR),
        );

        $this->get(route('slides.preview', ['document' => 'incomplete-structured']))
            ->assertStatus(422)
            ->assertSee('exactly one end slide');
    }
}
