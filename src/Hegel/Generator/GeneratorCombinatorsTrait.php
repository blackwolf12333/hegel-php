<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\SpanLabel;

trait GeneratorCombinatorsTrait
{
    public function map(\Closure $fn): Generator
    {
        assert($this instanceof Generator);
        return new MappedGenerator($this, $fn);
    }

    public function filter(\Closure $predicate): Generator
    {
        assert($this instanceof Generator);
        return new FilteredGenerator($this, $predicate);
    }

    public function flatMap(\Closure $fn): Generator
    {
        assert($this instanceof Generator);
        return new FlatMappedGenerator($this, $fn);
    }
}
