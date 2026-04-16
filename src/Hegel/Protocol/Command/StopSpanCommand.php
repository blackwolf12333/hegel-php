<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

final readonly class StopSpanCommand implements Command
{
    public function __construct(
        public bool $discard,
    ) {}

    #[\Override]
    public function toArray(): array
    {
        return [
            'command' => 'stop_span',
            'discard' => $this->discard,
        ];
    }
}
