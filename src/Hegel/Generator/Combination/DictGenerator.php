<?php

declare(strict_types=1);

namespace Hegel\Generator\Combination;

use Hegel\Exception\ProtocolException;
use Hegel\Generator\BasicGenerator;
use Hegel\Generator\Generator;
use Hegel\SpanLabel;
use Hegel\TestCase;

/**
 * @internal
 * @template K of array-key
 * @template V
 *
 * @template-extends Generator<array<K, V>>
 */
final class DictGenerator extends Generator
{
    /** @var BasicGenerator<array<K, V>>|null  */
    private ?BasicGenerator $basic = null;

    /**
     * @param Generator<K> $keys
     * @param Generator<V> $values
     * @param int $minSize
     * @param int|null $maxSize
     */
    public function __construct(
        private readonly Generator $keys,
        private readonly Generator $values,
        public readonly int        $minSize = 0,
        public readonly null|int   $maxSize = null,
    ) {
        $keysBasic = $this->keys->asBasic();
        $valuesBasic = $this->values->asBasic();

        if ($keysBasic && $valuesBasic) {
            $schema = [
                'type' => 'dict',
                'keys' => $keysBasic->schema,
                'values' => $valuesBasic->schema,
                'min_size' => $this->minSize
            ];
            if ($this->maxSize !== null) {
                $schema['max_size'] = $this->maxSize;
            }

            $this->basic = new BasicGenerator(
                $schema,
                function ($raw) use ($keysBasic, $valuesBasic) {
                    if (!is_array($raw)) throw new \Exception("Expected array");
                    $map = [];

                    foreach ($raw as $entry) {
                        if (!is_array($entry) || count($entry) !== 2 || !isset($entry[0], $entry[1])) {
                            throw new \Exception("Expected [key, value] pair");
                        }

                        $key = $keysBasic->parseRaw($entry[0]);
                        $value = $valuesBasic->parseRaw($entry[1]);
                        $map[$key] = $value;
                    }

                    return $map;
                }
            );
        }
    }

    public function minSize(int $value): self
    {
        return new self($this->keys, $this->values, $value, $this->maxSize);
    }

    public function maxSize(int $value): self
    {
        return new self($this->keys, $this->values, $this->minSize, $value);
    }

    #[\Override]
    public function asBasic(): ?BasicGenerator
    {
        return $this->basic;
    }

    /**
     * @return array<K, V>
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        if ($this->basic) return $this->basic->draw($testCase);

        $testCase->startSpan(SpanLabel::Map);
        $collection = $testCase->newCollection($this->minSize, $this->maxSize);
        $map = [];

        while ($collection->more()) {
            $testCase->startSpan(SpanLabel::MapEntry);
            $key = $this->keys->draw($testCase);
            $value = $this->values->draw($testCase);
            $testCase->stopSpan();

            if (array_key_exists($key, $map)) {
                $collection->reject();
                continue;
            }

            $map[$key] = $value;
        }
        $testCase->stopSpan();

        return $map;
    }
}
