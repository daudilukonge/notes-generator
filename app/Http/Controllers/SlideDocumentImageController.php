<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidSlideDocumentException;
use App\Services\Slides\SlideDocumentImageResolver;
use Symfony\Component\HttpFoundation\Response;

class SlideDocumentImageController extends Controller
{
    public function __construct(private readonly SlideDocumentImageResolver $imageResolver) {}

    public function show(string $document, string $image): Response
    {
        try {
            $path = $this->imageResolver->existingPathFor($document, $image);
        } catch (InvalidSlideDocumentException) {
            abort(404);
        }

        if ($path === null) {
            abort(404);
        }

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
