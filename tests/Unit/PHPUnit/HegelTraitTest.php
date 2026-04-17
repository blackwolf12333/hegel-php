<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\PHPUnit;

use Hegel\PHPUnit\Property;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HegelTraitTest extends TestCase
{
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function attribute_has_correct_defaults(): void
    {
        $prop = new Property();
        $this->assertSame(100, $prop->testCases);
        $this->assertNull($prop->seed);
        $this->assertSame([], $prop->suppressHealthChecks);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function attribute_accepts_custom_values(): void
    {
        $prop = new Property(
            testCases: 500,
            seed: 42,
            suppressHealthChecks: ['filter_too_much'],
        );
        $this->assertSame(500, $prop->testCases);
        $this->assertSame(42, $prop->seed);
        $this->assertSame(['filter_too_much'], $prop->suppressHealthChecks);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \ReflectionException
     */
    #[Test]
    public function property_attribute_is_detectable_via_reflection(): void
    {
        $method = new \ReflectionMethod(PropertyAnnotatedStub::class, 'myTest');
        $attributes = $method->getAttributes(Property::class);
        $this->assertCount(1, $attributes);

        $prop = $attributes[0]->newInstance();
        $this->assertSame(200, $prop->testCases);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \ReflectionException
     */
    #[Test]
    public function non_property_method_has_no_attribute(): void
    {
        $method = new \ReflectionMethod(PropertyAnnotatedStub::class, 'normalTest');
        $attributes = $method->getAttributes(Property::class);
        $this->assertCount(0, $attributes);
    }
}
