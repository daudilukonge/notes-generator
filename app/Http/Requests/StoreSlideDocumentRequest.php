<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreSlideDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'extensions:json,zip', 'max:20480'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if (! $file instanceof UploadedFile || $validator->errors()->has('file')) {
                    return;
                }

                $extension = strtolower($file->getClientOriginalExtension());
                $allowedMimeTypes = $extension === 'zip'
                    ? ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream']
                    : ['application/json', 'application/ld+json', 'text/json'];

                if (! in_array($file->getClientMimeType(), $allowedMimeTypes, true)) {
                    $validator->errors()->add(
                        'file',
                        $extension === 'zip'
                            ? 'The uploaded file must use a ZIP MIME type.'
                            : 'The uploaded file must use a JSON MIME type.',
                    );
                }

                if ($extension === 'json' && $file->getSize() > 5120 * 1024) {
                    $validator->errors()->add('file', 'The JSON document may not be larger than 5 MB.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a JSON document or ZIP package to upload.',
            'file.file' => 'The upload must be a valid file.',
            'file.extensions' => 'The uploaded file must use the .json or .zip extension.',
            'file.max' => 'The uploaded document package may not be larger than 20 MB.',
        ];
    }

    public function isPackage(): bool
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile
            && strtolower($file->getClientOriginalExtension()) === 'zip';
    }
}
