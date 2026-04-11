<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\TestCase;

/**
 * @internal
 */
final class DomainGenerator implements SchemaGenerator
{
    use GeneratorCombinatorsTrait;

    public function __construct(
        private readonly int $maxLength = 255,
    ) {}

    public function maxLength(int $value): self
    {
        return new self($value);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function schema(): array
    {
        return [
            'type' => 'domain',
            'max_length' => $this->maxLength,
        ];
    }

    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        return $testCase->generateFromSchema($this->schema());
    }
}
