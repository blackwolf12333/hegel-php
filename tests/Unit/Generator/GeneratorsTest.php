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
    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function integers_schema(): void
    {
        $gen = gen::integers(0, 100);
        $this->assertInstanceOf(BasicGenerator::class, $gen);
        $this->assertSame(['type' => 'integer', 'min_value' => 0, 'max_value' => 100], $gen->schema());
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function integers_rejects_min_greater_than_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::integers(100, 0);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function floats_default_schema(): void
    {
        $gen = gen::floats();
        $this->assertInstanceOf(FloatGenerator::class, $gen);
        $this->assertSame(['type' => 'float'], $gen->schema());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function floats_bounded_schema(): void
    {
        $gen = gen::floats()->min(0.0)->max(1.0);
        /** @var array{type: string, min_value: float, max_value: float} $schema */
        $schema = $gen->schema();
        $this->assertSame('float', $schema['type']);
        $this->assertSame(0.0, $schema['min_value']);
        $this->assertSame(1.0, $schema['max_value']);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function floats_allow_nan(): void
    {
        $gen = gen::floats()->allowNaN();
        /** @var array{allow_nan: bool} $schema */
        $schema = $gen->schema();
        $this->assertTrue($schema['allow_nan']);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function floats_exclude_min(): void
    {
        $gen = gen::floats()->min(0.0)->excludeMin();
        /** @var array{exclude_min: bool} $schema */
        $schema = $gen->schema();
        $this->assertTrue($schema['exclude_min']);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function booleans_schema(): void
    {
        $gen = gen::booleans();
        $this->assertSame(['type' => 'boolean'], $gen->schema());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function text_schema(): void
    {
        $gen = gen::text(5, 20);
        /** @var array{type: string, min_size: int, max_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame('string', $schema['type']);
        $this->assertSame(5, $schema['min_size']);
        $this->assertSame(20, $schema['max_size']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function text_schema_without_max(): void
    {
        $gen = gen::text(0);
        /** @var array{type: string, min_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame('string', $schema['type']);
        $this->assertSame(0, $schema['min_size']);
        $this->assertArrayNotHasKey('max_size', $schema);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function binary_schema(): void
    {
        $gen = gen::binary(0, 256);
        /** @var array{type: string, min_size: int, max_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame('binary', $schema['type']);
        $this->assertSame(0, $schema['min_size']);
        $this->assertSame(256, $schema['max_size']);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function just_schema(): void
    {
        $gen = gen::just(null);
        $this->assertSame(['type' => 'constant', 'value' => null], $gen->schema());
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function sampled_from_schema(): void
    {
        $gen = gen::sampledFrom(['a', 'b', 'c']);
        $schema = $gen->schema();
        // SampledFrom is implemented as a transform with spans
        $this->assertNull( $schema);
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function sampled_from_empty_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::sampledFrom([]);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function from_regex_schema(): void
    {
        $gen = gen::fromRegex('[a-z]+');
        /** @var array{type: string, pattern: string, fullmatch: bool} $schema */
        $schema = $gen->schema();
        $this->assertSame('regex', $schema['type']);
        $this->assertSame('[a-z]+', $schema['pattern']);
        $this->assertTrue($schema['fullmatch']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function lists_schema(): void
    {
        $gen = gen::lists(gen::integers(0, 10));
        $this->assertInstanceOf(ListGenerator::class, $gen);
        /** @var array{type: string, elements: array<string, mixed>} $schema */
        $schema = $gen->schema();
        $this->assertSame('list', $schema['type']);
        $elements = $schema['elements'];
        $this->assertSame(['type' => 'integer', 'min_value' => 0, 'max_value' => 10], $elements);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function lists_with_bounds(): void
    {
        $gen = gen::lists(gen::integers(0, 10))->minSize(1)->maxSize(5);
        /** @var array{min_size: int, max_size: int} $schema */
        $schema = $gen->schema();
        $this->assertSame(1, $schema['min_size']);
        $this->assertSame(5, $schema['max_size']);
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function lists_rejects_min_gt_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::lists(gen::integers(0, 10))->minSize(10)->maxSize(5);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function dicts_schema(): void
    {
        $gen = gen::dicts(gen::text(), gen::integers(0, 100));
        $this->assertInstanceOf(DictGenerator::class, $gen);
        /** @var array{type: string, keys: array<string, mixed>, values: array<string, mixed>} $schema */
        $schema = $gen->schema();
        $this->assertSame('dict', $schema['type']);
        $this->assertSame(['type' => 'string', 'min_size' => 0], $schema['keys']);
        $this->assertSame(['type' => 'integer', 'min_value' => 0, 'max_value' => 100], $schema['values']);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     */
    #[Test]
    public function tuples_schema(): void
    {
        $gen = gen::tuples(gen::integers(0, 10), gen::booleans());
        /** @var array{type: string, elements: list<array<string, mixed>>} $schema */
        $schema = $gen->schema();
        $this->assertSame('tuple', $schema['type']);
        $elements = $schema['elements'];
        $this->assertCount(2, $elements);
        $this->assertSame(['type' => 'integer', 'min_value' => 0, 'max_value' => 10], $elements[0]);
        $this->assertSame(['type' => 'boolean'], $elements[1]);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     */
    #[Test]
    public function one_of_schema(): void
    {
        $gen = gen::oneOf(gen::integers(0, 10), gen::booleans());
        $schema = $gen->schema();
        // oneOf is implemented as a transform with spans
        $this->assertNull($schema);
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function one_of_requires_at_least_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        gen::oneOf();
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     */
    #[Test]
    public function optional_schema(): void
    {
        $gen = gen::optional(gen::integers(0, 100));
        /** @var array{type: string, generators: list<array<string, mixed>>} $schema */
        $schema = $gen->schema();
        $this->assertSame('one_of', $schema['type']);
        $generators = $schema['generators'];
        $this->assertCount(2, $generators);

        // First branch: null (tag 0)
        /** @var array{elements: list<array{type?: string, value?: int}>} $branch0 */
        $branch0 = $generators[0];
        $branch0Elements = $branch0['elements'];
        /** @var array{value: int} $el0 */
        $el0 = $branch0Elements[0];
        $this->assertSame(0, $el0['value']);
        /** @var array{type: string} $el1 */
        $el1 = $branch0Elements[1];
        $this->assertSame('null', $el1['type']);

        // Second branch: inner generator (tag 1)
        /** @var array{elements: list<array{type?: string, value?: int}>} $branch1 */
        $branch1 = $generators[1];
        $branch1Elements = $branch1['elements'];
        /** @var array{value: int} $el0b */
        $el0b = $branch1Elements[0];
        $this->assertSame(1, $el0b['value']);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function emails_schema(): void
    {
        $gen = gen::emails();
        $this->assertSame(['type' => 'email'], $gen->schema());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function urls_schema(): void
    {
        $gen = gen::urls();
        $this->assertSame(['type' => 'url'], $gen->schema());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function domains_schema(): void
    {
        $gen = gen::domains();
        /** @var array{type: string} $schema */
        $schema = $gen->schema();
        $this->assertSame('domain', $schema['type']);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function ipv4_schema(): void
    {
        $gen = gen::ipv4();
        $this->assertSame(['type' => 'ipv4'], $gen->schema());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function ipv6_schema(): void
    {
        $gen = gen::ipv6();
        $this->assertSame(['type' => 'ipv6'], $gen->schema());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function dates_schema(): void
    {
        $gen = gen::dates();
        $this->assertSame(['type' => 'date'], $gen->schema());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function datetimes_schema(): void
    {
        $gen = gen::datetimes();
        $this->assertSame(['type' => 'datetime'], $gen->schema());
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function map_creates_mapped_generator(): void
    {
        $gen = gen::integers(0, 10)->map(static fn(int $n): int => $n * 2);
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
