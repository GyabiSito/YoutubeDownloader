<?php

use App\Http\Controllers\DownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DownloadController::class, 'index'])->name('home');
Route::post('/video-info', [DownloadController::class, 'info'])
    ->middleware('throttle:30,1')
    ->name('video.info');
Route::post('/download/prepare', [DownloadController::class, 'prepare'])
    ->middleware('throttle:5,1')
    ->name('download.prepare');
Route::get('/download/{token}', [DownloadController::class, 'download'])
    ->whereUuid('token')
    ->middleware('throttle:10,1')
    ->name('download');
