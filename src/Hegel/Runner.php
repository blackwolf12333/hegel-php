<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Exception\AssumeRejectedException;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Protocol\Connection;
use Hegel\Protocol\Stream;

final class Runner
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Run a property test.
     *
     * @param \Closure(TestCase): void $testFn
     * @param int $testCases Number of test cases to generate
     * @param int|null $seed Random seed
     * @param list<string> $suppressHealthCheck Health checks to suppress
     * @return RunResult
     */
    public function run(
        \Closure $testFn,
        int $testCases = 100,
        null|int $seed = null,
        array $suppressHealthCheck = [],
        null|\Closure $noteFn = null,
    ): RunResult {
        $testStream = $this->connection->newStream();
        $testStreamId = $testStream->streamId();

        // Send run_test on control stream
        $ctrl = $this->connection->controlStream();
        $runTestData = [
            'command' => 'run_test',
            'test_cases' => $testCases,
            'stream_id' => $testStreamId,
        ];
        if ($seed !== null) {
            $runTestData['seed'] = $seed;
        }
        if ($suppressHealthCheck !== []) {
            $runTestData['suppress_health_check'] = $suppressHealthCheck;
        }

        $ctrl->requestCbor($runTestData);

        // Event loop on test stream
        $finalErrors = [];
        $testDoneResults = null;

        while (true) {
            [$msgId, $event] = $testStream->receiveRequest();
            $eventName = $event['event'] ?? null;

            if ($eventName === 'test_case') {
                $this->handleTestCase($event, $testStream, $msgId, $testFn, $noteFn, $finalErrors);
                continue;
            }

            if ($eventName !== 'test_done') {
                continue;
            }

            $testDoneResults = $event['results'] ?? [];
            $testStream->sendReply($msgId, ['result' => true]);

            $nInteresting = $testDoneResults['interesting_test_cases'] ?? 0;
            $this->handleReplayCases($nInteresting, $testStream, $testFn, $noteFn, $finalErrors);
            break;
        }

        $testStream->close();

        return new RunResult(
            passed: $testDoneResults['passed'] ?? true,
            testCases: $testDoneResults['test_cases'] ?? 0,
            seed: $testDoneResults['seed'] ?? '',
            error: $testDoneResults['error'] ?? null,
            healthCheckFailure: $testDoneResults['health_check_failure'] ?? null,
            flaky: $testDoneResults['flaky'] ?? null,
            finalErrors: $finalErrors,
        );
    }

    /**
     * @param list<\Throwable> $finalErrors
     */
    private function handleTestCase(
        array $event,
        Stream $testStream,
        int $msgId,
        \Closure $testFn,
        null|\Closure $noteFn,
        array &$finalErrors,
    ): void {
        $caseStreamId = $event['stream_id'];
        $phase = $event['is_final'] ?? false ? TestPhase::Final : TestPhase::Exploration;

        $testStream->sendReply($msgId, ['result' => null]);

        $error = $this->runTestCase($caseStreamId, $testFn, $phase, $noteFn);

        if ($phase === TestPhase::Final && $error !== null) {
            $finalErrors[] = $error;
        }
    }

    /**
     * @param list<\Throwable> $finalErrors
     */
    private function handleReplayCases(
        int $nInteresting,
        Stream $testStream,
        \Closure $testFn,
        null|\Closure $noteFn,
        array &$finalErrors,
    ): void {
        for ($i = 0; $i < $nInteresting; $i++) {
            [$replayMsgId, $replayEvent] = $testStream->receiveRequest();

            if (!isset($replayEvent['event']) || $replayEvent['event'] !== 'test_case') {
                continue;
            }

            $caseStreamId = $replayEvent['stream_id'];
            $testStream->sendReply($replayMsgId, ['result' => null]);

            $error = $this->runTestCase($caseStreamId, $testFn, TestPhase::Final, $noteFn);
            if ($error !== null) {
                $finalErrors[] = $error;
            }
        }
    }

    /**
     * Run a single test case on its dedicated stream.
     *
     * @return \Throwable|null The error if the test case failed.
     */
    private function runTestCase(
        int $caseStreamId,
        \Closure $testFn,
        TestPhase $phase,
        null|\Closure $noteFn,
    ): null|\Throwable {
        $caseStream = $this->connection->connectStream($caseStreamId);
        $tc = new TestCase($caseStream, $phase, $noteFn);

        [$status, $origin, $error, $aborted] = $this->executeTestFn($testFn, $tc);

        if (!$aborted) {
            try {
                $caseStream->requestCbor([
                    'command' => 'mark_complete',
                    'status' => $status,
                    'origin' => $origin,
                ]);
            } catch (ConnectionException $e) {
                if (!str_contains($e->getMessage(), 'StopTest')) {
                    error_log('[hegel] mark_complete failed (stream may be closed): ' . $e->getMessage());
                }
            }
        }

        try {
            $caseStream->close();
        } catch (\Throwable $e) {
            error_log('[hegel] stream close failed: ' . $e->getMessage());
        }

        return $error;
    }

    /**
     * @return array{string, string|null, \Throwable|null, bool} [status, origin, error, aborted]
     */
    private function executeTestFn(\Closure $testFn, TestCase $tc): array
    {
        try {
            $testFn($tc);
            return ['VALID', null, null, false];
        } catch (AssumeRejectedException) {
            return ['INVALID', null, null, false];
        } catch (DataExhaustedException) {
            return ['VALID', null, null, true];
        } catch (\Throwable $e) {
            if ($this->isStopTestError($e)) {
                return ['VALID', null, null, true];
            }
            return ['INTERESTING', $this->formatOrigin($e), $e, false];
        }
    }

    private function isStopTestError(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        return (
            str_contains($msg, 'StopTest')
            || str_contains($msg, 'overflow')
            || str_contains($msg, 'FlakyStrategyDefinition')
            || str_contains($msg, 'FlakyReplay')
        );
    }

    private function formatOrigin(\Throwable $e): string
    {
        return sprintf('%s at %s:%d', get_class($e), $e->getFile(), $e->getLine());
    }
}
