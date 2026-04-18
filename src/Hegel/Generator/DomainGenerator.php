<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Exception\ProtocolException;
use Hegel\TestCase;

/**
 * @internal
 *
 * @template-implements SchemaGenerator<string>
 */
final class DomainGenerator implements SchemaGenerator
{
    /** @use \Hegel\Generator\GeneratorCombinatorsTrait<string> */
    use GeneratorCombinatorsTrait;

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

    /** @return array{type: 'domain', max_length: positive-int} */
    #[\Override]
    public function schema(): array
    {
        return [
            'type' => 'domain',
            'max_length' => $this->maxLength,
        ];
    }

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        return $testCase->generateFromSchema($this->schema());
    }
}
