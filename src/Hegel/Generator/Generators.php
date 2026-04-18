<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\SpanLabel;

final class Generators
{
    /**
     * @return BasicGenerator<int>
     * @throws \InvalidArgumentException
     */
    public static function integers(int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): BasicGenerator
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
     * @return BasicGenerator<bool>
     */
    public static function booleans(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'boolean']);
    }

    /**
     * @param int $minSize
     * @param int|null $maxSize
     * @return BasicGenerator<string>
     */
    public static function text(int $minSize = 0, null|int $maxSize = null): BasicGenerator
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
     * @return BasicGenerator<string>
     */
    public static function binary(int $minSize = 0, null|int $maxSize = null): BasicGenerator
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
     * @return BasicGenerator<T>
     */
    public static function just(mixed $value): BasicGenerator
    {
        return new BasicGenerator(['type' => 'constant', 'value' => $value]);
    }

    /**
     * @template T
     * @param list<T> $values
     * @return BasicGenerator<T>
     * @throws \InvalidArgumentException
     */
    public static function sampledFrom(array $values): BasicGenerator
    {
        if ($values === []) {
            throw new \InvalidArgumentException('sampledFrom requires at least one value');
        }
        $transform = self::makeSampledFromTransform($values);
        return new BasicGenerator(
            schema: ['type' => 'integer', 'min_value' => 0, 'max_value' => count($values) - 1],
            transform: $transform,
            spanLabel: SpanLabel::SampledFrom,
        );
    }

    /**
     * @template T
     * @param array<int, T> $indexed
     * @return \Closure(int): T
     */
    private static function makeSampledFromTransform(array $indexed): \Closure
    {
        return static function (mixed $index) use ($indexed): mixed {
            assert(is_int($index), 'sampledFrom index must be an integer');
            return $indexed[$index];
        };
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

    /**
     * @template T
     * @param SchemaGenerator<T> ...$elements
     * @return TupleGenerator<T>
     */
    public static function tuples(SchemaGenerator ...$elements): TupleGenerator
    {
        return new TupleGenerator($elements);
    }

    /**
     * @template T
     * @param SchemaGenerator<T> ...$generators
     * @return BasicGenerator<T>
     * @throws \InvalidArgumentException
     */
    public static function oneOf(SchemaGenerator ...$generators): BasicGenerator
    {
        if ($generators === []) {
            throw new \InvalidArgumentException('oneOf requires at least one generator');
        }

        $branches = [];
        foreach ($generators as $gen) {
            $branches[] = $gen->schema();
        }

        return new BasicGenerator(
            schema: ['type' => 'one_of', 'generators' => $branches],
            transform: static function (mixed $result): mixed {
                assert(is_array($result) && array_key_exists(1, $result), 'oneOf result must be an array with index 1');
                return $result[1];
            },
            spanLabel: SpanLabel::OneOf,
        );
    }

    /**
     * @template T
     * @param SchemaGenerator<T> $element
     * @return BasicGenerator<T|null>
     */
    public static function optional(SchemaGenerator $element): BasicGenerator
    {
        return new BasicGenerator(
            schema: [
                'type' => 'one_of',
                'generators' => [
                    [
                        'type' => 'tuple',
                        'elements' => [
                            ['type' => 'constant', 'value' => 0],
                            ['type' => 'null'],
                        ],
                    ],
                    [
                        'type' => 'tuple',
                        'elements' => [
                            ['type' => 'constant', 'value' => 1],
                            $element->schema(),
                        ],
                    ],
                ],
            ],
            transform: static function (mixed $result): mixed {
                assert(is_array($result) && array_key_exists(0, $result) && array_key_exists(1, $result), 'optional result must be an array with indices 0 and 1');
                return $result[0] === 0 ? null : $result[1];
            },
            spanLabel: SpanLabel::Optional,
        );
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
