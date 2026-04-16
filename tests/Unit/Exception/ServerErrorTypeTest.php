<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Exception;

use Hegel\Exception\ServerErrorType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerErrorTypeTest extends TestCase
{
    // Mutant: isExpectedTermination() returns false instead of true for StopTest
    #[Test]
    public function stop_test_is_expected_termination(): void
    {
        $this->assertTrue(ServerErrorType::StopTest->isExpectedTermination());
    }

    #[Test]
    public function overflow_is_expected_termination(): void
    {
        $this->assertTrue(ServerErrorType::Overflow->isExpectedTermination());
    }

    #[Test]
    public function flaky_strategy_definition_is_expected_termination(): void
    {
        $this->assertTrue(ServerErrorType::FlakyStrategyDefinition->isExpectedTermination());
    }

    #[Test]
    public function flaky_replay_is_expected_termination(): void
    {
        $this->assertTrue(ServerErrorType::FlakyReplay->isExpectedTermination());
    }

    #[Test]
    public function from_server_type_returns_correct_case(): void
    {
        $this->assertSame(ServerErrorType::StopTest, ServerErrorType::fromServerType('StopTest'));
        $this->assertSame(ServerErrorType::Overflow, ServerErrorType::fromServerType('Overflow'));
    }

    #[Test]
    public function from_server_type_throws_for_unknown_type(): void
    {
        $this->expectException(\ValueError::class);
        ServerErrorType::fromServerType('Unknown');
    }
}
