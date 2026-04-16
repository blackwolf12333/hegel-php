<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Protocol\Event;

use Hegel\Protocol\Event\TestDoneEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TestDoneEventTest extends TestCase
{
    #[Test]
    public function interesting_test_cases_defaults_to_zero_when_key_absent(): void
    {
        $event = TestDoneEvent::fromArray(['results' => []]);

        $this->assertSame(0, $event->interestingTestCases);
    }

    #[Test]
    public function passed_defaults_to_true_when_key_absent(): void
    {
        $event = TestDoneEvent::fromArray(['results' => []]);

        $this->assertTrue($event->passed);
    }

    #[Test]
    public function test_cases_defaults_to_zero_when_key_absent(): void
    {
        $event = TestDoneEvent::fromArray(['results' => []]);

        static::assertSame(0, $event->testCases);
    }

    #[Test]
    public function seed_defaults_to_empty_string_when_key_absent(): void
    {
        $event = TestDoneEvent::fromArray(['results' => []]);

        $this->assertSame('', $event->seed);
    }

    #[Test]
    public function results_key_itself_defaults_to_empty_array_when_absent(): void
    {
        $event = TestDoneEvent::fromArray([]);

        $this->assertSame(0, $event->interestingTestCases);
        $this->assertTrue($event->passed);
        $this->assertSame(0, $event->testCases);
        $this->assertSame('', $event->seed);
        $this->assertNull($event->error);
        $this->assertNull($event->healthCheckFailure);
        $this->assertNull($event->flaky);
    }

    #[Test]
    public function interesting_test_cases_is_parsed_from_results(): void
    {
        $event = TestDoneEvent::fromArray(['results' => ['interesting_test_cases' => 3]]);

        $this->assertSame(3, $event->interestingTestCases);
    }

    #[Test]
    public function passed_is_false_when_explicitly_false(): void
    {
        $event = TestDoneEvent::fromArray(['results' => ['passed' => false]]);

        $this->assertFalse($event->passed);
    }

    #[Test]
    public function test_cases_is_parsed_from_results(): void
    {
        $event = TestDoneEvent::fromArray(['results' => ['test_cases' => 100]]);

        static::assertSame(100, $event->testCases);
    }

    #[Test]
    public function seed_is_parsed_from_results(): void
    {
        $event = TestDoneEvent::fromArray(['results' => ['seed' => '42']]);

        $this->assertSame('42', $event->seed);
    }

    #[Test]
    public function error_is_null_when_key_absent(): void
    {
        $event = TestDoneEvent::fromArray(['results' => []]);

        $this->assertNull($event->error);
    }

    #[Test]
    public function error_is_set_when_present(): void
    {
        $event = TestDoneEvent::fromArray(['results' => ['error' => 'something went wrong']]);

        $this->assertSame('something went wrong', $event->error);
    }
}
