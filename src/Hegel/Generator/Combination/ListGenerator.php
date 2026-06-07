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
 *
 * @template T
 * @template-extends Generator<list<T>>
 */
final class ListGenerator extends Generator
{
    /** @var BasicGenerator<list<T>>|null  */
    private ?BasicGenerator $basic;

    /**
     * @param Generator<T> $elements
     * @param int $minSize
     * @param int|null $maxSize
     * @param bool $unique
     */
    public function __construct(
        private Generator $elements,
        private int $minSize = 0,
        private null|int $maxSize = null,
        private bool $unique = false,
    ) {
        $elementBasic = $this->elements->asBasic();
        if ($elementBasic) {
            $schema = [
                'type' => 'list',
                'unique' => $this->unique,
                'elements' => $elementBasic->schema,
                'min_size' => $this->minSize
            ];
            if ($this->maxSize) {
                $schema['max_size'] = $this->maxSize;
            }

            $this->basic = new BasicGenerator(
                $schema,
                static function (mixed $raw) use ($elementBasic) {
                    if (!is_array($raw)) throw new \Exception("Expected array");

                    return array_map(static fn(mixed $v) => $elementBasic->parseRaw($v), $raw);
                }
            );
        } else {
            $this->basic = null;
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function minSize(int $value): self
    {
        if ($this->maxSize !== null && $value > $this->maxSize) {
            throw new \InvalidArgumentException('minSize cannot be greater than maxSize');
        }
        $new = clone $this;
        $new->minSize = $value;
        return $new;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function maxSize(int $value): self
    {
        if ($value < $this->minSize) {
            throw new \InvalidArgumentException('maxSize cannot be less than minSize');
        }
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
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        if ($this->basic !== null) return $this->basic->draw($testCase);

        $testCase->startSpan(SpanLabel::List_);

        $result = [];
        $collection = $testCase->newCollection($this->minSize, $this->maxSize);

        while ($collection->more()) {
            $drawn = $this->elements->draw($testCase);

            if ($this->unique && array_any($result, fn($existing) => $drawn === $existing)) {
                $collection->reject();
                continue;
            }

            $result[] = $drawn;
        }

        $testCase->stopSpan();
        return $result;
    }
}
