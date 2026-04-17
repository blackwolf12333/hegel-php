<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\TestCase;

/**
 * @internal
 */
final class DictGenerator implements Generator
{
    use GeneratorCombinatorsTrait;

    public function __construct(
        private Generator $keys,
        private Generator $values,
        private int $minSize = 0,
        private null|int $maxSize = null,
    ) {}

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

    /** @return array<string, mixed> */
    #[\Override]
    public function schema(): array
    {
        $schema = [
            'type' => 'dict',
            'keys' => $this->keys->schema(),
            'values' => $this->values->schema(),
            'min_size' => $this->minSize,
        ];

        if ($this->maxSize !== null) {
            $schema['max_size'] = $this->maxSize;
        }

        return $schema;
    }

    /**
     * @return array<string|int, mixed>
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        /** @var mixed $result */
        $result = $testCase->generateFromSchema($this->schema());
        assert(is_array($result), 'Dict schema result must be an array');
        // Dict values come back as [[k,v], [k,v], ...], convert to assoc array
        $dict = [];
        /** @var mixed $pair */
        foreach ($result as $pair) {
            assert(is_array($pair) && array_key_exists(0, $pair) && array_key_exists(1, $pair), 'Dict entry must be a [key, value] pair');
            /** @var mixed $key */
            $key = $pair[0];
            assert(is_string($key) || is_int($key), 'Dict key must be a string or int');
            $dict[$key] = $pair[1];
        }
        return $dict;
    }
}
