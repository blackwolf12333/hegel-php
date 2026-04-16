<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

final readonly class StartSpanCommand implements Command
{
    public function __construct(
        public int $label,
    ) {}

    #[\Override]
    public function toArray(): array
    {
        return [
            'command' => 'start_span',
            'label' => $this->label,
        ];
    }
}
