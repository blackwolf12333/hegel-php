<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

final readonly class CollectionRejectCommand implements Command
{
    public function __construct(
        public int|string $collectionId,
    ) {}

    public function toArray(): array
    {
        return [
            'command' => 'collection_reject',
            'collection_id' => $this->collectionId,
        ];
    }
}
