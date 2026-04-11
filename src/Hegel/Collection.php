<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Protocol\Stream;

final class Collection
{
    public function __construct(
        private readonly mixed $collectionId,
        private readonly Stream $stream,
    ) {}

    public function more(): bool
    {
        return (bool) $this->stream->requestCbor([
            'command' => 'collection_more',
            'collection_id' => $this->collectionId,
        ]);
    }

    public function reject(): void
    {
        $this->stream->requestCbor([
            'command' => 'collection_reject',
            'collection_id' => $this->collectionId,
        ]);
    }
}
