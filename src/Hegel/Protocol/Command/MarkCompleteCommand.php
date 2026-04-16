<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

final readonly class MarkCompleteCommand implements Command
{
    public function __construct(
        public string $status,
        public null|string $origin = null,
    ) {}

    #[\Override]
    public function toArray(): array
    {
        return [
            'command' => 'mark_complete',
            'status' => $this->status,
            'origin' => $this->origin,
        ];
    }
}
