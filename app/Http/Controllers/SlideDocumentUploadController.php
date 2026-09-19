<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidSlideDocumentException;
use App\Http\Requests\StoreSlideDocumentRequest;
use App\Services\Slides\SlideDocumentPackageImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Throwable;

class SlideDocumentUploadController extends Controller
{
    public function create(): View
    {
        return view('welcome');
    }

    public function store(
        StoreSlideDocumentRequest $request,
        SlideDocumentPackageImporter $packageImporter,
    ): RedirectResponse {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file('file');
        $originalFilename = $uploadedFile->getClientOriginalName();

        try {
            $documentIdentifier = $request->isPackage()
                ? $packageImporter->storePackage($uploadedFile)
                : $packageImporter->storeJson($uploadedFile->get(), $originalFilename);
        } catch (InvalidSlideDocumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['file' => $this->documentErrorMessage($exception)]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['file' => 'The document could not be stored. Please try again.']);
        }

        return redirect()
            ->route('slides.preview', ['document' => $documentIdentifier])
            ->with('success', 'The document was uploaded successfully.')
            ->with('original_filename', $originalFilename)
            ->with('preview_url', route('slides.preview', ['document' => $documentIdentifier]));
    }

    private function documentErrorMessage(InvalidSlideDocumentException $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'contains invalid JSON')) {
            return 'The uploaded file contains malformed JSON.';
        }

        if (str_contains($message, 'ZIP package')) {
            return $message;
        }

        if (str_contains($message, 'Unsupported slide type')) {
            return 'The uploaded document contains an unsupported slide type. '.$message;
        }

        return 'The uploaded document has an invalid structure. '.$message;
    }
}
