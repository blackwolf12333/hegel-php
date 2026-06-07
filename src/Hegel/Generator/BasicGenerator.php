<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Closure;
use Hegel\Exception\ProtocolException;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 *
 * @template T
 * @template-extends Generator<T>
 */
final class BasicGenerator extends Generator
{
    /**
     * @param array<string, mixed> $schema
     * @param (\Closure(mixed): T)|null $transform
     */
    public function __construct(
        public readonly array $schema,
        private readonly null|\Closure $transform = null,
    ) {}

    #[\Override]
    public function asBasic(): ?BasicGenerator
    {
        return $this;
    }

    /**
     * @return T
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        /** @var mixed $generatedValue */
        $generatedValue = $testCase->generateFromSchema($this->schema);

        /** @var T */
        return match ($this->transform) {
            null => $generatedValue,
            default => ($this->transform)($generatedValue)
        };
    }

    /**
     * @param mixed $raw
     * @return T
     */
    public function parseRaw(mixed $raw): mixed
    {
        if ($this->transform) {
            return ($this->transform)($raw);
        }

        /** @var T */
        return $raw;
    }
}
