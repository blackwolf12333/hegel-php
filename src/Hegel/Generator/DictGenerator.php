<?php

declare(strict_types=1);

namespace Hegel\Generator;

use Hegel\TestCase;

/**
 * @internal
 */
final class DictGenerator implements SchemaGenerator
{
    use GeneratorCombinatorsTrait;

    public function __construct(
        private SchemaGenerator $keys,
        private SchemaGenerator $values,
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

    #[\Override]
    public function draw(TestCase $testCase): mixed
    {
        $result = $testCase->generateFromSchema($this->schema());
        // Dict values come back as [[k,v], [k,v], ...], convert to assoc array
        if (is_array($result)) {
            $dict = [];
            foreach ($result as $pair) {
                $dict[$pair[0]] = $pair[1];
            }
            return $dict;
        }
        return $result;
    }
}
