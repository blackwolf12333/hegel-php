<?php

declare(strict_types=1);

namespace Hegel\Generator;

trait GeneratorCombinatorsTrait
{
    public function map(\Closure $fn): Generator
    {
        return new MappedGenerator($this, $fn);
    }

    public function filter(\Closure $predicate): Generator
    {
        return new FilteredGenerator($this, $predicate);
    }

    public function flatMap(\Closure $fn): Generator
    {
        return new FlatMappedGenerator($this, $fn);
    }
}
