<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Exception\ProtocolException;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 * @template TIn
 * @template TOut
 * @template-implements Generator<TOut>
 */
final class MappedGenerator implements Generator
{
    /** @use \Hegel\Generator\GeneratorCombinatorsTrait<TIn> */
    use GeneratorCombinatorsTrait;

    /**
     * @param Generator<TIn> $inner
     * @param \Closure(TIn): TOut $fn
     */
    public function __construct(
        private readonly Generator $inner,
        private readonly \Closure $fn,
    ) {}

    #[\Override]
    public function schema(): ?array
    {
        return null;
    }

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        $testCase->startSpan(SpanLabel::Mapped);
        try {
            return ($this->fn)($this->inner->draw($testCase));
        } finally {
            $testCase->stopSpan();
        }
    }
}
