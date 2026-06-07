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
 * @template-extends  Generator<TOut>
 */
final class MappedGenerator extends Generator
{
    /**
     * @param Generator<TIn> $inner
     * @param \Closure(TIn): TOut $fn
     */
    public function __construct(
        private readonly Generator $inner,
        private readonly \Closure $fn,
    ) {
    }

    #[\Override]
    public function asBasic(): ?BasicGenerator
    {
        $sourceBasic = $this->inner->asBasic();
        if (!$sourceBasic) return null;

        return new BasicGenerator(
            $sourceBasic->schema,
            static fn(mixed $raw) => ($this->fn)($sourceBasic->parseRaw($raw))
        );
    }

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        $sourceBasic = $this->inner->asBasic();
        if ($sourceBasic !== null) {
            return ($this->fn)($sourceBasic->draw($testCase));
        }

        $testCase->startSpan(SpanLabel::Mapped);
        $result = ($this->fn)($this->inner->draw($testCase));
        $testCase->stopSpan();
        return $result;
    }
}
