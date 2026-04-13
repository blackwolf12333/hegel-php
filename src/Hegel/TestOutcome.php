<?php

declare(strict_types=1);

namespace Hegel;

final readonly class TestOutcome
{
    public function __construct(
        public string $status,
        public null|string $origin,
        public null|\Throwable $error,
        public bool $aborted,
    ) {}
}
