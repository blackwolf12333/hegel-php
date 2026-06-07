<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Generator;

use Hegel\Generator\Generators as gen;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for generator default values, immutability, and boundary conditions.
 */
final class GeneratorDefaultsTest extends TestCase
{
    /**
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function lists_rejects_min_size_greater_than_max_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::lists(gen::integers(0, 10))->maxSize(5)->minSize(10);
    }

}
