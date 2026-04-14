<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\TestCase;

/**
 * @internal
 */
final class ListGenerator implements Generator
{
    use GeneratorCombinatorsTrait;

    public function __construct(
        private Generator $elements,
        private int $minSize = 0,
        private null|int $maxSize = null,
    ) {}

    public function minSize(int $value): self
    {
        if ($this->maxSize !== null && $value > $this->maxSize) {
            throw new \InvalidArgumentException('minSize cannot be greater than maxSize');
        }
        $new = clone $this;
        $new->minSize = $value;
        return $new;
    }

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

    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        return $testCase->generateFromSchema($this->schema());
    }
}
