<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 */
final class BasicGenerator implements Generator
{
    use GeneratorCombinatorsTrait;

    /**
     * @param array<string, mixed> $schema
     * @param (\Closure(mixed): mixed)|null $transform
     */
    public function __construct(
        private readonly array $schema,
        private readonly null|\Closure $transform = null,
        private readonly null|SpanLabel $spanLabel = null,
    ) {}

    /** @return array<string, mixed> */
    public function schema(): array
    {
        return $this->schema;
    }

    public function draw(TestCase $testCase): mixed
    {
        if ($this->spanLabel !== null) {
            $testCase->startSpan($this->spanLabel->value);
        }

        $result = $testCase->generateFromSchema($this->schema);

        if ($this->transform !== null) {
            $result = ($this->transform)($result);
        }

        if ($this->spanLabel !== null) {
            $testCase->stopSpan();
        }

        return $result;
    }
}
