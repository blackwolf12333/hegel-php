<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Protocol\Command;

use Hegel\Protocol\Command\RunTestCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RunTestCommandTest extends TestCase
{
    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function to_array_includes_seed_when_set(): void
    {
        $command = new RunTestCommand(testCases: 100, streamId: 3, seed: 12_345);

        /** @var array{command: string, test_cases: int, stream_id: int, seed: int} $result */
        $result = $command->toArray();

        $this->assertArrayHasKey('seed', $result);
        $this->assertSame(12_345, $result['seed']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function to_array_excludes_seed_when_null(): void
    {
        $command = new RunTestCommand(testCases: 100, streamId: 3, seed: null);

        /** @var array{command: string, test_cases: int, stream_id: int} $result */
        $result = $command->toArray();

        $this->assertArrayNotHasKey('seed', $result);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function to_array_includes_suppress_health_check_when_set(): void
    {
        $command = new RunTestCommand(
            testCases: 100,
            streamId: 3,
            suppressHealthCheck: ['too_slow', 'filter_too_much'],
        );

        /** @var array{command: string, test_cases: int, stream_id: int, suppress_health_check: list<string>} $result */
        $result = $command->toArray();

        $this->assertArrayHasKey('suppress_health_check', $result);
        $this->assertSame(['too_slow', 'filter_too_much'], $result['suppress_health_check']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function to_array_excludes_suppress_health_check_when_empty(): void
    {
        $command = new RunTestCommand(testCases: 100, streamId: 3, suppressHealthCheck: []);

        /** @var array{command: string, test_cases: int, stream_id: int} $result */
        $result = $command->toArray();

        $this->assertArrayNotHasKey('suppress_health_check', $result);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function to_array_always_contains_command_key(): void
    {
        $command = new RunTestCommand(testCases: 50, streamId: 5);

        /** @var array{command: string, test_cases: int, stream_id: int} $result */
        $result = $command->toArray();

        $this->assertSame('run_test', $result['command']);
        $this->assertSame(50, $result['test_cases']);
        $this->assertSame(5, $result['stream_id']);
    }
}
