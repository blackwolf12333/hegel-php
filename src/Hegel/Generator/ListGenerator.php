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
        private readonly BasicGenerator $elements,
        private readonly int $minSize = 0,
        private readonly null|int $maxSize = null,
    ) {}

    public function minSize(int $value): self
    {
        if ($this->maxSize !== null && $value > $this->maxSize) {
            throw new \InvalidArgumentException('minSize cannot be greater than maxSize');
        }
        return new self($this->elements, $value, $this->maxSize);
    }

    public function maxSize(int $value): self
    {
        if ($value < $this->minSize) {
            throw new \InvalidArgumentException('maxSize cannot be less than minSize');
        }
        return new self($this->elements, $this->minSize, $value);
    }

    /** @return array<string, mixed> */
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

    public function draw(TestCase $testCase): mixed
    {
        return $testCase->generateFromSchema($this->schema());
    }
}
