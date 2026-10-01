<?php

namespace Tests\Unit;

use App\Enums\Network;
use App\Enums\PostFormat;
use PHPUnit\Framework\TestCase;

class PostFormatTest extends TestCase
{
    public function test_allowed_formats_per_network(): void
    {
        $this->assertEquals(
            [PostFormat::Image, PostFormat::Carousel, PostFormat::Reel],
            PostFormat::allowedFor(Network::Instagram)
        );

        $this->assertEquals(
            [PostFormat::Image],
            PostFormat::allowedFor(Network::LinkedIn)
        );

        $this->assertEquals(
            [PostFormat::Video],
            PostFormat::allowedFor(Network::TikTok)
        );
    }

    public function test_video_not_allowed_on_instagram_or_linkedin(): void
    {
        $this->assertNotContains(PostFormat::Video, PostFormat::allowedFor(Network::Instagram));
        $this->assertNotContains(PostFormat::Video, PostFormat::allowedFor(Network::LinkedIn));
        $this->assertNotContains(PostFormat::Reel, PostFormat::allowedFor(Network::LinkedIn));
        $this->assertNotContains(PostFormat::Reel, PostFormat::allowedFor(Network::TikTok));
    }
}
