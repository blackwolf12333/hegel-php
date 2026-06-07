<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Generator\Combination\DictGenerator;
use Hegel\Generator\Combination\ListGenerator;
use Hegel\Generator\Combination\SplObjectStorageGenerator;
use Hegel\Generator\Combination\TupleGenerator;
use Hegel\Generator\Composite\JustGenerator;
use Hegel\Generator\Composite\OneOfGenerator;
use Hegel\Generator\Composite\OptionalGenerator;
use Hegel\Generator\Strings\DomainGenerator;
use Hegel\SpanLabel;

final class Generators
{
    /**
     * @return Generator<int>
     * @throws \InvalidArgumentException
     */
    public static function integers(int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): Generator
    {
        if ($min > $max) {
            throw new \InvalidArgumentException("min ({$min}) cannot be greater than max ({$max})");
        }
        return new BasicGenerator(['type' => 'integer', 'min_value' => $min, 'max_value' => $max]);
    }

    public static function floats(): FloatGenerator
    {
        return new FloatGenerator();
    }

    /**
     * @return Generator<bool>
     */
    public static function booleans(): Generator
    {
        return new BasicGenerator(['type' => 'boolean']);
    }

    /**
     * @param int $minSize
     * @param int|null $maxSize
     * @return Generator<string>
     */
    public static function text(int $minSize = 0, null|int $maxSize = null): Generator
    {
        $schema = ['type' => 'string', 'min_size' => $minSize];
        if ($maxSize !== null) {
            $schema['max_size'] = $maxSize;
        }
        return new BasicGenerator($schema);
    }

    /**
     * @param int $minSize
     * @param int|null $maxSize
     * @return Generator<string>
     */
    public static function binary(int $minSize = 0, null|int $maxSize = null): Generator
    {
        $schema = ['type' => 'binary', 'min_size' => $minSize];
        if ($maxSize !== null) {
            $schema['max_size'] = $maxSize;
        }
        return new BasicGenerator($schema);
    }

    /**
     * @template T
     * @param T $value
     * @return Generator<T>
     */
    public static function just(mixed $value): Generator
    {
        return new JustGenerator($value);
    }

    /**
     * @template T
     * @param array<int, T> $values
     * @return Generator<T>
     * @throws \InvalidArgumentException
     */
    public static function sampledFrom(array $values): Generator
    {
        // This is implemented as an integer generator that generates an index which we map back
        // to a value in the input array because the values could be arbitrary PHP values that we can't
        // send to the server.

        if ($values === []) {
            throw new \InvalidArgumentException('sampledFrom requires at least one value');
        }
        return new BasicGenerator(
            schema: ['type' => 'integer', 'min_value' => 0, 'max_value' => count($values) - 1],
            transform: static fn(int $index) => $values[$index],
        );
    }

    /**
     * @param string $pattern
     * @return BasicGenerator<string>
     */
    public static function fromRegex(string $pattern): BasicGenerator
    {
        return new BasicGenerator(['type' => 'regex', 'pattern' => $pattern, 'fullmatch' => true]);
    }

    /**
     * @param string $pattern
     * @return BasicGenerator<string>
     */
    public static function fromPartialRegex(string $pattern): BasicGenerator
    {
        return new BasicGenerator(['type' => 'regex', 'pattern' => $pattern, 'fullmatch' => false]);
    }

    /**
     * @template T
     * @param Generator<T> $elements
     * @return ListGenerator<T>
     */
    public static function lists(Generator $elements): ListGenerator
    {
        return new ListGenerator($elements);
    }

    /**
     * @template T
     * @param Generator<T> $elements
     * @return ListGenerator<T>
     */
    public static function sets(Generator $elements): ListGenerator
    {
        return new ListGenerator($elements, unique: true);
    }

    /**
     * @template K of array-key
     * @template V
     * @param Generator<K> $keys
     * @param Generator<V> $values
     * @return DictGenerator<K, V>
     */
    public static function dicts(Generator $keys, Generator $values): DictGenerator
    {
        return new DictGenerator($keys, $values);
    }

    public static function splObjectStorage(Generator $keys, Generator $values): SplObjectStorageGenerator {
        return new SplObjectStorageGenerator(
            $keys,
            $values
        );
    }

    /**
     * @template T
     * @param Generator<T> ...$elements
     * @return Generator<T>
     */
    public static function tuples(Generator ...$elements): Generator
    {
        return new TupleGenerator($elements);
    }

    /**
     * @template T
     * @param Generator<T> ...$generators
     * @return Generator<T>
     * @throws \InvalidArgumentException
     */
    public static function oneOf(Generator ...$generators): Generator
    {
        return new OneOfGenerator(...$generators);
    }

    /**
     * @template T
     * @param Generator<T> $element
     * @return Generator<T|null>
     */
    public static function optional(Generator $element): Generator
    {
        return new OptionalGenerator($element);
    }

    /**
     * @return BasicGenerator<string>
     */
    public static function emails(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'email']);
    }

    /**
     * @return BasicGenerator<string>
     */
    public static function urls(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'url']);
    }

    public static function domains(): DomainGenerator
    {
        return new DomainGenerator();
    }

    /**
     * @return BasicGenerator<string>
     */
    public static function ipv4(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'ipv4']);
    }

    /**
     * @return BasicGenerator<string>
     */
    public static function ipv6(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'ipv6']);
    }

    /**
     * @return BasicGenerator<string>
     */
    public static function dates(): BasicGenerator
    {
        // TODO(@blackwolf12333): transform to DateTime
        return new BasicGenerator(
            ['type' => 'date'],
        );
    }

    /**
     * @return BasicGenerator<string>
     */
    public static function datetimes(): BasicGenerator
    {
        // TODO(@blackwolf12333): transform to DateTime
        return new BasicGenerator(['type' => 'datetime']);
    }
}
