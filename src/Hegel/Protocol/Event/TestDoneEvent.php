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
        $results = $data['results'] ?? [];
        assert(is_array($results));

        return new self(
            interestingTestCases: (int) ($results['interesting_test_cases'] ?? 0),
            passed: (bool) ($results['passed'] ?? true),
            testCases: (int) ($results['test_cases'] ?? 0),
            seed: (string) ($results['seed'] ?? ''),
            error: isset($results['error']) ? (string) $results['error'] : null,
            healthCheckFailure: isset($results['health_check_failure'])
                ? (string) $results['health_check_failure']
                : null,
            flaky: isset($results['flaky']) ? (string) $results['flaky'] : null,
        );
    }
}
