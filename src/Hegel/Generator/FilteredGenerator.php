<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Exception\AssumeRejectedException;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 */
final class FilteredGenerator implements Generator
{
    use GeneratorCombinatorsTrait;

    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly Generator $inner,
        private readonly \Closure $predicate,
    ) {}

    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        $testCase->startSpan(SpanLabel::Filter->value);

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $drawn = $this->inner->draw($testCase);
            if (($this->predicate)($drawn)) {
                $testCase->stopSpan();
                return $drawn;
            }
        }

        $testCase->discardSpan();
        throw new AssumeRejectedException(sprintf('Filter rejected %d consecutive attempts', self::MAX_ATTEMPTS));
    }
}
