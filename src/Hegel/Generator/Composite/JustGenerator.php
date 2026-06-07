<?php

namespace Hegel\Generator\Composite;

use Hegel\Generator\BasicGenerator;
use Hegel\Generator\Generator;
use Hegel\TestCase;

class JustGenerator extends Generator
{
    public function __construct(private readonly mixed $value)
    {
    }

    /**
     * @inheritDoc
     */
    public function draw(TestCase $testCase): mixed
    {
        return $this->value;
    }

    #[\Override]
    public function asBasic(): ?BasicGenerator
    {
        return new BasicGenerator(
            ['type' => 'constant', 'value' => $this->value],
            fn() => $this->value
        );
    }
}
