<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Exception\ProtocolException;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 * @template K of array-key
 * @template V
 *
 * @template-implements SchemaGenerator<array<K, V>>
 */
final class DictGenerator implements SchemaGenerator
{
    /** @use \Hegel\Generator\GeneratorCombinatorsTrait<array<K, V>> */
    use GeneratorCombinatorsTrait;

    /**
     * @param Generator<K> $keys
     * @param Generator<V> $values
     * @param int $minSize
     * @param int|null $maxSize
     */
    public function __construct(
        private readonly Generator $keys,
        private readonly Generator $values,
        private int                $minSize = 0,
        private null|int           $maxSize = null,
    ) {}

    public function minSize(int $value): self
    {
        $new = clone $this;
        $new->minSize = $value;
        return $new;
    }

    public function maxSize(int $value): self
    {
        $new = clone $this;
        $new->maxSize = $value;
        return $new;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function schema(): array
    {
        assert($this->keys instanceof SchemaGenerator, 'Keys generator should be a SchemaGenerator');
        assert($this->values instanceof SchemaGenerator, 'Values generator should be a SchemaGenerator');
        $schema = [
            'type' => 'dict',
            'keys' => $this->keys->schema(),
            'values' => $this->values->schema(),
            'min_size' => $this->minSize,
        ];

        if ($this->maxSize !== null) {
            $schema['max_size'] = $this->maxSize;
        }

        return $schema;
    }

    /**
     * @return array<K, V>
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        if ($this->keys instanceof SchemaGenerator && $this->values instanceof SchemaGenerator) {
            /** @var array<K, V> */
            return $testCase->generateFromSchema($this->schema());
        }

        $testCase->startSpan(SpanLabel::Map);
        $collection = $testCase->newCollection($this->minSize, $this->maxSize);
        $map = [];

        while ($collection->more()) {
            $key = $this->keys->draw($testCase);

            if (array_key_exists($key, $map)) {
                $collection->reject();
                continue;
            }

            $map[$key] = $this->values->draw($testCase);
        }

        return $map;
    }
}
