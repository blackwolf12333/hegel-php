<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

final readonly class TargetCommand implements Command
{
    public function __construct(
        public float $value,
        public string $label,
    ) {}

    public function toArray(): array
    {
        return [
            'command' => 'target',
            'value' => $this->value,
            'label' => $this->label,
        ];
    }
}
