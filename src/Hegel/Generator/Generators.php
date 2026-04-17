<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\SpanLabel;

final class Generators
{
    /**
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

    public static function booleans(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'boolean']);
    }

    public static function text(int $minSize = 0, null|int $maxSize = null): BasicGenerator
    {
        $schema = ['type' => 'string', 'min_size' => $minSize];
        if ($maxSize !== null) {
            $schema['max_size'] = $maxSize;
        }
        return new BasicGenerator($schema);
    }

    public static function binary(int $minSize = 0, null|int $maxSize = null): BasicGenerator
    {
        $schema = ['type' => 'binary', 'min_size' => $minSize];
        if ($maxSize !== null) {
            $schema['max_size'] = $maxSize;
        }
        return new BasicGenerator($schema);
    }

    public static function just(mixed $value): BasicGenerator
    {
        return new BasicGenerator(['type' => 'constant', 'value' => $value]);
    }

    /**
     * @param list<mixed> $values
     * @throws \InvalidArgumentException
     */
    public static function sampledFrom(array $values): BasicGenerator
    {
        if ($values === []) {
            throw new \InvalidArgumentException('sampledFrom requires at least one value');
        }
        $transform = self::makeSampledFromTransform(array_values($values));
        return new BasicGenerator(
            schema: ['type' => 'integer', 'min_value' => 0, 'max_value' => count($values) - 1],
            transform: $transform,
            spanLabel: SpanLabel::SampledFrom,
        );
    }

    /**
     * @param array<int, mixed> $indexed
     * @return \Closure(mixed): mixed
     */
    private static function makeSampledFromTransform(array $indexed): \Closure
    {
        return static function (mixed $index) use ($indexed): mixed {
            assert(is_int($index), 'sampledFrom index must be an integer');
            return $indexed[$index];
        };
    }

    public static function fromRegex(string $pattern): BasicGenerator
    {
        return new BasicGenerator(['type' => 'regex', 'pattern' => $pattern, 'fullmatch' => true]);
    }

    public static function fromPartialRegex(string $pattern): BasicGenerator
    {
        return new BasicGenerator(['type' => 'regex', 'pattern' => $pattern, 'fullmatch' => false]);
    }

    public static function lists(Generator $elements): ListGenerator
    {
        return new ListGenerator($elements);
    }

    public static function dicts(Generator $keys, Generator $values): DictGenerator
    {
        return new DictGenerator($keys, $values);
    }

    public static function tuples(Generator ...$elements): BasicGenerator
    {
        return new BasicGenerator([
            'type' => 'tuple',
            'elements' => array_map(static fn(Generator $g): array => $g->schema(), $elements),
        ]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function oneOf(Generator ...$generators): BasicGenerator
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

    public static function optional(Generator $element): BasicGenerator
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

    public static function emails(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'email']);
    }

    public static function urls(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'url']);
    }

    public static function domains(): DomainGenerator
    {
        return new DomainGenerator();
    }

    public static function ipv4(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'ipv4']);
    }

    public static function ipv6(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'ipv6']);
    }

    public static function dates(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'date']);
    }

    public static function datetimes(): BasicGenerator
    {
        return new BasicGenerator(['type' => 'datetime']);
    }
}
