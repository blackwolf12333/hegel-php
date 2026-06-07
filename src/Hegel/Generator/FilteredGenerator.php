<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Exception\AssumeRejectedException;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Exception\ProtocolException;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 *
 * @template T
 * @template-extends  Generator<T>
 */
final class FilteredGenerator extends Generator
{
    private const int MAX_ATTEMPTS = 3;

    /**
     * @param Generator<T> $inner
     * @param \Closure $predicate
     */
    public function __construct(
        private readonly Generator $inner,
        private readonly \Closure $predicate,
    ) {}

    /**
     * @throws AssumeRejectedException
     * @throws ConnectionException|ProtocolException
     * @throws DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $testCase->startSpan(SpanLabel::Filter);
            /** @var mixed $drawn */
            $drawn = $this->inner->draw($testCase);
            if (($this->predicate)($drawn)) {
                $testCase->stopSpan();
                return $drawn;
            }
            $testCase->discardSpan();
        }

        throw new AssumeRejectedException(sprintf('Filter rejected %d consecutive attempts', self::MAX_ATTEMPTS));
    }
}
