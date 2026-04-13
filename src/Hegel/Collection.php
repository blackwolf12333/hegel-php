<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Protocol\Command\CollectionMoreCommand;
use Hegel\Protocol\Command\CollectionRejectCommand;
use Hegel\Protocol\Stream;

final class Collection
{
    public function __construct(
        private readonly int|string $collectionId,
        private readonly Stream $stream,
    ) {}

    public function more(): bool
    {
        $result = $this->stream->requestCbor(new CollectionMoreCommand($this->collectionId));
        assert(is_bool($result));
        return $result;
    }

    public function reject(): void
    {
        $this->stream->requestCbor(new CollectionRejectCommand($this->collectionId));
    }
}
