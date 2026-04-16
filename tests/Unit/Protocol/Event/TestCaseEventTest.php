<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Protocol\Event;

use Hegel\Protocol\Event\TestCaseEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TestCaseEventTest extends TestCase
{
    #[Test]
    public function is_final_defaults_to_false_when_key_absent(): void
    {
        $event = TestCaseEvent::fromArray(['stream_id' => 3]);

        $this->assertFalse($event->isFinal);
    }

    #[Test]
    public function is_final_is_true_when_key_is_true(): void
    {
        $event = TestCaseEvent::fromArray(['stream_id' => 3, 'is_final' => true]);

        $this->assertTrue($event->isFinal);
    }

    #[Test]
    public function is_final_is_false_when_key_is_false(): void
    {
        $event = TestCaseEvent::fromArray(['stream_id' => 3, 'is_final' => false]);

        $this->assertFalse($event->isFinal);
    }

    #[Test]
    public function stream_id_is_parsed_correctly(): void
    {
        $event = TestCaseEvent::fromArray(['stream_id' => 7]);

        $this->assertSame(7, $event->streamId);
    }
}
