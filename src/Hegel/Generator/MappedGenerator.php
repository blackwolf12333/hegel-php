<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 */
final class MappedGenerator implements Generator
{
    use GeneratorCombinatorsTrait;

    public function __construct(
        private readonly Generator $inner,
        private readonly \Closure $fn,
    ) {}

    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        $testCase->startSpan(SpanLabel::Mapped->value);
        try {
            return ($this->fn)($this->inner->draw($testCase));
        } finally {
            $testCase->stopSpan();
        }
    }
}
