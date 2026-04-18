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
 * @template-implements Generator<TOut>
 */
final class FlatMappedGenerator implements Generator
{
    /** @use \Hegel\Generator\GeneratorCombinatorsTrait<TIn> */
    use GeneratorCombinatorsTrait;

    /**
     * @param Generator<TIn> $inner
     * @param \Closure(TIn): Generator<TOut> $fn
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
        $testCase->startSpan(SpanLabel::FlatMap);
        try {
            /** @var mixed $derived */
            $derived = ($this->fn)($this->inner->draw($testCase));
            assert($derived instanceof Generator, 'flatMap callback must return a Generator');
            return $derived->draw($testCase);
        } finally {
            $testCase->stopSpan();
        }
    }
}
