<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Exception\ProtocolException;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 *
 * @template TIn
 * @template TOut
 *
 * @template-extends  Generator<TOut>
 */
final class FlatMappedGenerator extends Generator
{
    /**
     * @param Generator<TIn> $inner
     * @param \Closure(TIn): Generator<TOut> $fn
     */
    public function __construct(
        private readonly Generator $inner,
        private readonly \Closure $fn,
    ) {}

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        $testCase->startSpan(SpanLabel::FlatMap);
        $derived = ($this->fn)($this->inner->draw($testCase));
        $drawn = $derived->draw($testCase);
        $testCase->stopSpan();
        return $drawn;
    }
}
