<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\TestCase;

/**
 * @internal Do not implement directly. Use Generators factory methods.
 */
interface Generator extends SchemaGenerator
{
    public function draw(TestCase $testCase): mixed;

    public function map(\Closure $fn): Generator;

    public function filter(\Closure $predicate): Generator;

    public function flatMap(\Closure $fn): Generator;
}
