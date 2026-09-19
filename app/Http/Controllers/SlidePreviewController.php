<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidSlideDocumentException;
use App\Exceptions\SlideDocumentNotFoundException;
use App\Services\Slides\CourseDocumentLoader;
use App\Services\Slides\SlideRenderer;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

class SlidePreviewController extends Controller
{
    public function __construct(
        private readonly CourseDocumentLoader $documentLoader,
        private readonly SlideRenderer $slideRenderer,
    ) {}

    public function show(string $document): View|Response
    {
        try {
            $courseDocument = $this->documentLoader->load($document);

            return $this->slideRenderer->render($courseDocument, $document);
        } catch (SlideDocumentNotFoundException $exception) {
            return response()->view('slides.error', [
                'title' => 'Slide document not found',
                'message' => $exception->getMessage(),
            ], 404);
        } catch (InvalidSlideDocumentException $exception) {
            return response()->view('slides.error', [
                'title' => 'Unable to render slide document',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
