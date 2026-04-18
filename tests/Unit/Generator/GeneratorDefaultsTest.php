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
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function dicts_default_min_size_is_zero(): void
    {
        $gen = gen::dicts(gen::text(), gen::integers(0, 100));
        /** @var array{min_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame(0, $schema['min_size']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function dicts_schema_excludes_max_size_when_not_set(): void
    {
        $gen = gen::dicts(gen::text(), gen::integers(0, 100));
        $this->assertArrayNotHasKey('max_size', $gen->schema());
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function dicts_schema_includes_max_size_when_set(): void
    {
        $gen = gen::dicts(gen::text(), gen::integers(0, 100))->maxSize(10);
        /** @var array{max_size: int} $schema */
        $schema = $gen->schema();
        $this->assertArrayHasKey('max_size', $schema);
        $this->assertSame(10, $schema['max_size']);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function domains_default_max_length_is_255(): void
    {
        $gen = gen::domains();
        /** @var array{max_length: int} $schema */
        $schema = $gen->schema();
        $this->assertSame(255, $schema['max_length']);
    }

    #[Test]
    public function domains_schema_includes_max_length_when_set(): void
    {
        $gen = gen::domains()->maxLength(12);
        $schema = $gen->schema();
        $this->assertSame(12, $schema['max_length']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function float_min_does_not_modify_original(): void
    {
        $original = gen::floats();
        $modified = $original->min(1.0);
        $this->assertArrayNotHasKey('min_value', $original->schema());
        /** @var array{min_value: float} $modifiedSchema */
        $modifiedSchema = $modified->schema();
        $this->assertSame(1.0, $modifiedSchema['min_value']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function float_max_does_not_modify_original(): void
    {
        $original = gen::floats();
        $modified = $original->max(1.0);
        $this->assertArrayNotHasKey('max_value', $original->schema());
        /** @var array{max_value: float} $modifiedSchema */
        $modifiedSchema = $modified->schema();
        $this->assertSame(1.0, $modifiedSchema['max_value']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function float_allow_nan_does_not_modify_original(): void
    {
        $original = gen::floats();
        $modified = $original->allowNaN();
        $this->assertArrayNotHasKey('allow_nan', $original->schema());
        /** @var array{allow_nan: bool} $modifiedSchema */
        $modifiedSchema = $modified->schema();
        $this->assertTrue($modifiedSchema['allow_nan']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function float_exclude_min_does_not_modify_original(): void
    {
        $original = gen::floats()->min(0.0);
        $modified = $original->excludeMin();
        $this->assertArrayNotHasKey('exclude_min', $original->schema());
        /** @var array{exclude_min: bool} $modifiedSchema */
        $modifiedSchema = $modified->schema();
        $this->assertTrue($modifiedSchema['exclude_min']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function integers_accepts_equal_min_and_max(): void
    {
        $gen = gen::integers(5, 5);
        $this->assertSame(['type' => 'integer', 'min_value' => 5, 'max_value' => 5], $gen->schema());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function binary_default_min_size_is_zero(): void
    {
        $gen = gen::binary();
        /** @var array{min_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame(0, $schema['min_size']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function optional_schema_branch_types(): void
    {
        $gen = gen::optional(gen::integers(0, 100));
        $schema = $gen->schema();
        /** @var array{generators: list<array<string, mixed>>} $schema */
        $generators = $schema['generators'];

        /** @var array{type: string, elements: list<array<string, mixed>>} $branch0 */
        $branch0 = $generators[0];
        $this->assertSame('tuple', $branch0['type']);

        /** @var list<array{type: string}> $branch0Elements */
        $branch0Elements = $branch0['elements'];
        $el0 = $branch0Elements[0];
        $this->assertSame('constant', $el0['type']);

        /** @var array{type: string, elements: list<array<string, mixed>>} $branch1 */
        $branch1 = $generators[1];
        $this->assertSame('tuple', $branch1['type']);

        /** @var list<array{type: string}> $branch1Elements */
        $branch1Elements = $branch1['elements'];
        $el0b = $branch1Elements[0];
        $this->assertSame('constant', $el0b['type']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function lists_default_min_size_is_zero(): void
    {
        $gen = gen::lists(gen::integers(0, 10));
        /** @var array{min_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame(0, $schema['min_size']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function lists_accepts_equal_min_and_max_size_via_max_first(): void
    {
        $gen = gen::lists(gen::integers(0, 10))->maxSize(5)->minSize(5);
        /** @var array{min_size: int, max_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame(5, $schema['min_size']);
        $this->assertSame(5, $schema['max_size']);
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function lists_rejects_min_size_greater_than_max_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::lists(gen::integers(0, 10))->maxSize(5)->minSize(10);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function lists_accepts_equal_min_and_max_size_via_min_first(): void
    {
        $gen = gen::lists(gen::integers(0, 10))->minSize(5)->maxSize(5);
        /** @var array{min_size: int, max_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame(5, $schema['min_size']);
        $this->assertSame(5, $schema['max_size']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function list_min_size_does_not_modify_original(): void
    {
        $original = gen::lists(gen::integers(0, 10));
        $modified = $original->minSize(3);
        /** @var array{min_size: int} $origSchema */
        $origSchema = $original->schema();
        /** @var array{min_size: int} $modSchema */
        $modSchema = $modified->schema();
        $this->assertSame(0, $origSchema['min_size']);
        $this->assertSame(3, $modSchema['min_size']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function list_max_size_does_not_modify_original(): void
    {
        $original = gen::lists(gen::integers(0, 10));
        $modified = $original->maxSize(7);
        $this->assertArrayNotHasKey('max_size', $original->schema());
        /** @var array{max_size: int} $modSchema */
        $modSchema = $modified->schema();
        $this->assertSame(7, $modSchema['max_size']);
    }

    // Mutant: clone removal in DictGenerator::maxSize() — original must not be modified
    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function dict_max_size_does_not_modify_original(): void
    {
        $original = gen::dicts(gen::text(), gen::integers(0, 100));
        $modified = $original->maxSize(5);
        $this->assertArrayNotHasKey('max_size', $original->schema());
        /** @var array{max_size: int} $modSchema */
        $modSchema = $modified->schema();
        $this->assertSame(5, $modSchema['max_size']);
    }

    // Complementary: dict minSize clone immutability
    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function dict_min_size_does_not_modify_original(): void
    {
        $original = gen::dicts(gen::text(), gen::integers(0, 100));
        $modified = $original->minSize(3);
        /** @var array{min_size: int} $origSchema */
        $origSchema = $original->schema();
        /** @var array{min_size: int} $modSchema */
        $modSchema = $modified->schema();
        $this->assertSame(0, $origSchema['min_size']);
        $this->assertSame(3, $modSchema['min_size']);
    }
}
