<?php

declare(strict_types=1);

namespace Hegel;

final readonly class RunResult
{
    /**
     * @param list<\Throwable> $finalErrors
     */
    public function __construct(
        public bool $passed,
        public int $testCases,
        public string $seed,
        public null|string $error = null,
        public null|string $healthCheckFailure = null,
        public null|string $flaky = null,
        public array $finalErrors = [],
    ) {}
}
