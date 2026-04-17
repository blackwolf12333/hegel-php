<?php

declare(strict_types=1);

namespace Hegel\PHPUnit;

use PHPUnit\Framework\TestCase;

/**
 * Trait for PHPUnit test cases that use Hegel property-based testing.
 *
 * Test methods annotated with #[Property] receive a Hegel TestCase as their
 * first argument. DataProvider and #[TestWith] arguments follow after it.
 *
 * Must be used in a class extending PHPUnit\Framework\TestCase.
 *
 * Example:
 *   #[Test, Property(testCases: 500)]
 *   public function additionIsCommutative(TC $tc): void {
 *       $x = $tc->draw(gen::integers(-1000, 1000));
 *       $y = $tc->draw(gen::integers(-1000, 1000));
 *       $this->assertSame($x + $y, $y + $x);
 *   }
 *
 * @mixin TestCase
 */
trait HegelTrait
{
    /**
     * @param array<mixed> $testArguments
     * @throws \LogicException
     * @throws \Hegel\Exception\FlakyTestException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Throwable
     */
    // @mago-expect analysis:invalid-parent-type
    protected function invokeTestMethod(string $methodName, array $testArguments): mixed
    {
        $property = $this->resolvePropertyAttribute($methodName);

        if ($property === null) {
            return parent::invokeTestMethod($methodName, $testArguments);
        }

        HegelTraitHelper::runPropertyTest($this, $methodName, $testArguments, $property);

        return null;
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
}
