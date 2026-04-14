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
    #[\Override]
    public function schema(): array
    {
        return $this->schema;
    }

    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        if ($this->spanLabel !== null) {
            $testCase->startSpan($this->spanLabel->value);
        }

        try {
            return $this->transform !== null
                ? ($this->transform)($testCase->generateFromSchema($this->schema))
                : $testCase->generateFromSchema($this->schema);
        } finally {
            if ($this->spanLabel !== null) {
                $testCase->stopSpan();
            }
        }
    }
}
