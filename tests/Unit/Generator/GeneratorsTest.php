<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Generator;

use Hegel\Generator\BasicGenerator;
use Hegel\Generator\DictGenerator;
use Hegel\Generator\FilteredGenerator;
use Hegel\Generator\FlatMappedGenerator;
use Hegel\Generator\FloatGenerator;
use Hegel\Generator\Generators as gen;
use Hegel\Generator\ListGenerator;
use Hegel\Generator\MappedGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeneratorsTest extends TestCase
{
    #[Test]
    public function integers_schema(): void
    {
        $gen = gen::integers(0, 100);
        $this->assertInstanceOf(BasicGenerator::class, $gen);
        $this->assertSame(['type' => 'integer', 'min_value' => 0, 'max_value' => 100], $gen->schema());
    }

    #[Test]
    public function integers_rejects_min_greater_than_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::integers(100, 0);
    }

    #[Test]
    public function floats_default_schema(): void
    {
        $gen = gen::floats();
        $this->assertInstanceOf(FloatGenerator::class, $gen);
        $this->assertSame(['type' => 'float'], $gen->schema());
    }

    #[Test]
    public function floats_bounded_schema(): void
    {
        $gen = gen::floats()->min(0.0)->max(1.0);
        $schema = $gen->schema();
        $this->assertSame('float', $schema['type']);
        $this->assertSame(0.0, $schema['min_value']);
        $this->assertSame(1.0, $schema['max_value']);
    }

    #[Test]
    public function floats_allow_nan(): void
    {
        $gen = gen::floats()->allowNaN();
        $this->assertTrue($gen->schema()['allow_nan']);
    }

    #[Test]
    public function floats_exclude_min(): void
    {
        $gen = gen::floats()->min(0.0)->excludeMin();
        $this->assertTrue($gen->schema()['exclude_min']);
    }

    #[Test]
    public function booleans_schema(): void
    {
        $gen = gen::booleans();
        $this->assertSame(['type' => 'boolean'], $gen->schema());
    }

    #[Test]
    public function text_schema(): void
    {
        $gen = gen::text(5, 20);
        $schema = $gen->schema();
        $this->assertSame('string', $schema['type']);
        $this->assertSame(5, $schema['min_size']);
        $this->assertSame(20, $schema['max_size']);
    }

    #[Test]
    public function text_schema_without_max(): void
    {
        $gen = gen::text(0);
        $schema = $gen->schema();
        $this->assertSame('string', $schema['type']);
        $this->assertSame(0, $schema['min_size']);
        $this->assertArrayNotHasKey('max_size', $schema);
    }

    #[Test]
    public function binary_schema(): void
    {
        $gen = gen::binary(0, 256);
        $schema = $gen->schema();
        $this->assertSame('binary', $schema['type']);
        $this->assertSame(0, $schema['min_size']);
        $this->assertSame(256, $schema['max_size']);
    }

    #[Test]
    public function just_schema(): void
    {
        $gen = gen::just(null);
        $this->assertSame(['type' => 'constant', 'value' => null], $gen->schema());
    }

    #[Test]
    public function sampled_from_schema(): void
    {
        $gen = gen::sampledFrom(['a', 'b', 'c']);
        $schema = $gen->schema();
        // SampledFrom is implemented as integer gen [0, len-1] with client-side indexing
        $this->assertSame('integer', $schema['type']);
        $this->assertSame(0, $schema['min_value']);
        $this->assertSame(2, $schema['max_value']);
    }

    #[Test]
    public function sampled_from_empty_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::sampledFrom([]);
    }

    #[Test]
    public function from_regex_schema(): void
    {
        $gen = gen::fromRegex('[a-z]+');
        $schema = $gen->schema();
        $this->assertSame('regex', $schema['type']);
        $this->assertSame('[a-z]+', $schema['pattern']);
        $this->assertTrue($schema['fullmatch']);
    }

    #[Test]
    public function lists_schema(): void
    {
        $gen = gen::lists(gen::integers(0, 10));
        $this->assertInstanceOf(ListGenerator::class, $gen);
        $schema = $gen->schema();
        $this->assertSame('list', $schema['type']);
        /** @var mixed $elements */
        $elements = $schema['elements'];
        assert(is_array($elements), 'List elements schema must be an array');
        $this->assertSame(['type' => 'integer', 'min_value' => 0, 'max_value' => 10], $elements);
    }

    #[Test]
    public function lists_with_bounds(): void
    {
        $gen = gen::lists(gen::integers(0, 10))->minSize(1)->maxSize(5);
        $schema = $gen->schema();
        $this->assertSame(1, $schema['min_size']);
        $this->assertSame(5, $schema['max_size']);
    }

    #[Test]
    public function lists_rejects_min_gt_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::lists(gen::integers(0, 10))->minSize(10)->maxSize(5);
    }

    #[Test]
    public function dicts_schema(): void
    {
        $gen = gen::dicts(gen::text(), gen::integers(0, 100));
        $this->assertInstanceOf(DictGenerator::class, $gen);
        $schema = $gen->schema();
        $this->assertSame('dict', $schema['type']);
        $this->assertSame(['type' => 'string', 'min_size' => 0], $schema['keys']);
        $this->assertSame(['type' => 'integer', 'min_value' => 0, 'max_value' => 100], $schema['values']);
    }

    #[Test]
    public function tuples_schema(): void
    {
        $gen = gen::tuples(gen::integers(0, 10), gen::booleans());
        $schema = $gen->schema();
        $this->assertSame('tuple', $schema['type']);
        /** @var mixed $elements */
        $elements = $schema['elements'];
        assert(is_array($elements), 'Tuple elements schema must be an array');
        $this->assertCount(2, $elements);
        $this->assertSame(['type' => 'integer', 'min_value' => 0, 'max_value' => 10], $elements[0]);
        $this->assertSame(['type' => 'boolean'], $elements[1]);
    }

    #[Test]
    public function one_of_schema(): void
    {
        $gen = gen::oneOf(gen::integers(0, 10), gen::booleans());
        $schema = $gen->schema();
        $this->assertSame('one_of', $schema['type']);
        /** @var mixed $generators */
        $generators = $schema['generators'];
        assert(is_array($generators), 'oneOf generators must be an array');
        $this->assertCount(2, $generators);
    }

    #[Test]
    public function one_of_requires_at_least_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::oneOf();
    }

    #[Test]
    public function optional_schema(): void
    {
        $gen = gen::optional(gen::integers(0, 100));
        $schema = $gen->schema();
        $this->assertSame('one_of', $schema['type']);
        /** @var mixed $generators */
        $generators = $schema['generators'];
        assert(is_array($generators), 'Optional generators must be an array');
        $this->assertCount(2, $generators);

        // First branch: null (tag 0)
        /** @var mixed $branch0 */
        $branch0 = $generators[0];
        assert(is_array($branch0), 'Branch 0 must be an array');
        /** @var mixed $branch0Elements */
        $branch0Elements = $branch0['elements'];
        assert(is_array($branch0Elements), 'Branch 0 elements must be an array');
        /** @var mixed $el0 */
        $el0 = $branch0Elements[0];
        assert(is_array($el0), 'Branch 0 element 0 must be an array');
        $this->assertSame(0, $el0['value']);
        /** @var mixed $el1 */
        $el1 = $branch0Elements[1];
        assert(is_array($el1), 'Branch 0 element 1 must be an array');
        $this->assertSame('null', $el1['type']);

        // Second branch: inner generator (tag 1)
        /** @var mixed $branch1 */
        $branch1 = $generators[1];
        assert(is_array($branch1), 'Branch 1 must be an array');
        /** @var mixed $branch1Elements */
        $branch1Elements = $branch1['elements'];
        assert(is_array($branch1Elements), 'Branch 1 elements must be an array');
        /** @var mixed $el0b */
        $el0b = $branch1Elements[0];
        assert(is_array($el0b), 'Branch 1 element 0 must be an array');
        $this->assertSame(1, $el0b['value']);
    }

    #[Test]
    public function emails_schema(): void
    {
        $gen = gen::emails();
        $this->assertSame(['type' => 'email'], $gen->schema());
    }

    #[Test]
    public function urls_schema(): void
    {
        $gen = gen::urls();
        $this->assertSame(['type' => 'url'], $gen->schema());
    }

    #[Test]
    public function domains_schema(): void
    {
        $gen = gen::domains();
        $schema = $gen->schema();
        $this->assertSame('domain', $schema['type']);
    }

    #[Test]
    public function ipv4_schema(): void
    {
        $gen = gen::ipv4();
        $this->assertSame(['type' => 'ipv4'], $gen->schema());
    }

    #[Test]
    public function ipv6_schema(): void
    {
        $gen = gen::ipv6();
        $this->assertSame(['type' => 'ipv6'], $gen->schema());
    }

    #[Test]
    public function dates_schema(): void
    {
        $gen = gen::dates();
        $this->assertSame(['type' => 'date'], $gen->schema());
    }

    #[Test]
    public function datetimes_schema(): void
    {
        $gen = gen::datetimes();
        $this->assertSame(['type' => 'datetime'], $gen->schema());
    }

    #[Test]
    public function map_creates_mapped_generator(): void
    {
        $gen = gen::integers(0, 10)->map(static fn(int $n): int => $n * 2);
        $this->assertInstanceOf(MappedGenerator::class, $gen);
    }

    #[Test]
    public function filter_creates_filtered_generator(): void
    {
        $gen = gen::integers(0, 100)->filter(static fn(int $n): bool => $n > 50);
        $this->assertInstanceOf(FilteredGenerator::class, $gen);
    }

    #[Test]
    public function flat_map_creates_flat_mapped_generator(): void
    {
        $gen = gen::integers(1, 10)->flatMap(static fn(int $n): ListGenerator => gen::lists(gen::integers(0, $n)));
        $this->assertInstanceOf(FlatMappedGenerator::class, $gen);
    }
}
