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

    public function draw(TestCase $testCase): mixed
    {
        $testCase->startSpan(SpanLabel::Mapped->value);
        $value = $this->inner->draw($testCase);
        $result = ($this->fn)($value);
        $testCase->stopSpan();
        return $result;
    }
}
