<?php

declare(strict_types=1);

namespace Hegel\PHPUnit;

use Hegel\Exception\FlakyTestException;
use Hegel\Protocol\Connection;
use Hegel\Runner;
use Hegel\RunResult;
use Hegel\Server\Session;
use Hegel\TestCase as HegelTestCase;

/**
 * Trait for PHPUnit test cases that use Hegel property-based testing.
 *
 * Provides the check() method to run property-based tests. Configuration
 * comes from #[Property] on the test method, or from check() parameters.
 *
 * Example:
 *   #[Test, Property(testCases: 500)]
 *   public function additionIsCommutative(): void {
 *       $this->check(function (HegelTestCase $tc) {
 *           $x = $tc->draw(gen::integers(-1000, 1000));
 *           $y = $tc->draw(gen::integers(-1000, 1000));
 *           $this->assertSame($x + $y, $y + $x);
 *       });
 *   }
 */
trait HegelTrait
{
    /**
     * Run a property-based test.
     *
     * @param \Closure(HegelTestCase): void $testFn The property test function
     * @param int|null $testCases Override number of test cases (default: from #[Property] or 100)
     * @param int|null $seed Override random seed
     * @param list<string>|null $suppressHealthChecks Override health check suppression
     */
    protected function check(
        \Closure $testFn,
        null|int $testCases = null,
        null|int $seed = null,
        null|array $suppressHealthChecks = null,
        null|Connection $connection = null,
    ): void {
        // Read #[Property] attribute from calling test method
        $property = $this->resolvePropertyAttribute();

        $testCases ??= $property->testCases ?? 100;
        $seed ??= $property?->seed;
        $suppressHealthChecks ??= $property->suppressHealthChecks ?? [];

        $conn = $connection ?? Session::global()->connection();
        $runner = new Runner($conn);

        $result = $runner->run(
            testFn: $testFn,
            testCases: $testCases,
            seed: $seed,
            suppressHealthCheck: $suppressHealthChecks,
            noteFn: function (string $msg): void {
                fwrite(STDERR, "[hegel] {$msg}\n");
            },
        );

        $this->handleResult($result);
    }

    private function resolvePropertyAttribute(): null|Property
    {
        try {
            $method = new \ReflectionMethod($this, $this->name());
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

        // Count as an assertion
        $this->addToAssertionCount(1);
    }
}
