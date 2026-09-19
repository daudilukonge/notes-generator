<?php

namespace App\Services\Slides;

use App\Exceptions\InvalidSlideDocumentException;
use App\Exceptions\SlidePdfGenerationException;
use Spatie\Browsershot\Browsershot;
use Throwable;

class SlidePdfGenerator
{
    public function __construct(private readonly SlideRenderer $slideRenderer) {}

    /** @param array<string, mixed> $document */
    public function generate(array $document, string $documentName): string
    {
        try {
            $html = $this->slideRenderer->render($document, $documentName, true)->render();
        } catch (InvalidSlideDocumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SlidePdfGenerationException($exception);
        }

        try {
            $browsershot = Browsershot::html($html)
                ->paperSize(16, 9, 'in')
                ->margins(0, 0, 0, 0, 'in')
                ->showBackground()
                ->setOption('preferCSSPageSize', true)
                ->newHeadless()
                ->timeout((int) config('slides.pdf.timeout', 60));

            $nodeBinary = config('slides.pdf.node_binary');
            $npmBinary = config('slides.pdf.npm_binary');
            $chromePath = config('slides.pdf.chrome_path');

            if (is_string($nodeBinary) && $nodeBinary !== '') {
                $browsershot->setNodeBinary($nodeBinary);
            }

            if (is_string($npmBinary) && $npmBinary !== '') {
                $browsershot->setNpmBinary($npmBinary);
            }

            if (is_string($chromePath) && $chromePath !== '') {
                $browsershot->setChromePath($chromePath);
            }

            if ((bool) config('slides.pdf.no_sandbox', false)) {
                $browsershot->noSandbox();
            }

            $pdf = $browsershot->pdf();

            if (! str_starts_with($pdf, '%PDF-')) {
                throw new SlidePdfGenerationException;
            }

            return $pdf;
        } catch (SlidePdfGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SlidePdfGenerationException($exception);
        }
    }
}
