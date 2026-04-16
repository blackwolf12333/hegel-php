<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

final readonly class GenerateCommand implements Command
{
    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(
        public array $schema,
    ) {}

    #[\Override]
    public function toArray(): array
    {
        return [
            'command' => 'generate',
            'schema' => $this->schema,
        ];
    }
}
