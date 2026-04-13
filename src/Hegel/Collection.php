<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Protocol\Stream;

final class Collection
{
    public function __construct(
        private readonly int|string $collectionId,
        private readonly Stream $stream,
    ) {}

    public function more(): bool
    {
        $result = $this->stream->requestCbor([
            'command' => 'collection_more',
            'collection_id' => $this->collectionId,
        ]);
        assert(is_bool($result));
        return $result;
    }

    public function reject(): void
    {
        $this->stream->requestCbor([
            'command' => 'collection_reject',
            'collection_id' => $this->collectionId,
        ]);
    }
}
