<?php

declare(strict_types=1);

namespace Hegel\Generator\Strings;

use Hegel\Exception\ProtocolException;
use Hegel\Generator\Generator;
use Hegel\TestCase;

/**
 * @internal
 *
 * @template-extends  Generator<string>
 */
final class DomainGenerator extends Generator
{
    /**
     * @param positive-int $maxLength
     */
    public function __construct(
        private readonly int $maxLength = 255,
    ) {}

    /**
     * @param positive-int $value
     * @return self
     */
    public function maxLength(int $value): self
    {
        return new self($value);
    }

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        return $testCase->generateFromSchema([
            'type' => 'domain',
            'max_length' => $this->maxLength,
        ]);
    }
}
