<?php

declare(strict_types=1);

namespace Hegel\Protocol\Command;

final readonly class RunTestCommand implements Command
{
    /**
     * @param list<string> $suppressHealthCheck
     */
    public function __construct(
        public int $testCases,
        public int $streamId,
        public null|int $seed = null,
        public array $suppressHealthCheck = [],
    ) {}

    public function toArray(): array
    {
        $data = [
            'command' => 'run_test',
            'test_cases' => $this->testCases,
            'stream_id' => $this->streamId,
        ];
        if ($this->seed !== null) {
            $data['seed'] = $this->seed;
        }
        if ($this->suppressHealthCheck !== []) {
            $data['suppress_health_check'] = $this->suppressHealthCheck;
        }

        return $data;
    }
}
