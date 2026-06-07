<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Generator;

use Hegel\Generator\BasicGenerator;
use Hegel\Generator\Combination\DictGenerator;
use Hegel\Generator\Combination\ListGenerator;
use Hegel\Generator\FilteredGenerator;
use Hegel\Generator\FlatMappedGenerator;
use Hegel\Generator\FloatGenerator;
use Hegel\Generator\Generators as gen;
use Hegel\Generator\MappedGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeneratorsTest extends TestCase
{
    #[Test]
    public function list_generator_max_size_does_not_affect_original(): void {
        $original = gen::lists(gen::integers(0,10));
        $maxSize = $original->maxSize(10);

        $this->assertSame(null, $original->maxSize);
        $this->assertSame(10, $maxSize->maxSize);
    }

    #[Test]
    public function list_generator_min_size_does_not_affect_original(): void {
        $original = gen::lists(gen::integers(0,10));
        $maxSize = $original->minSize(10);

        $this->assertSame(0, $original->minSize);
        $this->assertSame(10, $maxSize->minSize);
    }
    #[Test]
    public function dict_generator_max_size_does_not_affect_original(): void {
        $original = gen::dicts(gen::integers(0,10), gen::integers(0,10));
        $maxSize = $original->maxSize(10);

        $this->assertSame(null, $original->maxSize);
        $this->assertSame(10, $maxSize->maxSize);
    }

    #[Test]
    public function dict_generator_min_size_does_not_affect_original(): void {
        $original = gen::dicts(gen::integers(0,10), gen::integers(0,10));
        $maxSize = $original->minSize(10);

        $this->assertSame(0, $original->minSize);
        $this->assertSame(10, $maxSize->minSize);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function map_on_basic_generator_creates_basic_generator_with_transform(): void
    {
        $gen = gen::integers(0, 10)->map(static fn(int $n): int => $n * 2);
        $this->assertInstanceOf(MappedGenerator::class, $gen);
    }

    #[Test]
    public function map_on_complex_generator_creates_basic_generator_with_transform(): void
    {
        $gen = gen::integers(0, 10)->filter(static fn(int $n) => $n % 2 === 0)->map(static fn(int $n): int => $n * 2);
        $this->assertInstanceOf(MappedGenerator::class, $gen);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function filter_creates_filtered_generator(): void
    {
        $gen = gen::integers(0, 100)->filter(static fn(int $n): bool => $n > 50);
        $this->assertInstanceOf(FilteredGenerator::class, $gen);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function flat_map_creates_flat_mapped_generator(): void
    {
        $gen = gen::integers(1, 10)->flatMap(static fn(int $n): ListGenerator => gen::lists(gen::integers(0, $n)));
        $this->assertInstanceOf(FlatMappedGenerator::class, $gen);
    }
}
