<?php

declare(strict_types=1);

namespace Hegel\Generator\Combination;

use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Exception\ProtocolException;
use Hegel\Generator\BasicGenerator;
use Hegel\Generator\Generator;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @template T
 * @template-extends Generator<list<T>>
 */
class TupleGenerator extends Generator
{
    private ?BasicGenerator $basic = null;

    /**
     * @param Generator[] $elements
     */
    public function __construct(
        private readonly array $elements
    )
    {
        $basics = array_map(static fn(Generator $g) => $g->asBasic(), $this->elements);

        if (array_all($basics, static fn(?BasicGenerator $b) => $b !== null)) {
            $this->basic = new BasicGenerator(
                [
                    'type' => 'tuple',
                    'elements' => array_map(
                        static fn($b) => $b->schema,
                        $basics
                    )
                ],
                function(mixed $raw) use ($basics) {
                    if (!is_array($raw)) throw new \Exception("Expected array");

                    return array_map(
                        fn(mixed $v, int $i) => $basics[$i]->parseRaw($v),
                        $raw,
                        range(0, count($raw) - 1),
                    );
                }
            );
        }
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
        if ($this->basic !== null) {
            return $this->basic->draw($testCase);
        }

        $testCase->startSpan(SpanLabel::Tuple);

        $tuple = [];
        foreach ($this->elements as $elem) {
            $tuple[] = $testCase->draw($elem);
        }

        $testCase->stopSpan();
        return $tuple;
    }

    #[\Override]
    public function asBasic(): ?BasicGenerator
    {
        return $this->basic;
    }
}
