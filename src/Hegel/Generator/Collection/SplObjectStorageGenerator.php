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
 * @template K
 * @template V
 *
 * @template-extends  Generator<\SplObjectStorage<K, V>>
 */
final class SplObjectStorageGenerator extends Generator
{
    private ?BasicGenerator $basic;

    /**
     * @param Generator<K> $keys
     * @param Generator<V> $values
     * @param int $minSize
     * @param int|null $maxSize
     */
    public function __construct(
        private readonly Generator $keys,
        private readonly Generator $values,
        private int                $minSize = 0,
        private null|int           $maxSize = null,
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
                    $map = new \SplObjectStorage();

                    foreach ($raw as $entry) {
                        if (!is_array($entry) || count($entry) !== 2) {
                            throw new \Exception("Expected [key, value] pair");
                        }

                        $map[$keysBasic->parseRaw($entry[0])] = $valuesBasic->parseRaw($entry[1]);
                    }

                    return $map;
                }
            );
        }
    }

    public function minSize(int $value): self
    {
        $new = clone $this;
        $new->minSize = $value;
        return $new;
    }

    public function maxSize(int $value): self
    {
        $new = clone $this;
        $new->maxSize = $value;
        return $new;
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
        $map = new \SplObjectStorage();

        while ($collection->more()) {
            $testCase->startSpan(SpanLabel::MapEntry);
            $key = $this->keys->draw($testCase);
            $value = $this->values->draw($testCase);
            $testCase->stopSpan();

            if ($map->offsetExists($key)) {
                $collection->reject();
                continue;
            }

            $map[$key] = $value;
        }
        $testCase->stopSpan();

        return $map;
    }
}
