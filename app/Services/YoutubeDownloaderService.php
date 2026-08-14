<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\YoutubeDownloadException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class YoutubeDownloaderService
{
    /** @var array<string, true> */
    private array $verifiedBinaries = [];

    /** @return array{id: string, title: string, channel: string|null, thumbnail: string|null, duration: int|null, qualities: list<int>} */
    public function getVideoInfo(string $url): array
    {
        $this->ensurePotProviderAvailable();
        $this->ensureBinary($this->ytDlpBinary(), '--version', 'yt-dlp');
        $this->ensureBinary($this->denoBinary(), '--version', 'Deno');

        $process = new Process(array_merge([
            $this->ytDlpBinary(),
            '--dump-single-json',
            '--skip-download',
            '--no-playlist',
            '--no-warnings',
        ], $this->denoRuntimeArguments(), $this->youtubeExtractorArguments(), [
            $url,
        ]));

        $this->run($process, $this->infoTimeout(), 'metadata');

        try {
            $metadata = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new YoutubeDownloadException(
                'YouTube devolvió una respuesta que no pudimos interpretar. Intentá nuevamente.',
                502,
                ['operation' => 'metadata', 'error' => $exception->getMessage()],
                $exception,
            );
        }

        if (! is_array($metadata)) {
            throw new YoutubeDownloadException('No pudimos obtener la información de ese video.');
        }

        return $this->simplifyMetadata($metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{id: string, title: string, channel: string|null, thumbnail: string|null, duration: int|null, qualities: list<int>}
     */
    public function simplifyMetadata(array $metadata): array
    {
        if (($metadata['is_live'] ?? false) === true || in_array($metadata['live_status'] ?? null, ['is_live', 'is_upcoming'], true)) {
            throw new YoutubeDownloadException('Los streams en vivo no están disponibles para descargar.');
        }

        $qualities = [];

        $formats = is_array($metadata['formats'] ?? null) ? $metadata['formats'] : [];

        foreach ($formats as $format) {
            if (! is_array($format) || ($format['vcodec'] ?? 'none') === 'none' || ! is_numeric($format['height'] ?? null)) {
                continue;
            }

            $height = (int) $format['height'];

            if ($height >= 144 && $height <= 4320) {
                $qualities[$height] = true;
            }
        }

        $qualities = array_keys($qualities);
        rsort($qualities, SORT_NUMERIC);

        if ($qualities === []) {
            throw new YoutubeDownloadException('No encontramos formatos de video disponibles para este enlace.');
        }

        $thumbnail = $this->safeRemoteUrl($metadata['thumbnail'] ?? null);
        $duration = is_numeric($metadata['duration'] ?? null) ? max(0, (int) $metadata['duration']) : null;
        $title = trim((string) ($metadata['title'] ?? ''));
        $channel = trim((string) ($metadata['channel'] ?? $metadata['uploader'] ?? ''));

        return [
            'id' => (string) ($metadata['id'] ?? ''),
            'title' => $title !== '' ? $title : 'Video de YouTube',
            'channel' => $channel !== '' ? $channel : null,
            'thumbnail' => $thumbnail,
            'duration' => $duration,
            'qualities' => $qualities,
        ];
    }

    /** @return array{path: string, filename: string, mime_type: string, directory: string} */
    public function downloadVideo(string $url, int $quality): array
    {
        $info = $this->getVideoInfo($url);
        $this->ensurePotProviderAvailable();

        if (! in_array($quality, $info['qualities'], true)) {
            throw new YoutubeDownloadException('Esa calidad ya no está disponible. Analizá el video nuevamente.');
        }

        $directory = $this->createDownloadDirectory();

        try {
            $this->ensureBinary($this->ffmpegBinary(), '-version', 'ffmpeg');

            $format = sprintf(
                'bestvideo[height=%1$d][protocol^=m3u8]+bestaudio[protocol^=m3u8]/bestvideo[height=%1$d][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height=%1$d]+bestaudio/best[height=%1$d][ext=mp4]/best[height=%1$d]',
                $quality,
            );
            $process = new Process(array_merge([
                $this->ytDlpBinary(),
                '--no-playlist',
                '--no-progress',
                '--format', $format,
                '--merge-output-format', 'mp4',
                '--remux-video', 'mp4',
            ], $this->denoRuntimeArguments(), $this->youtubeExtractorArguments(), $this->ffmpegLocationArguments(), [
                '--output', $directory.DIRECTORY_SEPARATOR.'media.%(ext)s',
                $url,
            ]));

            $this->run($process, $this->downloadTimeout(), 'video download');
            $path = $this->findOutputFile($directory, 'mp4');

            return [
                'path' => $path,
                'filename' => $this->safeFilename($info['title'], $info['id'], 'mp4'),
                'mime_type' => 'video/mp4',
                'directory' => $directory,
            ];
        } catch (YoutubeDownloadException $exception) {
            File::deleteDirectory($directory);

            throw $exception;
        } catch (Throwable $exception) {
            File::deleteDirectory($directory);

            throw new YoutubeDownloadException(
                'No pudimos preparar el MP4. Intentá nuevamente.',
                500,
                ['operation' => 'video download', 'error' => $exception->getMessage()],
                $exception,
            );
        }
    }

    /** @return array{path: string, filename: string, mime_type: string, directory: string} */
    public function downloadAudio(string $url): array
    {
        $info = $this->getVideoInfo($url);
        $this->ensurePotProviderAvailable();
        $directory = $this->createDownloadDirectory();

        try {
            $this->ensureBinary($this->ffmpegBinary(), '-version', 'ffmpeg');

            $process = new Process(array_merge([
                $this->ytDlpBinary(),
                '--no-playlist',
                '--no-progress',
                '--format', 'bestaudio[protocol^=m3u8]/bestaudio/best',
                '--extract-audio',
                '--audio-format', 'mp3',
                '--audio-quality', '0',
            ], $this->denoRuntimeArguments(), $this->youtubeExtractorArguments(), $this->ffmpegLocationArguments(), [
                '--output', $directory.DIRECTORY_SEPARATOR.'media.%(ext)s',
                $url,
            ]));

            $this->run($process, $this->downloadTimeout(), 'audio download');
            $path = $this->findOutputFile($directory, 'mp3');

            return [
                'path' => $path,
                'filename' => $this->safeFilename($info['title'], $info['id'], 'mp3'),
                'mime_type' => 'audio/mpeg',
                'directory' => $directory,
            ];
        } catch (YoutubeDownloadException $exception) {
            File::deleteDirectory($directory);

            throw $exception;
        } catch (Throwable $exception) {
            File::deleteDirectory($directory);

            throw new YoutubeDownloadException(
                'No pudimos preparar el MP3. Intentá nuevamente.',
                500,
                ['operation' => 'audio download', 'error' => $exception->getMessage()],
                $exception,
            );
        }
    }

    public function cleanupExpiredDownloads(): int
    {
        $baseDirectory = storage_path('app/downloads');

        if (! File::isDirectory($baseDirectory)) {
            return 0;
        }

        $configuredAge = max(10, (int) config('downloader.cleanup_after', 120)) * 60;
        $minimumSafeAge = $this->infoTimeout() + $this->downloadTimeout() + 1800;
        $cutoff = time() - max($configuredAge, $minimumSafeAge);
        $deleted = 0;

        // ponytail: O(n) is deliberate for one local disk; move this scan to a scheduled command if volume grows.
        foreach (File::directories($baseDirectory) as $directory) {
            if (File::lastModified($directory) <= $cutoff && File::deleteDirectory($directory)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function run(Process $process, int $timeout, string $operation): void
    {
        $process->setTimeout($timeout);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $exception) {
            throw new YoutubeDownloadException(
                'El proceso tardó demasiado. Probá con un video más corto o una calidad menor.',
                504,
                ['operation' => $operation, 'timeout' => $timeout],
                $exception,
            );
        } catch (Throwable $exception) {
            $output = trim($process->getErrorOutput()."\n".$process->getOutput());
            $context = [
                'operation' => $operation,
                'exit_code' => $process->getExitCode(),
                'process_error' => Str::limit($this->redactUrls($output), 1500),
            ];

            if ($this->isPotProviderUnavailable($output)) {
                $context['pot_provider_url'] = $this->potProviderUrl();
            }

            throw new YoutubeDownloadException(
                $this->friendlyProcessError($output, $operation),
                $this->processFailureStatus($output, $operation),
                $context,
                $exception,
            );
        }
    }

    private function ensureBinary(string $binary, string $versionArgument, string $label): void
    {
        if (isset($this->verifiedBinaries[$binary])) {
            return;
        }

        $process = new Process([$binary, $versionArgument]);
        $process->setTimeout(10);

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            throw new YoutubeDownloadException(
                'El servicio de descarga no está disponible en este momento.',
                503,
                ['operation' => 'dependency check', 'binary' => $label, 'error' => $exception->getMessage()],
                $exception,
            );
        }

        $this->verifiedBinaries[$binary] = true;
    }

    private function ensurePotProviderAvailable(): void
    {
        $providerUrl = $this->potProviderUrl();

        if ($providerUrl === '') {
            return;
        }

        $scheme = strtolower((string) parse_url($providerUrl, PHP_URL_SCHEME));
        $host = parse_url($providerUrl, PHP_URL_HOST);
        $configuredPort = parse_url($providerUrl, PHP_URL_PORT);
        $port = is_int($configuredPort) ? $configuredPort : ($scheme === 'https' ? 443 : 80);
        $errorCode = 0;
        $errorMessage = '';
        $connection = is_string($host) && $host !== '' && in_array($scheme, ['http', 'https'], true)
            ? @fsockopen($host, $port, $errorCode, $errorMessage, 2)
            : false;

        if ($connection === false) {
            throw new YoutubeDownloadException(
                'No pudimos preparar este video. Intentá nuevamente.',
                503,
                [
                    'operation' => 'pot provider check',
                    'pot_provider_url' => $providerUrl,
                    'error_code' => $errorCode,
                ],
            );
        }

        fclose($connection);
    }

    private function friendlyProcessError(string $output, string $operation): string
    {
        $error = strtolower($output);

        return match (true) {
            $this->isPotProviderUnavailable($error) => 'No pudimos preparar este video. Intentá nuevamente.',
            str_contains($error, 'private video') => 'Ese video es privado y no se puede descargar.',
            str_contains($error, 'requested format is not available') => 'La calidad seleccionada ya no está disponible.',
            str_contains($error, 'video unavailable'), str_contains($error, 'not available') => 'Ese contenido no está disponible.',
            str_contains($error, 'sign in'), str_contains($error, 'age-restricted') => 'Ese video tiene una restricción que impide descargarlo.',
            str_contains($error, 'live event'), str_contains($error, 'is live') => 'Los streams en vivo no están disponibles para descargar.',
            $operation === 'metadata' => 'No pudimos encontrar ese video. Revisá el enlace e intentá nuevamente.',
            $operation === 'audio download' => 'No pudimos preparar el MP3. Intentá nuevamente.',
            default => 'No pudimos preparar el MP4. Intentá nuevamente.',
        };
    }

    private function processFailureStatus(string $output, string $operation): int
    {
        if ($this->isPotProviderUnavailable($output)) {
            return 503;
        }

        return $operation === 'metadata' || str_contains(strtolower($output), 'requested format is not available')
            ? 422
            : 502;
    }

    private function isPotProviderUnavailable(string $output): bool
    {
        $error = strtolower($output);

        return str_contains($error, 'no http server available')
            || str_contains($error, '[pot:bgutil:http] error')
            || str_contains($error, '[pot:bgutil:http] failed')
            || (str_contains($error, '[pot:bgutil:http]') && str_contains($error, 'connection refused'));
    }

    private function createDownloadDirectory(): string
    {
        $directory = storage_path('app/downloads/'.Str::uuid());

        if (! File::makeDirectory($directory, 0755, true, true) && ! File::isDirectory($directory)) {
            throw new YoutubeDownloadException('No pudimos preparar el espacio temporal para la descarga.', 500);
        }

        return $directory;
    }

    private function findOutputFile(string $directory, string $extension): string
    {
        foreach (File::files($directory) as $file) {
            if (strtolower($file->getExtension()) === $extension) {
                return $file->getPathname();
            }
        }

        throw new YoutubeDownloadException('El archivo no pudo generarse correctamente.', 500);
    }

    private function safeFilename(string $title, string $id, string $extension): string
    {
        $name = Str::limit(Str::slug(Str::ascii($title)), 100, '');

        if ($name === '') {
            $name = 'youtube-'.($id !== '' ? Str::slug($id) : 'download');
        }

        return "{$name}.{$extension}";
    }

    private function safeRemoteUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }

    private function redactUrls(string $value): string
    {
        return preg_replace('~https?://\S+~i', '[url]', $value) ?? '[process output unavailable]';
    }

    /** @return list<string> */
    private function ffmpegLocationArguments(): array
    {
        $binary = $this->ffmpegBinary();

        return str_contains($binary, '/') || str_contains($binary, '\\')
            ? ['--ffmpeg-location', $binary]
            : [];
    }

    private function ytDlpBinary(): string
    {
        return (string) config('downloader.yt_dlp_binary', 'yt-dlp');
    }

    private function ffmpegBinary(): string
    {
        return (string) config('downloader.ffmpeg_binary', 'ffmpeg');
    }

    private function denoBinary(): string
    {
        return (string) config('downloader.deno_binary', 'deno');
    }

    /** @return list<string> */
    private function denoRuntimeArguments(): array
    {
        $binary = $this->denoBinary();

        return str_contains($binary, '/') || str_contains($binary, '\\')
            ? ['--js-runtimes', 'deno:'.$binary]
            : [];
    }

    /** @return list<string> */
    private function youtubeExtractorArguments(): array
    {
        $providerUrl = $this->potProviderUrl();

        if ($providerUrl === '') {
            return [];
        }

        return [
            '--extractor-args', 'youtube:player_client=visionos',
            '--extractor-args', 'youtubepot-bgutilhttp:base_url='.$providerUrl,
        ];
    }

    private function potProviderUrl(): string
    {
        return trim((string) config('downloader.pot_provider_url'));
    }

    private function infoTimeout(): int
    {
        return max(10, (int) config('downloader.info_timeout', 45));
    }

    private function downloadTimeout(): int
    {
        return max(60, (int) config('downloader.download_timeout', 1800));
    }
}
