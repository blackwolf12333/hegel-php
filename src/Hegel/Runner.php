<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Exception\AssumeRejectedException;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Protocol\Command\MarkCompleteCommand;
use Hegel\Protocol\Command\RunTestCommand;
use Hegel\Protocol\Connection;
use Hegel\Protocol\Event\TestCaseEvent;
use Hegel\Protocol\Event\TestDoneEvent;
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
     * @throws ConnectionException
     * @throws \InvalidArgumentException
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
        $ctrl->requestCbor(new RunTestCommand(
            testCases: $testCases,
            streamId: $testStreamId,
            seed: $seed,
            suppressHealthCheck: $suppressHealthCheck,
        ));

        // Event loop on test stream
        $finalErrors = [];

        while (true) {
            $received = $testStream->receiveRequest();
            $msgId = $received[0];
            /** @var mixed $rawEvent */
            $rawEvent = $received[1];
            assert(is_array($rawEvent), 'Event payload must be an array');
            /** @var array<string, mixed> $event */
            $event = $rawEvent;
            /** @var mixed $eventName */
            $eventName = $event['event'] ?? null;

            if ($eventName === 'test_case') {
                $testCaseEvent = TestCaseEvent::fromArray($event);
                $this->handleTestCase($testCaseEvent, $testStream, $msgId, $testFn, $noteFn, $finalErrors);
                continue;
            }

            if ($eventName !== 'test_done') {
                continue;
            }

            $testDoneEvent = TestDoneEvent::fromArray($event);
            $testStream->sendReply($msgId, ['result' => true]);

            $this->handleReplayCases($testDoneEvent->interestingTestCases, $testStream, $testFn, $noteFn, $finalErrors);
            break;
        }

        $testStream->close();

        return new RunResult(
            passed: $testDoneEvent->passed,
            testCases: $testDoneEvent->testCases,
            seed: $testDoneEvent->seed,
            error: $testDoneEvent->error,
            healthCheckFailure: $testDoneEvent->healthCheckFailure,
            flaky: $testDoneEvent->flaky,
            finalErrors: $finalErrors,
        );
    }

    /**
     * @param list<\Throwable> $finalErrors
     * @throws \InvalidArgumentException
     */
    private function handleTestCase(
        TestCaseEvent $event,
        Stream $testStream,
        int $msgId,
        \Closure $testFn,
        null|\Closure $noteFn,
        array &$finalErrors,
    ): void {
        $phase = $event->isFinal ? TestPhase::Final : TestPhase::Exploration;

        $testStream->sendReply($msgId, ['result' => null]);

        $error = $this->runTestCase($event->streamId, $testFn, $phase, $noteFn);

        if ($phase === TestPhase::Final && $error !== null) {
            $finalErrors[] = $error;
        }
    }

    /**
     * @param list<\Throwable> $finalErrors
     * @throws ConnectionException
     * @throws \InvalidArgumentException
     */
    private function handleReplayCases(
        int $nInteresting,
        Stream $testStream,
        \Closure $testFn,
        null|\Closure $noteFn,
        array &$finalErrors,
    ): void {
        for ($i = 0; $i < $nInteresting; $i++) {
            $replayReceived = $testStream->receiveRequest();
            $replayMsgId = $replayReceived[0];
            /** @var mixed $rawReplayEvent */
            $rawReplayEvent = $replayReceived[1];
            assert(is_array($rawReplayEvent), 'Replay event payload must be an array');
            /** @var array<string, mixed> $replayEvent */
            $replayEvent = $rawReplayEvent;

            if (($replayEvent['event'] ?? null) !== 'test_case') {
                continue;
            }

            $event = TestCaseEvent::fromArray($replayEvent);
            $testStream->sendReply($replayMsgId, ['result' => null]);

            $error = $this->runTestCase($event->streamId, $testFn, TestPhase::Final, $noteFn);
            if ($error !== null) {
                $finalErrors[] = $error;
            }
        }
    }

    /**
     * Run a single test case on its dedicated stream.
     *
     * @return \Throwable|null The error if the test case failed.
     * @throws \InvalidArgumentException
     */
    private function runTestCase(
        int $caseStreamId,
        \Closure $testFn,
        TestPhase $phase,
        null|\Closure $noteFn,
    ): null|\Throwable {
        $caseStream = $this->connection->connectStream($caseStreamId);
        $tc = new TestCase($caseStream, $phase, $noteFn);

        $outcome = $this->executeTestFn($testFn, $tc);

        if (!$outcome->aborted) {
            try {
                $caseStream->requestCbor(new MarkCompleteCommand(
                    status: $outcome->status,
                    origin: $outcome->origin,
                ));
            } catch (ConnectionException) { // @mago-expect lint:no-empty-catch-clause
            }
        }

        try {
            $caseStream->close();
        } catch (\Throwable) { // @mago-expect lint:no-empty-catch-clause
        }

        return $outcome->error;
    }

    private function executeTestFn(\Closure $testFn, TestCase $tc): TestOutcome
    {
        try {
            $testFn($tc);
            return new TestOutcome('VALID', null, null, false);
        } catch (AssumeRejectedException) {
            return new TestOutcome('INVALID', null, null, false);
        } catch (DataExhaustedException) {
            return new TestOutcome('VALID', null, null, true);
        } catch (\Throwable $e) {
            if ($this->isExpectedTermination($e)) {
                return new TestOutcome('VALID', null, null, true);
            }
            return new TestOutcome('INTERESTING', $this->formatOrigin($e), $e, false);
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
