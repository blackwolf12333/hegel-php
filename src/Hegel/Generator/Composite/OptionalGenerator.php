<?php

namespace Hegel\Generator\Composite;

use Hegel\Generator\BasicGenerator;
use Hegel\Generator\Generator;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @template T
 * @template-extends Generator<T|null>
 */
class OptionalGenerator extends Generator
{
    /** @var BasicGenerator<T|null>|null  */
    private ?BasicGenerator $basic;

    /**
     * @param Generator<T> $inner
     */
    public function __construct(
        private Generator $inner
    )
    {
        $innerBasic = $this->inner->asBasic();

        if ($innerBasic) {
            $nullSchema = [
                'type' => 'tuple',
                'elements' => [
                    ['type' => 'constant', 'value' => 0],
                    ['type' => 'null'],
                ],
            ];
            $valueSchema = [
                'type' => 'tuple',
                'elements' => [
                    ['type' => 'constant', 'value' => 1],
                    $innerBasic->schema,
                ],
            ];

            $this->basic = new BasicGenerator(
                [ 'type' => 'one_of', 'generators' => [$nullSchema, $valueSchema]],
                static function($raw) use ($innerBasic) {
                    if (!is_array($raw)) throw new \Exception("Expected array");

                    $tag = $raw[0];

                    if ($tag === 0) {
                        return null;
                    }

                    return $innerBasic->parseRaw($raw[1]);
                }
            );
        } else {
            $this->basic = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function draw(TestCase $testCase): mixed
    {
        if ($this->basic !== null) return $this->basic->draw($testCase);

        $testCase->startSpan(SpanLabel::Optional);
        /** @var bool $isSome */
        $isSome = $testCase->generateFromSchema(['type' => 'boolean']);
        $result = $isSome ? $this->inner->draw($testCase) : null;
        $testCase->stopSpan();

        return $result;
    }

    public function asBasic(): ?BasicGenerator
    {
        return $this->basic;
    }
}
