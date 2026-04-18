<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Exception\ProtocolException;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 *
 * @template T
 * @template-implements SchemaGenerator<list<T>>
 */
final class ListGenerator implements SchemaGenerator
{
    /** @use GeneratorCombinatorsTrait<list<T>> */
    use GeneratorCombinatorsTrait;

    /**
     * @param Generator<T> $elements
     * @param int $minSize
     * @param int|null $maxSize
     */
    public function __construct(
        private Generator $elements,
        private int $minSize = 0,
        private null|int $maxSize = null,
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public function minSize(int $value): self
    {
        if ($this->maxSize !== null && $value > $this->maxSize) {
            throw new \InvalidArgumentException('minSize cannot be greater than maxSize');
        }
        $new = clone $this;
        $new->minSize = $value;
        return $new;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function maxSize(int $value): self
    {
        if ($value < $this->minSize) {
            throw new \InvalidArgumentException('maxSize cannot be less than minSize');
        }
        $new = clone $this;
        $new->maxSize = $value;
        return $new;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function schema(): array
    {
        assert($this->elements instanceof SchemaGenerator, 'Elements generator should be a SchemaGenerator');

        $schema = [
            'type' => 'list',
            'elements' => $this->elements->schema(),
            'min_size' => $this->minSize,
        ];

        if ($this->maxSize !== null) {
            $schema['max_size'] = $this->maxSize;
        }

        return $schema;
    }

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        if ($this->elements instanceof SchemaGenerator) {
            return $testCase->generateFromSchema($this->schema());
        }

        $testCase->startSpan(SpanLabel::List_);
        $result = [];
        $collection = $testCase->newCollection($this->minSize, $this->maxSize);

        while ($collection->more()) {
            $result[] = $this->elements->draw($testCase);
        }

        $testCase->stopSpan();

        return $result;
    }
}
