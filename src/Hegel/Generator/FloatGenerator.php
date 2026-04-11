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
        private readonly null|float $min = null,
        private readonly null|float $max = null,
        private readonly null|bool $allowNaN = null,
        private readonly null|bool $allowInfinity = null,
        private readonly null|bool $excludeMin = null,
        private readonly null|bool $excludeMax = null,
    ) {}

    public function min(float $value): self
    {
        return new self(
            $value,
            $this->max,
            $this->allowNaN,
            $this->allowInfinity,
            $this->excludeMin,
            $this->excludeMax,
        );
    }

    public function max(float $value): self
    {
        return new self(
            $this->min,
            $value,
            $this->allowNaN,
            $this->allowInfinity,
            $this->excludeMin,
            $this->excludeMax,
        );
    }

    public function allowNaN(): self
    {
        return new self($this->min, $this->max, true, $this->allowInfinity, $this->excludeMin, $this->excludeMax);
    }

    public function disallowNaN(): self
    {
        return new self($this->min, $this->max, false, $this->allowInfinity, $this->excludeMin, $this->excludeMax);
    }

    public function allowInfinity(): self
    {
        return new self($this->min, $this->max, $this->allowNaN, true, $this->excludeMin, $this->excludeMax);
    }

    public function disallowInfinity(): self
    {
        return new self($this->min, $this->max, $this->allowNaN, false, $this->excludeMin, $this->excludeMax);
    }

    public function excludeMin(): self
    {
        return new self($this->min, $this->max, $this->allowNaN, $this->allowInfinity, true, $this->excludeMax);
    }

    public function includeMin(): self
    {
        return new self($this->min, $this->max, $this->allowNaN, $this->allowInfinity, false, $this->excludeMax);
    }

    public function excludeMax(): self
    {
        return new self($this->min, $this->max, $this->allowNaN, $this->allowInfinity, $this->excludeMin, true);
    }

    public function includeMax(): self
    {
        return new self($this->min, $this->max, $this->allowNaN, $this->allowInfinity, $this->excludeMin, false);
    }

    /** @return array<string, mixed> */
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

    public function draw(TestCase $testCase): mixed
    {
        return $testCase->generateFromSchema($this->schema());
    }
}
