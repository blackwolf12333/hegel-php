<?php

declare(strict_types=1);

namespace Hegel\PHPUnit;

use Hegel\Exception\FlakyTestException;
use Hegel\Protocol\Connection;
use Hegel\Runner;
use Hegel\RunResult;
use Hegel\Server\Session;
use Hegel\TestCase as TC;

/**
 * Trait for PHPUnit test cases that use Hegel property-based testing.
 *
 * Test methods annotated with #[Property] receive a Hegel TestCase as their
 * first argument. DataProvider and #[TestWith] arguments follow after it.
 *
 * Example:
 *   #[Test, Property(testCases: 500)]
 *   public function additionIsCommutative(TC $tc): void {
 *       $x = $tc->draw(gen::integers(-1000, 1000));
 *       $y = $tc->draw(gen::integers(-1000, 1000));
 *       $this->assertSame($x + $y, $y + $x);
 *   }
 */
trait HegelTrait
{
    /**
     * @param array<mixed> $testArguments
     */
    protected function invokeTestMethod(string $methodName, array $testArguments): mixed
    {
        $property = $this->resolvePropertyAttribute($methodName);

        if ($property === null) {
            return parent::invokeTestMethod($methodName, $testArguments);
        }

        $this->runPropertyTest($methodName, $testArguments, $property);

        return null;
    }

    private function runPropertyTest(
        string $methodName,
        array $testArguments,
        Property $property,
        null|Connection $connection = null,
    ): void {
        $conn = $connection ?? Session::global()->connection();
        $runner = new Runner($conn);

        $result = $runner->run(
            testFn: function (TC $tc) use ($methodName, $testArguments): void {
                $this->{$methodName}($tc, ...$testArguments);
            },
            testCases: $property->testCases,
            seed: $property->seed,
            suppressHealthCheck: $property->suppressHealthChecks,
            noteFn: function (string $msg): void {
                fwrite(STDERR, "[hegel] {$msg}\n");
            },
        );

        $this->handleResult($result);
    }

    private function resolvePropertyAttribute(string $methodName): null|Property
    {
        try {
            $method = new \ReflectionMethod($this, $methodName);
            $attributes = $method->getAttributes(Property::class);
            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        } catch (\ReflectionException) { // @mago-expect lint:no-empty-catch-clause
        }

        return null;
    }

    private function handleResult(RunResult $result): void
    {
        if ($result->healthCheckFailure !== null) {
            $this->fail("Health check failed: {$result->healthCheckFailure}");
        }

        if ($result->flaky !== null) {
            throw new FlakyTestException("Flaky test detected: {$result->flaky}");
        }

        if ($result->error !== null) {
            $this->fail("Hegel error: {$result->error}");
        }

        if (!$result->passed && $result->finalErrors !== []) {
            throw $result->finalErrors[0];
        }

        if (!$result->passed) {
            $this->fail('Property test failed (seed: ' . $result->seed . ')');
        }

        $this->addToAssertionCount(1);
    }
}
