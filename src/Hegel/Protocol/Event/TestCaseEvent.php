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
        assert(array_key_exists('stream_id', $data), 'TestCaseEvent data must contain stream_id');
        return new self(
            streamId: (int) $data['stream_id'],
            isFinal: ($data['is_final'] ?? false) === true,
        );
    }
}
