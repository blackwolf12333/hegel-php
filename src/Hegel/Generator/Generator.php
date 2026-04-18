<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\TestCase;

/**
 * @internal Do not implement directly. Use Generators factory methods.
 * @template T
 */
interface Generator
{
    /**
     * @return T
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    public function draw(TestCase $testCase): mixed;

    /**
     * @template TOut
     * @param \Closure(T): TOut $fn
     * @return Generator<TOut>
     */
    public function map(\Closure $fn): Generator;

    /**
     * @param \Closure(T): bool $predicate
     * @return Generator<T>
     */
    public function filter(\Closure $predicate): Generator;

    /**
     * @template TOut
     * @param \Closure(T): Generator<TOut> $fn
     * @return FlatMappedGenerator<T, TOut>
     */
    public function flatMap(\Closure $fn): Generator;
}
