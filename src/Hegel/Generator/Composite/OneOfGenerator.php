<?php

namespace Hegel\Generator\Composite;

use Hegel\Exception\ProtocolException;
use Hegel\Generator\BasicGenerator;
use Hegel\Generator\Generator;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @template T
 * @template-extends Generator<T>
 */
class OneOfGenerator extends Generator {

    /** @var BasicGenerator<T>|null */
    private ?BasicGenerator $basic;
    /** @var Generator<T>[] */
    private array $sources;

    /**
     * @param Generator<T> ...$sources
     */
    public function __construct(Generator ...$sources)
    {
        $this->sources = $sources;
        $basics = array_map(static fn(Generator $g) => $g->asBasic(), $sources);

        if (array_all($basics, static fn($b) => $b !== null)) {
            $basicsSchemas = array_map(static fn($b) => $b->schema, $basics);
            $this->basic = new BasicGenerator(
                [
                    'type' => 'one_of',
                    'generators' => $basicsSchemas,
                ],
                static function(mixed $raw) use ($basics) {
                    if (!is_array($raw) || count($raw) !== 2 || !isset($raw[0], $raw[1])) {
                        throw new \Exception("Expected an array of length 2, got: " . print_r($raw, true));
                    }

                    $tag = $raw[0];

                    return $basics[$tag]->parseRaw($raw[1]);
                }
            );
        } else {
            $this->basic = null;
        }
    }

    /**
     * @param TestCase $testCase
     * @return T
     * @throws ProtocolException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        if ($this->basic !== null) return $this->basic->draw($testCase);

        $testCase->startSpan(SpanLabel::OneOf);

        /** @var int $index */
        $index = $testCase->generateFromSchema(['type' => 'integer', 'min_value' => 0, 'max_value' => count($this->sources) - 1]);

        $result = $this->sources[$index]->draw($testCase);
        $testCase->stopSpan();

        return $result;
    }

    #[\Override]
    public function asBasic(): ?BasicGenerator
    {
        return $this->basic;
    }
}
