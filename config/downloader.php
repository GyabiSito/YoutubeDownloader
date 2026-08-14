<?php

return [
    'yt_dlp_binary' => env('YT_DLP_BINARY', 'yt-dlp'),
    'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),
    'deno_binary' => env('DENO_BINARY', 'deno'),
    'pot_provider_url' => env('YOUTUBE_POT_PROVIDER_URL', 'http://pot-provider:4416'),
    'info_timeout' => (int) env('YOUTUBE_INFO_TIMEOUT', 45),
    'download_timeout' => (int) env('YOUTUBE_DOWNLOAD_TIMEOUT', 1800),
    'prepared_ttl' => (int) env('YOUTUBE_PREPARED_TTL', 10),
    'cleanup_after' => (int) env('YOUTUBE_CLEANUP_AFTER', 120),
];
