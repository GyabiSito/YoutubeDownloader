<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\YoutubeDownloadException;
use App\Services\YoutubeDownloaderService;
use PHPUnit\Framework\TestCase;

final class YoutubeDownloaderServiceTest extends TestCase
{
    public function test_it_exposes_only_safe_metadata_and_unique_descending_qualities(): void
    {
        $result = (new YoutubeDownloaderService)->simplifyMetadata([
            'id' => 'abc123',
            'title' => 'A useful video',
            'channel' => 'Example channel',
            'thumbnail' => 'https://i.ytimg.com/example.jpg',
            'duration' => 754.8,
            'ignored' => 'never exposed',
            'formats' => [
                ['height' => 720, 'vcodec' => 'avc1'],
                ['height' => 1080, 'vcodec' => 'av01'],
                ['height' => 720, 'vcodec' => 'vp9'],
                ['height' => null, 'vcodec' => 'none'],
                ['height' => 360, 'vcodec' => 'avc1'],
            ],
        ]);

        self::assertSame([
            'id' => 'abc123',
            'title' => 'A useful video',
            'channel' => 'Example channel',
            'thumbnail' => 'https://i.ytimg.com/example.jpg',
            'duration' => 754,
            'qualities' => [1080, 720, 360],
        ], $result);
    }

    public function test_it_rejects_live_streams(): void
    {
        $this->expectException(YoutubeDownloadException::class);
        $this->expectExceptionMessage('streams en vivo');

        (new YoutubeDownloaderService)->simplifyMetadata([
            'is_live' => true,
            'formats' => [['height' => 1080, 'vcodec' => 'avc1']],
        ]);
    }
}
