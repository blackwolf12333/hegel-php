<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\TestCase;

/**
 * @internal
 */
final class FloatGenerator implements Generator
{
    use GeneratorCombinatorsTrait;

    public function __construct(
        private null|float $min = null,
        private null|float $max = null,
        private null|bool $allowNaN = null,
        private null|bool $allowInfinity = null,
        private null|bool $excludeMin = null,
        private null|bool $excludeMax = null,
    ) {}

    public function min(float $value): self
    {
        $new = clone $this;
        $new->min = $value;
        return $new;
    }

    public function max(float $value): self
    {
        $new = clone $this;
        $new->max = $value;
        return $new;
    }

    public function allowNaN(): self
    {
        $new = clone $this;
        $new->allowNaN = true;
        return $new;
    }

    public function disallowNaN(): self
    {
        $new = clone $this;
        $new->allowNaN = false;
        return $new;
    }

    public function allowInfinity(): self
    {
        $new = clone $this;
        $new->allowInfinity = true;
        return $new;
    }

    public function disallowInfinity(): self
    {
        $new = clone $this;
        $new->allowInfinity = false;
        return $new;
    }

    public function excludeMin(): self
    {
        $new = clone $this;
        $new->excludeMin = true;
        return $new;
    }

    public function includeMin(): self
    {
        $new = clone $this;
        $new->excludeMin = false;
        return $new;
    }

    public function excludeMax(): self
    {
        $new = clone $this;
        $new->excludeMax = true;
        return $new;
    }

    public function includeMax(): self
    {
        $new = clone $this;
        $new->excludeMax = false;
        return $new;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function schema(): array
    {
        $schema = ['type' => 'float'];

        if ($this->min !== null) {
            $schema['min_value'] = $this->min;
        }
        if ($this->max !== null) {
            $schema['max_value'] = $this->max;
        }
        if ($this->allowNaN !== null) {
            $schema['allow_nan'] = $this->allowNaN;
        }
        if ($this->allowInfinity !== null) {
            $schema['allow_infinity'] = $this->allowInfinity;
        }
        if ($this->excludeMin !== null) {
            $schema['exclude_min'] = $this->excludeMin;
        }
        if ($this->excludeMax !== null) {
            $schema['exclude_max'] = $this->excludeMax;
        }

        return $schema;
    }

    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        return $testCase->generateFromSchema($this->schema());
    }
}
