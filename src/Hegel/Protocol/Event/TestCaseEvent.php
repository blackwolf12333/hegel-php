<?php

declare(strict_types=1);

namespace Hegel\Protocol\Event;

final readonly class TestCaseEvent
{
    public function __construct(
        public int $streamId,
        public bool $isFinal,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            streamId: (int) $data['stream_id'],
            isFinal: (bool) ($data['is_final'] ?? false),
        );
    }
}
