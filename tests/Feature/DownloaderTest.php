<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\YoutubeDownloaderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DownloaderTest extends TestCase
{
    public function test_home_page_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Descargá sólo')
            ->assertSee('download-form');
    }

    public function test_video_info_rejects_non_youtube_urls_before_running_a_process(): void
    {
        $this->postJson('/video-info', ['url' => 'http://127.0.0.1/private'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    public function test_prepare_rejects_an_invalid_quality_before_running_a_process(): void
    {
        $this->postJson('/download/prepare', [
            'url' => 'https://youtube.com/watch?v=abc123',
            'type' => 'video',
            'quality' => 999999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('quality');
    }

    public function test_prepare_returns_a_download_token_without_calling_youtube(): void
    {
        $downloaded = $this->preparedFile();
        $token = null;

        $this->app->instance(YoutubeDownloaderService::class, new class($downloaded) extends YoutubeDownloaderService
        {
            public function __construct(private readonly array $downloaded) {}

            public function downloadVideo(string $url, int $quality): array
            {
                return $this->downloaded;
            }

            public function cleanupExpiredDownloads(): int
            {
                return 0;
            }
        });

        try {
            $response = $this->postJson('/download/prepare', [
                'url' => 'https://youtube.com/watch?v=abc123',
                'type' => 'video',
                'quality' => 1080,
            ])->assertOk()
                ->assertJsonStructure(['download_token', 'filename'])
                ->assertJson(['filename' => $downloaded['filename']]);

            $token = $response->json('download_token');

            $this->assertIsString($token);
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $token,
            );
            $this->assertTrue(Cache::has('download:'.$token));
        } finally {
            if (is_string($token)) {
                Cache::forget('download:'.$token);
            }

            File::deleteDirectory($downloaded['directory']);
        }
    }

    public function test_download_rejects_a_non_uuid_token(): void
    {
        $this->getJson('/download/not-a-token')->assertNotFound();
    }

    public function test_download_rejects_an_unknown_token(): void
    {
        $this->getJson('/download/'.Str::uuid())
            ->assertNotFound()
            ->assertJson(['message' => 'La descarga expiró o ya fue utilizada.']);
    }

    public function test_download_returns_an_attachment_and_consumes_its_token(): void
    {
        $downloaded = $this->preparedFile();
        $token = (string) Str::uuid();
        Cache::put('download:'.$token, $downloaded, now()->addMinutes(10));

        try {
            $this->get('/download/'.$token)
                ->assertOk()
                ->assertDownload($downloaded['filename']);

            $this->assertFalse(Cache::has('download:'.$token));

            $this->getJson('/download/'.$token)
                ->assertNotFound()
                ->assertJson(['message' => 'La descarga expiró o ya fue utilizada.']);
        } finally {
            Cache::forget('download:'.$token);
            File::deleteDirectory($downloaded['directory']);
        }
    }

    /** @return array{path: string, filename: string, mime_type: string, directory: string} */
    private function preparedFile(): array
    {
        $directory = storage_path('app/downloads/'.Str::uuid());
        $path = $directory.DIRECTORY_SEPARATOR.'fixture.mp4';

        File::makeDirectory($directory, 0755, true);
        File::put($path, 'prepared video');

        return [
            'path' => $path,
            'filename' => 'fixture.mp4',
            'mime_type' => 'video/mp4',
            'directory' => $directory,
        ];
    }
}
