<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\YoutubeDownloadException;
use App\Http\Requests\DownloadRequest;
use App\Http\Requests\VideoInfoRequest;
use App\Services\YoutubeDownloaderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class DownloadController extends Controller
{
    private const DOWNLOAD_CACHE_PREFIX = 'download:';

    public function __construct(private readonly YoutubeDownloaderService $downloader) {}

    public function index(): View
    {
        return view('downloader');
    }

    public function info(VideoInfoRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'video' => $this->downloader->getVideoInfo($request->string('url')->toString()),
            ]);
        } catch (YoutubeDownloadException $exception) {
            $this->logFailure($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->httpStatus);
        }
    }

    public function prepare(DownloadRequest $request): JsonResponse
    {
        $downloaded = null;

        try {
            $this->downloader->cleanupExpiredDownloads();

            $url = $request->string('url')->toString();
            $downloaded = $request->string('type')->toString() === 'audio'
                ? $this->downloader->downloadAudio($url)
                : $this->downloader->downloadVideo($url, $request->integer('quality'));

            $token = (string) Str::uuid();
            $ttl = min(15, max(5, (int) config('downloader.prepared_ttl', 10)));
            $expiresAt = now()->addMinutes($ttl);

            if (Cache::put(self::DOWNLOAD_CACHE_PREFIX.$token, $downloaded, $expiresAt) === false) {
                throw new RuntimeException('Prepared download metadata could not be cached.');
            }

            return response()->json([
                'download_token' => $token,
                'filename' => $downloaded['filename'],
            ]);
        } catch (YoutubeDownloadException $exception) {
            $this->logFailure($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->httpStatus);
        } catch (Throwable $exception) {
            if (is_array($downloaded) && is_string($downloaded['directory'] ?? null)) {
                File::deleteDirectory($downloaded['directory']);
            }

            Log::error('Prepared download could not be stored.', [
                'exception_type' => get_class($exception),
            ]);

            return response()->json([
                'message' => 'No pudimos preparar la descarga. Intentá nuevamente.',
            ], 500);
        }
    }

    public function download(string $token): BinaryFileResponse|JsonResponse
    {
        $downloaded = Cache::pull(self::DOWNLOAD_CACHE_PREFIX.$token);

        if (! $this->isValidPreparedDownload($downloaded)) {
            return response()->json([
                'message' => 'La descarga expiró o ya fue utilizada.',
            ], 404);
        }

        app()->terminating(static function () use ($downloaded): void {
            File::deleteDirectory($downloaded['directory']);
        });

        return response()->download(
            $downloaded['path'],
            $downloaded['filename'],
            ['Content-Type' => $downloaded['mime_type']],
        )->deleteFileAfterSend(true);
    }

    private function isValidPreparedDownload(mixed $downloaded): bool
    {
        if (! is_array($downloaded)) {
            return false;
        }

        foreach (['path', 'filename', 'mime_type', 'directory'] as $key) {
            if (! is_string($downloaded[$key] ?? null) || $downloaded[$key] === '') {
                return false;
            }
        }

        $baseDirectory = realpath(storage_path('app/downloads'));
        $directory = realpath($downloaded['directory']);
        $path = realpath($downloaded['path']);

        return $baseDirectory !== false
            && $directory !== false
            && $path !== false
            && str_starts_with($directory, $baseDirectory.DIRECTORY_SEPARATOR)
            && str_starts_with($path, $directory.DIRECTORY_SEPARATOR)
            && File::isFile($path)
            && preg_match('/[\r\n\x{002F}\x{005C}]/u', $downloaded['filename']) !== 1;
    }

    private function logFailure(YoutubeDownloadException $exception): void
    {
        $message = isset($exception->context['pot_provider_url'])
            ? 'PO Token provider unavailable at '.$exception->context['pot_provider_url'].'.'
            : 'YouTube downloader operation failed.';

        Log::warning($message, $exception->context + [
            'exception_type' => get_class($exception->getPrevious() ?? $exception),
        ]);
    }
}
