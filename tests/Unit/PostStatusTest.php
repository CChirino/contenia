<?php

namespace Tests\Unit;

use App\Enums\PostStatus;
use PHPUnit\Framework\TestCase;

class PostStatusTest extends TestCase
{
    public function test_valid_transitions(): void
    {
        $this->assertTrue(PostStatus::Drafted->canTransitionTo(PostStatus::Approved));
        $this->assertTrue(PostStatus::Approved->canTransitionTo(PostStatus::Rendered));
        $this->assertTrue(PostStatus::Rendered->canTransitionTo(PostStatus::ClientApproved));
        $this->assertTrue(PostStatus::Rendered->canTransitionTo(PostStatus::Drafted));
        $this->assertTrue(PostStatus::Rendered->canTransitionTo(PostStatus::Approved));
        $this->assertTrue(PostStatus::ClientApproved->canTransitionTo(PostStatus::Scheduled));
        $this->assertTrue(PostStatus::ClientApproved->canTransitionTo(PostStatus::Approved));
        $this->assertTrue(PostStatus::Scheduled->canTransitionTo(PostStatus::Published));
        $this->assertTrue(PostStatus::Scheduled->canTransitionTo(PostStatus::Failed));
        $this->assertTrue(PostStatus::Failed->canTransitionTo(PostStatus::Scheduled));
    }

    public function test_invalid_transitions(): void
    {
        $invalid = [
            [PostStatus::Drafted, PostStatus::Rendered],
            [PostStatus::Drafted, PostStatus::ClientApproved],
            [PostStatus::Drafted, PostStatus::Scheduled],
            [PostStatus::Drafted, PostStatus::Published],
            [PostStatus::Approved, PostStatus::ClientApproved],
            [PostStatus::Approved, PostStatus::Scheduled],
            [PostStatus::Approved, PostStatus::Published],
            // Ya no se puede programar directo desde rendered: pasa por el
            // cliente (o se salta el paso a client_approved).
            [PostStatus::Rendered, PostStatus::Scheduled],
            [PostStatus::Rendered, PostStatus::Published],
            [PostStatus::ClientApproved, PostStatus::Drafted],
            [PostStatus::ClientApproved, PostStatus::Rendered],
            [PostStatus::ClientApproved, PostStatus::Published],
            [PostStatus::Scheduled, PostStatus::Approved],
            [PostStatus::Scheduled, PostStatus::Drafted],
            [PostStatus::Scheduled, PostStatus::ClientApproved],
            [PostStatus::Published, PostStatus::Drafted],
            [PostStatus::Published, PostStatus::ClientApproved],
            [PostStatus::Published, PostStatus::Scheduled],
            [PostStatus::Published, PostStatus::Failed],
            [PostStatus::Failed, PostStatus::Drafted],
            [PostStatus::Failed, PostStatus::Published],
        ];

        foreach ($invalid as [$from, $to]) {
            $this->assertFalse(
                $from->canTransitionTo($to),
                "{$from->value} no debería transicionar a {$to->value}"
            );
        }
    }

    public function test_no_status_transitions_to_itself(): void
    {
        foreach (PostStatus::cases() as $status) {
            $this->assertFalse($status->canTransitionTo($status));
        }
    }

    public function test_published_is_terminal(): void
    {
        foreach (PostStatus::cases() as $status) {
            $this->assertFalse(PostStatus::Published->canTransitionTo($status));
        }
    }
}
