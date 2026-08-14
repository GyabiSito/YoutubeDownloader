<?php

use App\Services\YoutubeDownloaderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('downloads:cleanup', function () {
    $deleted = app(YoutubeDownloaderService::class)->cleanupExpiredDownloads();

    $this->info("Removed {$deleted} expired download directories.");
})->purpose('Remove abandoned prepared downloads');
