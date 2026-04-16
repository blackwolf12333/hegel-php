<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Protocol\Command;

use Hegel\Protocol\Command\RunTestCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RunTestCommandTest extends TestCase
{
    #[Test]
    public function to_array_includes_seed_when_set(): void
    {
        $command = new RunTestCommand(testCases: 100, streamId: 3, seed: 12_345);

        /** @var mixed $result */
        $result = $command->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('seed', $result);
        $this->assertSame(12_345, $result['seed']);
    }

    #[Test]
    public function to_array_excludes_seed_when_null(): void
    {
        $command = new RunTestCommand(testCases: 100, streamId: 3, seed: null);

        /** @var mixed $result */
        $result = $command->toArray();

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('seed', $result);
    }

    #[Test]
    public function to_array_includes_suppress_health_check_when_set(): void
    {
        $command = new RunTestCommand(
            testCases: 100,
            streamId: 3,
            suppressHealthCheck: ['too_slow', 'filter_too_much'],
        );

        /** @var mixed $result */
        $result = $command->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('suppress_health_check', $result);
        $this->assertSame(['too_slow', 'filter_too_much'], $result['suppress_health_check']);
    }

    #[Test]
    public function to_array_excludes_suppress_health_check_when_empty(): void
    {
        $command = new RunTestCommand(testCases: 100, streamId: 3, suppressHealthCheck: []);

        /** @var mixed $result */
        $result = $command->toArray();

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('suppress_health_check', $result);
    }

    #[Test]
    public function to_array_always_contains_command_key(): void
    {
        $command = new RunTestCommand(testCases: 50, streamId: 5);

        /** @var mixed $result */
        $result = $command->toArray();

        $this->assertIsArray($result);
        $this->assertSame('run_test', $result['command']);
        $this->assertSame(50, $result['test_cases']);
        $this->assertSame(5, $result['stream_id']);
    }
}
