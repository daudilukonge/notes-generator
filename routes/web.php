<?php

use App\Http\Controllers\SlideDocumentImageController;
use App\Http\Controllers\SlideDocumentUploadController;
use App\Http\Controllers\SlidePdfController;
use App\Http\Controllers\SlidePreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SlideDocumentUploadController::class, 'create'])
    ->name('slides.upload');

Route::get('/slides/upload', [SlideDocumentUploadController::class, 'create'])
    ->name('slides.upload.form');

Route::post('/slides/upload', [SlideDocumentUploadController::class, 'store'])
    ->name('slides.upload.store');

Route::get('/slides/{document}/images/{image}', [SlideDocumentImageController::class, 'show'])
    ->where([
        'document' => '[A-Za-z0-9][A-Za-z0-9._-]*',
        'image' => '.*',
    ])
    ->name('slides.image');

Route::get('/slides/{document}/pdf', [SlidePdfController::class, 'show'])
    ->where('document', '[A-Za-z0-9][A-Za-z0-9._-]*')
    ->name('slides.pdf');

Route::get('/slides/{document}', [SlidePreviewController::class, 'show'])
    ->where('document', '[A-Za-z0-9][A-Za-z0-9._-]*')
    ->name('slides.preview');
