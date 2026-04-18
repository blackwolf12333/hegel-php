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
 * @template-implements SchemaGenerator<T>
 */
final class BasicGenerator implements SchemaGenerator
{
    /** @use \Hegel\Generator\GeneratorCombinatorsTrait<T> */
    use GeneratorCombinatorsTrait;

    /**
     * @param array<string, mixed> $schema
     * @param (\Closure(mixed): T)|null $transform
     * @param SpanLabel|null $spanLabel
     */
    public function __construct(
        private readonly array $schema,
        private readonly null|\Closure $transform = null,
        private readonly ?SpanLabel $spanLabel = null,
    ) {}

    /** @return array<string, mixed> */
    #[\Override]
    public function schema(): array
    {
        return $this->schema;
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
        // This weird defering is done because I think there is a bug in mago that said that in the finally block
        // below the if statement that was there `if ($this->spanLable !== null)` would always be false because
        // I think it assumed that this initial if statement already ensured `$this->spanLabel` will always be null
        // in the `finally` block. But that's not really true in this case, if it's null here it is definitely also
        // still null in the `finally` block.
        $stopSpan = static function() {};
        if ($this->spanLabel !== null) {
            $testCase->startSpan($this->spanLabel);
            $stopSpan = $testCase->stopSpan(...);
        }

        try {
            /** @var mixed $generatedValue */
            $generatedValue = $testCase->generateFromSchema($this->schema);

            /** @var T */
            return match($this->transform) {
                null => $generatedValue,
                default => ($this->transform)($generatedValue)
            };
        } finally {
            $stopSpan();
        }
    }

    #[\Override]
    public function map(Closure $fn): Generator {
        return new BasicGenerator(
            $this->schema(),
            fn(mixed $value) => $this->transform !== null
                ? $fn(($this->transform)($value))
                : $fn($value),
        );
    }
}
