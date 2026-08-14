<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\YoutubeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class YoutubeUrlTest extends TestCase
{
    #[DataProvider('validUrls')]
    public function test_it_accepts_known_youtube_hosts(string $url): void
    {
        self::assertNull($this->validationMessage($url));
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_non_youtube_or_unsafe_urls(string $url): void
    {
        self::assertNotNull($this->validationMessage($url));
    }

    /** @return array<string, array{string}> */
    public static function validUrls(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=abc123'],
            'short' => ['https://youtu.be/abc123'],
            'mobile' => ['https://m.youtube.com/shorts/abc123'],
            'music' => ['https://music.youtube.com/watch?v=abc123'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidUrls(): array
    {
        return [
            'not a url' => ['not-a-url'],
            'wrong scheme' => ['file://youtube.com/video'],
            'lookalike host' => ['https://youtube.com.example.org/watch?v=abc123'],
            'youtube subdomain' => ['https://evil.youtube.com/watch?v=abc123'],
            'credentials' => ['https://user@youtube.com/watch?v=abc123'],
            'custom port' => ['https://youtube.com:8080/watch?v=abc123'],
        ];
    }

    private function validationMessage(string $url): ?string
    {
        $message = null;

        (new YoutubeUrl)->validate('url', $url, static function (string $failure) use (&$message): void {
            $message = $failure;
        });

        return $message;
    }
}
