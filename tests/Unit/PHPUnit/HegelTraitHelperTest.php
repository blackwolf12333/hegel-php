<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\PHPUnit;

use Hegel\Codec\CborCodec;
use Hegel\PHPUnit\HegelTraitHelper;
use Hegel\PHPUnit\Property;
use Hegel\Protocol\Connection;
use Hegel\Wire\Packet;
use Hegel\Wire\PacketWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HegelTraitHelperTest extends TestCase
{
    /**
     * @param resource $sock
     */
    private function reply(mixed $sock, int $streamId, int $messageId, mixed $payload): void
    {
        assert(is_resource($sock), 'Expected a resource for socket');
        PacketWriter::write($sock, new Packet(
            streamId: $streamId,
            messageId: $messageId,
            isReply: true,
            payload: CborCodec::encode($payload),
        ));
    }

    /**
     * @param resource $sock
     */
    private function serverRequest(mixed $sock, int $streamId, int $messageId, mixed $payload): void
    {
        assert(is_resource($sock), 'Expected a resource for socket');
        PacketWriter::write($sock, new Packet(
            streamId: $streamId,
            messageId: $messageId,
            isReply: false,
            payload: CborCodec::encode($payload),
        ));
    }

    /**
     * Build a socket-pair Connection and pre-write a complete successful run
     * (no test cases, just an immediate test_done passed=true).
     *
     * @return array{Connection, resource} [connection, serverSock]
     */
    private function makePassingConnection(int $testStreamId = 3): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_done',
            'results' => [
                'passed' => true,
                'test_cases' => 1,
                'valid_test_cases' => 1,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => '0',
            ],
        ]);

        return [$conn, $serverSock];
    }

    // Mutant 43: when a connection is provided it must be used, not Session::global()
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\FlakyTestException
     * @throws \LogicException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Throwable
     */
    #[Test]
    public function run_property_test_uses_provided_connection(): void
    {
        [$conn, $serverSock] = $this->makePassingConnection();

        $caller = new HegelTraitHelperStub('myTest');

        // If the provided $connection is used, the pre-written server packets are
        // consumed correctly. If Session::global() were used instead, it would try
        // to launch a real server process and fail in a unit-test environment.
        HegelTraitHelper::runPropertyTest(
            caller: $caller,
            methodName: 'myTest',
            testArguments: [],
            property: new Property(testCases: 1),
            connection: $conn,
        );

        // Reaching here without exception means the provided connection was used
        $this->assertTrue(true);

        fclose($serverSock);
    }

    // Mutant 46: handleResult must be called (assertion count increases)
    // Mutants 49-51: addToAssertionCount(1) — must increase by exactly 1
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\FlakyTestException
     * @throws \LogicException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Throwable
     */
    #[Test]
    public function run_property_test_increments_assertion_count_by_one_on_pass(): void
    {
        [$conn, $serverSock] = $this->makePassingConnection();

        $caller = new HegelTraitHelperStub('myTest');

        $before = $caller->numberOfAssertionsPerformed();

        HegelTraitHelper::runPropertyTest(
            caller: $caller,
            methodName: 'myTest',
            testArguments: [],
            property: new Property(testCases: 1),
            connection: $conn,
        );

        $after = $caller->numberOfAssertionsPerformed();
        $this->assertSame(1, $after - $before, 'Assertion count must increase by exactly 1 for a passing property');

        fclose($serverSock);
    }

    // Mutants 47-48: failing property with finalErrors must rethrow the first error
    /**
     * @throws \Hegel\Exception\FlakyTestException
     * @throws \LogicException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Throwable
     */
    #[Test]
    public function run_property_test_rethrows_first_final_error_on_failure(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;
        $caseStreamId = 4;

        $this->reply($serverSock, 0, 1, true);
        // Exploration test_case
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_case',
            'stream_id' => $caseStreamId,
            'is_final' => false,
        ]);
        // Reply for mark_complete on caseStream
        $this->reply($serverSock, $caseStreamId, 1, ['result' => null]);
        // test_done with 1 interesting case
        $this->serverRequest($serverSock, $testStreamId, 2, [
            'event' => 'test_done',
            'results' => [
                'passed' => false,
                'test_cases' => 1,
                'valid_test_cases' => 0,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 1,
                'seed' => '42',
            ],
        ]);
        // Replay (final) test_case
        $finalCaseStreamId = 6;
        $this->serverRequest($serverSock, $testStreamId, 3, [
            'event' => 'test_case',
            'stream_id' => $finalCaseStreamId,
            'is_final' => true,
        ]);
        // Reply for mark_complete on finalCaseStream
        $this->reply($serverSock, $finalCaseStreamId, 1, ['result' => null]);

        $caller = new HegelTraitHelperFailStub('myTest');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('property violated');

        HegelTraitHelper::runPropertyTest(
            caller: $caller,
            methodName: 'myTest',
            testArguments: [],
            property: new Property(testCases: 1),
            connection: $conn,
        );

        fclose($serverSock);
    }

    // Mutants 47-48 (other branch): failing property with NO finalErrors must call $testCase->fail()
    /**
     * @throws \Hegel\Exception\FlakyTestException
     * @throws \LogicException
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \Throwable
     */
    #[Test]
    public function run_property_test_calls_fail_when_not_passed_and_no_final_errors(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;

        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_done',
            'results' => [
                'passed' => false,
                'test_cases' => 1,
                'valid_test_cases' => 1,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => '99',
            ],
        ]);

        $caller = new HegelTraitHelperStub('myTest');

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessage('Property test failed');

        HegelTraitHelper::runPropertyTest(
            caller: $caller,
            methodName: 'myTest',
            testArguments: [],
            property: new Property(testCases: 1),
            connection: $conn,
        );

        fclose($serverSock);
    }

    // Mutant: concat operand removal — seed value must appear in failure message
    // The mutant produces 'Property test failed (seed: )' without the actual seed.
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\FlakyTestException
     * @throws \LogicException
     * @throws \Throwable
     */
    #[Test]
    public function run_property_test_failure_message_includes_seed_value(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;
        $expectedSeed = '12345678';

        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_done',
            'results' => [
                'passed' => false,
                'test_cases' => 1,
                'valid_test_cases' => 1,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => $expectedSeed,
            ],
        ]);

        $caller = new HegelTraitHelperStub('myTest');

        try {
            HegelTraitHelper::runPropertyTest(
                caller: $caller,
                methodName: 'myTest',
                testArguments: [],
                property: new Property(testCases: 1),
                connection: $conn,
            );
            $this->fail('Expected AssertionFailedError was not thrown');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            // The message must contain the actual seed value, not just an empty placeholder
            $this->assertStringContainsString($expectedSeed, $e->getMessage(),
                'Failure message must include the seed value');
            $this->assertStringContainsString('Property test failed (seed: ' . $expectedSeed . ')', $e->getMessage(),
                'Failure message must have the full "Property test failed (seed: <value>)" format');
        }

        fclose($serverSock);
    }
}
