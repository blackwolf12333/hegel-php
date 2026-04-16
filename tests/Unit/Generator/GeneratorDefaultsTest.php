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
    #[Test]
    public function dicts_default_min_size_is_zero(): void
    {
        $gen = gen::dicts(gen::text(), gen::integers(0, 100));
        $schema = $gen->schema();
        $this->assertSame(0, $schema['min_size']);
    }

    #[Test]
    public function dicts_schema_excludes_max_size_when_not_set(): void
    {
        $gen = gen::dicts(gen::text(), gen::integers(0, 100));
        $this->assertArrayNotHasKey('max_size', $gen->schema());
    }

    #[Test]
    public function dicts_schema_includes_max_size_when_set(): void
    {
        $gen = gen::dicts(gen::text(), gen::integers(0, 100))->maxSize(10);
        $schema = $gen->schema();
        $this->assertArrayHasKey('max_size', $schema);
        $this->assertSame(10, $schema['max_size']);
    }

    #[Test]
    public function domains_default_max_length_is_255(): void
    {
        $gen = gen::domains();
        $schema = $gen->schema();
        $this->assertSame(255, $schema['max_length']);
    }

    #[Test]
    public function float_min_does_not_modify_original(): void
    {
        $original = gen::floats();
        $modified = $original->min(1.0);
        $this->assertArrayNotHasKey('min_value', $original->schema());
        $this->assertSame(1.0, $modified->schema()['min_value']);
    }

    #[Test]
    public function float_max_does_not_modify_original(): void
    {
        $original = gen::floats();
        $modified = $original->max(1.0);
        $this->assertArrayNotHasKey('max_value', $original->schema());
        $this->assertSame(1.0, $modified->schema()['max_value']);
    }

    #[Test]
    public function float_allow_nan_does_not_modify_original(): void
    {
        $original = gen::floats();
        $modified = $original->allowNaN();
        $this->assertArrayNotHasKey('allow_nan', $original->schema());
        $this->assertTrue($modified->schema()['allow_nan']);
    }

    #[Test]
    public function float_exclude_min_does_not_modify_original(): void
    {
        $original = gen::floats()->min(0.0);
        $modified = $original->excludeMin();
        $this->assertArrayNotHasKey('exclude_min', $original->schema());
        $this->assertTrue($modified->schema()['exclude_min']);
    }

    #[Test]
    public function integers_accepts_equal_min_and_max(): void
    {
        $gen = gen::integers(5, 5);
        $this->assertSame(['type' => 'integer', 'min_value' => 5, 'max_value' => 5], $gen->schema());
    }

    #[Test]
    public function binary_default_min_size_is_zero(): void
    {
        $gen = gen::binary();
        $schema = $gen->schema();
        $this->assertSame(0, $schema['min_size']);
    }

    #[Test]
    public function optional_schema_branch_types(): void
    {
        $gen = gen::optional(gen::integers(0, 100));
        $schema = $gen->schema();
        /** @var mixed $generators */
        $generators = $schema['generators'];
        assert(is_array($generators), 'Optional generators must be an array');

        /** @var mixed $branch0 */
        $branch0 = $generators[0];
        assert(is_array($branch0), 'Branch 0 must be an array');
        $this->assertSame('tuple', $branch0['type']);

        /** @var mixed $branch0Elements */
        $branch0Elements = $branch0['elements'];
        assert(is_array($branch0Elements), 'Branch 0 elements must be an array');
        /** @var mixed $el0 */
        $el0 = $branch0Elements[0];
        assert(is_array($el0), 'Branch 0 element 0 must be an array');
        $this->assertSame('constant', $el0['type']);

        /** @var mixed $branch1 */
        $branch1 = $generators[1];
        assert(is_array($branch1), 'Branch 1 must be an array');
        $this->assertSame('tuple', $branch1['type']);

        /** @var mixed $branch1Elements */
        $branch1Elements = $branch1['elements'];
        assert(is_array($branch1Elements), 'Branch 1 elements must be an array');
        /** @var mixed $el0b */
        $el0b = $branch1Elements[0];
        assert(is_array($el0b), 'Branch 1 element 0 must be an array');
        $this->assertSame('constant', $el0b['type']);
    }

    #[Test]
    public function lists_default_min_size_is_zero(): void
    {
        $gen = gen::lists(gen::integers(0, 10));
        $schema = $gen->schema();
        $this->assertSame(0, $schema['min_size']);
    }

    #[Test]
    public function lists_accepts_equal_min_and_max_size_via_max_first(): void
    {
        $gen = gen::lists(gen::integers(0, 10))->maxSize(5)->minSize(5);
        $schema = $gen->schema();
        $this->assertSame(5, $schema['min_size']);
        $this->assertSame(5, $schema['max_size']);
    }

    #[Test]
    public function lists_rejects_min_size_greater_than_max_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::lists(gen::integers(0, 10))->maxSize(5)->minSize(10);
    }

    #[Test]
    public function lists_accepts_equal_min_and_max_size_via_min_first(): void
    {
        $gen = gen::lists(gen::integers(0, 10))->minSize(5)->maxSize(5);
        $schema = $gen->schema();
        $this->assertSame(5, $schema['min_size']);
        $this->assertSame(5, $schema['max_size']);
    }

    #[Test]
    public function list_min_size_does_not_modify_original(): void
    {
        $original = gen::lists(gen::integers(0, 10));
        $modified = $original->minSize(3);
        $this->assertSame(0, $original->schema()['min_size']);
        $this->assertSame(3, $modified->schema()['min_size']);
    }

    #[Test]
    public function list_max_size_does_not_modify_original(): void
    {
        $original = gen::lists(gen::integers(0, 10));
        $modified = $original->maxSize(7);
        $this->assertArrayNotHasKey('max_size', $original->schema());
        $this->assertSame(7, $modified->schema()['max_size']);
    }

    // Mutant: clone removal in DictGenerator::maxSize() — original must not be modified
    #[Test]
    public function dict_max_size_does_not_modify_original(): void
    {
        $original = gen::dicts(gen::text(), gen::integers(0, 100));
        $modified = $original->maxSize(5);
        $this->assertArrayNotHasKey('max_size', $original->schema());
        $this->assertSame(5, $modified->schema()['max_size']);
    }

    // Complementary: dict minSize clone immutability
    #[Test]
    public function dict_min_size_does_not_modify_original(): void
    {
        $original = gen::dicts(gen::text(), gen::integers(0, 100));
        $modified = $original->minSize(3);
        $this->assertSame(0, $original->schema()['min_size']);
        $this->assertSame(3, $modified->schema()['min_size']);
    }
}
