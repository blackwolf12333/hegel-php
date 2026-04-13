<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

final readonly class NewCollectionCommand implements Command
{
    public function __construct(
        public int $minSize,
        public null|int $maxSize = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'command' => 'new_collection',
            'min_size' => $this->minSize,
        ];
        if ($this->maxSize !== null) {
            $data['max_size'] = $this->maxSize;
        }

        return $data;
    }
}
