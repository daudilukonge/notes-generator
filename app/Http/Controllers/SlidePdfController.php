<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidSlideDocumentException;
use App\Exceptions\SlideDocumentNotFoundException;
use App\Exceptions\SlidePdfGenerationException;
use App\Services\Slides\CourseDocumentLoader;
use App\Services\Slides\SlidePdfGenerator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SlidePdfController extends Controller
{
    public function __construct(
        private readonly CourseDocumentLoader $documentLoader,
        private readonly SlidePdfGenerator $pdfGenerator,
    ) {}

    public function show(string $document): Response
    {
        try {
            $courseDocument = $this->documentLoader->load($document);
            $pdf = $this->pdfGenerator->generate($courseDocument, $document);
        } catch (SlideDocumentNotFoundException) {
            return response()->view('slides.error', [
                'title' => 'Slide document not found',
                'message' => 'The requested slide document could not be found.',
            ], Response::HTTP_NOT_FOUND);
        } catch (InvalidSlideDocumentException $exception) {
            return response()->view('slides.error', [
                'title' => 'Unable to generate PDF',
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (SlidePdfGenerationException $exception) {
            report($exception);

            return response()->view('slides.error', [
                'title' => 'Unable to generate PDF',
                'message' => 'The PDF could not be generated. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filenameFor($courseDocument).'"',
            'Content-Length' => (string) strlen($pdf),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param array<string, mixed> $document */
    private function filenameFor(array $document): string
    {
        $courseTitle = is_string($document['course']['title'] ?? null) ? $document['course']['title'] : 'slides';
        $moduleTitle = is_string($document['module']['title'] ?? null) ? $document['module']['title'] : '';
        $moduleNumber = is_int($document['module']['number'] ?? null) ? $document['module']['number'] : null;
        $parts = [Str::slug($courseTitle)];

        if ($moduleNumber !== null) {
            $parts[] = 'module-'.$moduleNumber;
        }

        if ($moduleTitle !== '') {
            $parts[] = Str::slug($moduleTitle);
        }

        $name = trim(implode('-', array_filter($parts)), '-');

        return Str::limit($name !== '' ? $name : 'slides', 120, '').'.pdf';
    }
}
