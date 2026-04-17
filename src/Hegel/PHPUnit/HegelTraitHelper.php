<?php

declare(strict_types=1);

namespace Hegel\PHPUnit;

use Hegel\Exception\FlakyTestException;
use Hegel\Protocol\Connection;
use Hegel\Runner;
use Hegel\RunResult;
use Hegel\Server\Session;
use Hegel\TestCase as TC;
use PHPUnit\Framework\TestCase;

/**
 * @internal Extracted from HegelTrait to give mago a concrete class context.
 */
final class HegelTraitHelper
{
    /**
     * @param array<mixed> $testArguments
     * @throws \LogicException
     * @throws FlakyTestException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Throwable
     */
    public static function runPropertyTest(
        object $caller,
        string $methodName,
        array $testArguments,
        Property $property,
        null|Connection $connection = null,
    ): void {
        if (!$caller instanceof TestCase) {
            throw new \LogicException('HegelTrait must be used in a class extending TestCase');
        }
        $testCase = $caller;
        $conn = $connection ?? Session::global()->connection();
        $runner = new Runner($conn);

        $healthChecks = array_values($property->suppressHealthChecks);

        $result = $runner->run(
            testFn: static function (TC $tc) use ($testCase, $methodName, $testArguments): void {
                (new \ReflectionMethod($testCase, $methodName))->invoke($testCase, $tc, ...$testArguments);
            },
            testCases: $property->testCases,
            seed: $property->seed,
            suppressHealthCheck: $healthChecks,
            noteFn: static function (string $msg): void {
                fwrite(STDERR, "[hegel] {$msg}\n");
            },
        );

        self::handleResult($testCase, $result);
    }

    /**
     * @throws FlakyTestException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Throwable
     */
    private static function handleResult(TestCase $testCase, RunResult $result): void
    {
        if ($result->healthCheckFailure !== null) {
            $testCase->fail("Health check failed: {$result->healthCheckFailure}");
        }

        if ($result->flaky !== null) {
            throw new FlakyTestException("Flaky test detected: {$result->flaky}");
        }

        if ($result->error !== null) {
            $testCase->fail("Hegel error: {$result->error}");
        }

        if (!$result->passed && $result->finalErrors !== []) {
            throw $result->finalErrors[0];
        }

        if (!$result->passed) {
            $testCase->fail('Property test failed (seed: ' . $result->seed . ')');
        }

        $testCase->addToAssertionCount(1);
    }
}
