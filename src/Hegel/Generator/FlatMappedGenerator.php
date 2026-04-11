<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 */
final class FlatMappedGenerator implements Generator
{
    use GeneratorCombinatorsTrait;

    public function __construct(
        private readonly Generator $inner,
        private readonly \Closure $fn,
    ) {}

    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        $testCase->startSpan(SpanLabel::FlatMap->value);
        $value = $this->inner->draw($testCase);
        /** @var Generator $derived */
        $derived = ($this->fn)($value);
        $result = $derived->draw($testCase);
        $testCase->stopSpan();
        return $result;
    }
}
