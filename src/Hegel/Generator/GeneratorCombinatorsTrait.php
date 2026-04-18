<?php

declare(strict_types=1);

namespace Hegel\Generator;

/**
 * @template TIn
 */
trait GeneratorCombinatorsTrait
{
    /**
     * @template TOut
     * @param \Closure(TIn): TOut $fn
     * @return Generator<TOut>
     */
    public function map(\Closure $fn): Generator
    {
        assert($this instanceof Generator, 'GeneratorCombinatorsTrait must be used on a Generator');
        return new MappedGenerator($this, $fn);
    }

    /**
     * @param \Closure(TIn): bool $predicate
     * @return Generator<TIn>
     */
    public function filter(\Closure $predicate): Generator
    {
        assert($this instanceof Generator, 'GeneratorCombinatorsTrait must be used on a Generator');
        return new FilteredGenerator($this, $predicate);
    }

    /**
     * @template TOut
     * @param \Closure(TIn): Generator<TOut> $fn
     * @return Generator<TOut>
     */
    public function flatMap(\Closure $fn): Generator
    {
        assert($this instanceof Generator, 'GeneratorCombinatorsTrait must be used on a Generator');
        return new FlatMappedGenerator($this, $fn);
    }
}
