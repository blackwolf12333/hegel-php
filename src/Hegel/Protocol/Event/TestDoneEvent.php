<?php

declare(strict_types=1);

namespace Hegel\Protocol\Event;

final readonly class TestDoneEvent
{
    public function __construct(
        public int $interestingTestCases,
        public bool $passed,
        public int $testCases,
        public string $seed,
        public null|string $error,
        public null|string $healthCheckFailure,
        public null|string $flaky,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $results */
        $results = $data['results'] ?? [];

        return new self(
            interestingTestCases: (int) ($results['interesting_test_cases'] ?? 0),
            passed: ($results['passed'] ?? true) === true,
            testCases: (int) ($results['test_cases'] ?? 0),
            seed: (string) ($results['seed'] ?? ''),
            error: array_key_exists('error', $results) ? (string) $results['error'] : null,
            healthCheckFailure: array_key_exists('health_check_failure', $results)
                ? (string) $results['health_check_failure']
                : null,
            flaky: array_key_exists('flaky', $results) ? (string) $results['flaky'] : null,
        );
    }
}
