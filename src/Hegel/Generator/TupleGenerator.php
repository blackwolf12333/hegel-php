<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Exception\ProtocolException;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @template T
 * @template-implements Generator<list<T>>
 */
class TupleGenerator implements Generator
{
    /** @use GeneratorCombinatorsTrait<list<T>> */
    use GeneratorCombinatorsTrait;

    /**
     * @param array<Generator<T>> $elements
     */
    public function __construct(
        private readonly array $elements
    )
    {
    }

    /**
     * @param TestCase $testCase
     * @return list<T>
     *
     * @throws ProtocolException|\InvalidArgumentException|ConnectionException|DataExhaustedException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        $schema = $this->schema();
        if ($schema !== null) {
            /** @var list<T> */
            return $testCase->generateFromSchema($schema);
        }

        $testCase->startSpan(SpanLabel::Tuple);

        try {
            $tuple = [];

            foreach ($this->elements as $elem) {
                $tuple[] = $testCase->draw($elem);
            }

            return $tuple;
        } finally {
            $testCase->stopSpan();
        }
    }

    #[\Override]
    public function schema(): ?array
    {
        $elementSchemas = array_map(static fn(Generator $elem) => $elem->schema(), $this->elements);
        if (array_any($elementSchemas, static fn($elem) => $elem === null)) {
            return null;
        }

        return [
            'type' => 'tuple',
            'elements' => $elementSchemas
        ];
    }
}
