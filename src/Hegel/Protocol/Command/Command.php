<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

interface Command
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
