<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Exception\AssumeRejectedException;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Exception\ServerErrorType;
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

        while (true) {
            [$msgId, $event] = $testStream->receiveRequest();
            assert(is_array($event));
            $eventName = $event['event'] ?? null;

            if ($eventName === 'test_case') {
                $this->handleTestCase($event, $testStream, $msgId, $testFn, $noteFn, $finalErrors);
                continue;
            }

            if ($eventName !== 'test_done') {
                continue;
            }

            $results = $event['results'] ?? [];
            assert(is_array($results));
            $testStream->sendReply($msgId, ['result' => true]);

            $nInteresting = (int) ($results['interesting_test_cases'] ?? 0);
            $this->handleReplayCases($nInteresting, $testStream, $testFn, $noteFn, $finalErrors);
            break;
        }

        $testStream->close();

        return new RunResult(
            passed: (bool) ($results['passed'] ?? true),
            testCases: (int) ($results['test_cases'] ?? 0),
            seed: (string) ($results['seed'] ?? ''),
            error: isset($results['error']) ? (string) $results['error'] : null,
            healthCheckFailure: isset($results['health_check_failure']) ? (string) $results['health_check_failure'] : null,
            flaky: isset($results['flaky']) ? (string) $results['flaky'] : null,
            finalErrors: $finalErrors,
        );
    }

    /**
     * @param array<array-key, mixed> $event
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
        $caseStreamId = (int) $event['stream_id'];
        $isFinal = (bool) ($event['is_final'] ?? false);
        $phase = $isFinal ? TestPhase::Final : TestPhase::Exploration;

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
            assert(is_array($replayEvent));

            if (!isset($replayEvent['event']) || $replayEvent['event'] !== 'test_case') {
                continue;
            }

            $caseStreamId = (int) $replayEvent['stream_id'];
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
            } catch (ConnectionException) { // @mago-expect lint:no-empty-catch-clause
            }
        }

        try {
            $caseStream->close();
        } catch (\Throwable) { // @mago-expect lint:no-empty-catch-clause
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
            if ($this->isExpectedTermination($e)) {
                return ['VALID', null, null, true];
            }
            return ['INTERESTING', $this->formatOrigin($e), $e, false];
        }
    }

    private function isExpectedTermination(\Throwable $e): bool
    {
        return (
            $e instanceof ConnectionException
            && $e->serverErrorType !== null
            && $e->serverErrorType->isExpectedTermination()
        );
    }

    private function formatOrigin(\Throwable $e): string
    {
        return sprintf('%s at %s:%d', get_class($e), $e->getFile(), $e->getLine());
    }
}
