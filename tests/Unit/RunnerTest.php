<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit;

use Hegel\Codec\CborCodec;
use Hegel\Protocol\Connection;
use Hegel\Runner;
use Hegel\TestCase as HegelTestCase;
use Hegel\Wire\Packet;
use Hegel\Wire\PacketReader;
use Hegel\Wire\PacketWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
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
     * Read all available packets from a socket (with a short timeout).
     * @param resource $sock
     * @return list<Packet>
     */
    private function drainPackets(mixed $sock): array
    {
        stream_set_timeout($sock, 0, 100_000); // 100ms timeout
        $packets = [];
        while (true) {
            try {
                $p = PacketReader::read($sock);
                if ($p === null) {
                    break;
                }
                $packets[] = $p;
            } catch (\Throwable) {
                break;
            }
        }
        return $packets;
    }

    /**
     * Find mark_complete commands in a list of packets for a given stream.
     * @param list<Packet> $packets
     * @return list<array{command: string, status: string}>
     */
    private function findMarkComplete(array $packets, int $streamId): array
    {
        $results = [];
        foreach ($packets as $p) {
            if ($p->streamId !== $streamId || $p->isReply || $p->isCloseStream()) {
                continue;
            }
            try {
                /** @var mixed $decoded */
                $decoded = CborCodec::decode($p->payload);
                if (is_array($decoded) && ($decoded['command'] ?? '') === 'mark_complete') {
                    /** @var array{command: string, status: string} $decoded */
                    $results[] = $decoded;
                }
            } catch (\Throwable $e) {
                error_log('[test] skipping non-decodable packet: ' . $e->getMessage());
            }
        }
        return $results;
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_valid_test_sends_mark_complete_valid(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;
        $caseStreamId = 4;

        // Pre-write all server responses
        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_case',
            'stream_id' => $caseStreamId,
            'is_final' => false,
        ]);
        $this->reply($serverSock, $caseStreamId, 1, ['result' => 42]);
        $this->reply($serverSock, $caseStreamId, 2, ['result' => null]);
        $this->serverRequest($serverSock, $testStreamId, 2, [
            'event' => 'test_done',
            'results' => [
                'passed' => true,
                'test_cases' => 1,
                'valid_test_cases' => 1,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => '12345',
            ],
        ]);

        $runner = new Runner($conn);
        $result = $runner->run(
            testFn: static function (HegelTestCase $tc): void {
                $tc->generateFromSchema(['type' => 'integer', 'min_value' => 0, 'max_value' => 100]);
            },
            testCases: 1,
        );

        $this->assertTrue($result->passed);
        $this->assertSame(1, $result->testCases);
        $this->assertEmpty($result->finalErrors);

        // Verify mark_complete was VALID
        fclose($clientSock); // EOF for drainPackets
        $packets = $this->drainPackets($serverSock);
        $mc = $this->findMarkComplete($packets, $caseStreamId);
        $this->assertCount(1, $mc);
        $this->assertSame('VALID', $mc[0]['status']);

        fclose($serverSock);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_assertion_failure_sends_mark_complete_interesting(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;
        $caseStreamId = 4;
        $finalCaseStreamId = 6;

        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_case',
            'stream_id' => $caseStreamId,
            'is_final' => false,
        ]);
        $this->reply($serverSock, $caseStreamId, 1, ['result' => 51]);
        $this->reply($serverSock, $caseStreamId, 2, ['result' => null]);
        $this->serverRequest($serverSock, $testStreamId, 2, [
            'event' => 'test_done',
            'results' => [
                'passed' => false,
                'test_cases' => 1,
                'valid_test_cases' => 0,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 1,
                'seed' => '99999',
            ],
        ]);
        $this->serverRequest($serverSock, $testStreamId, 3, [
            'event' => 'test_case',
            'stream_id' => $finalCaseStreamId,
            'is_final' => true,
        ]);
        $this->reply($serverSock, $finalCaseStreamId, 1, ['result' => 50]);
        $this->reply($serverSock, $finalCaseStreamId, 2, ['result' => null]);

        $runner = new Runner($conn);
        $result = $runner->run(
            testFn: static function (HegelTestCase $tc): void {
                $n = (int) $tc->generateFromSchema(['type' => 'integer', 'min_value' => 0, 'max_value' => 100]);
                if ($n >= 50) {
                    throw new \RuntimeException("Value {$n} is >= 50");
                }
            },
            testCases: 1,
        );

        $this->assertFalse($result->passed);
        $this->assertCount(1, $result->finalErrors);
        $this->assertStringContainsString('>= 50', $result->finalErrors[0]->getMessage());

        fclose($clientSock);
        $packets = $this->drainPackets($serverSock);
        $mc = $this->findMarkComplete($packets, $caseStreamId);
        $this->assertNotEmpty($mc);
        $this->assertContains('INTERESTING', array_column($mc, 'status'));

        fclose($serverSock);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_assume_rejected_sends_mark_complete_invalid(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;
        $caseStreamId = 4;

        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_case',
            'stream_id' => $caseStreamId,
            'is_final' => false,
        ]);
        $this->reply($serverSock, $caseStreamId, 1, ['result' => null]); // mark_complete reply
        $this->serverRequest($serverSock, $testStreamId, 2, [
            'event' => 'test_done',
            'results' => [
                'passed' => true,
                'test_cases' => 1,
                'valid_test_cases' => 0,
                'invalid_test_cases' => 1,
                'interesting_test_cases' => 0,
                'seed' => '111',
            ],
        ]);

        $runner = new Runner($conn);
        $result = $runner->run(
            testFn: static function (HegelTestCase $tc): void {
                $tc->reject();
            },
            testCases: 1,
        );

        $this->assertTrue($result->passed);

        fclose($clientSock);
        $packets = $this->drainPackets($serverSock);
        $mc = $this->findMarkComplete($packets, $caseStreamId);
        $this->assertCount(1, $mc);
        $this->assertSame('INVALID', $mc[0]['status']);

        fclose($serverSock);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_handles_health_check_failure(): void
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
                'test_cases' => 0,
                'valid_test_cases' => 0,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => '0',
                'health_check_failure' => 'filter_too_much',
            ],
        ]);

        $runner = new Runner($conn);
        $result = $runner->run(
            testFn: static fn(HegelTestCase $_tc): mixed => null,
            testCases: 100,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('filter_too_much', $result->healthCheckFailure);

        fclose($clientSock);
        fclose($serverSock);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_handles_flaky_detection(): void
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
                'test_cases' => 10,
                'valid_test_cases' => 9,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => '42',
                'flaky' => 'Test gave inconsistent results',
            ],
        ]);

        $runner = new Runner($conn);
        $result = $runner->run(
            testFn: static fn(HegelTestCase $_tc): mixed => null,
            testCases: 10,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('Test gave inconsistent results', $result->flaky);

        fclose($clientSock);
        fclose($serverSock);
    }

    // Mutants 69-70: default $testCases = 100
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_default_test_cases_is_100(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;

        // ctrl reply for run_test command, then immediate test_done
        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_done',
            'results' => [
                'passed' => true,
                'test_cases' => 100,
                'valid_test_cases' => 100,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => '0',
            ],
        ]);

        $runner = new Runner($conn);
        // Call run() without specifying testCases to exercise the default
        $runner->run(testFn: static fn(HegelTestCase $_tc): mixed => null);

        fclose($clientSock);
        $packets = $this->drainPackets($serverSock);

        // Find the run_test command on the control stream (streamId 0)
        $runTestPackets = array_filter($packets, static fn(Packet $p): bool => $p->streamId === 0 && !$p->isReply && !$p->isCloseStream());

        $this->assertNotEmpty($runTestPackets, 'Expected a run_test command on control stream');

        /** @var array{command: string, test_cases: int} $decoded */
        $decoded = CborCodec::decode(array_values($runTestPackets)[0]->payload);
        $this->assertSame('run_test', $decoded['command']);
        $this->assertSame(100, $decoded['test_cases']);

        fclose($serverSock);
    }

    // Mutants 71-72: test_done reply value
    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_sends_result_key_in_test_done_reply(): void
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
                'passed' => true,
                'test_cases' => 1,
                'valid_test_cases' => 1,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => '0',
            ],
        ]);

        $runner = new Runner($conn);
        $runner->run(testFn: static fn(HegelTestCase $_tc): mixed => null, testCases: 1);

        fclose($clientSock);
        $packets = $this->drainPackets($serverSock);

        // Find reply on testStreamId (the test_done ack)
        $replies = array_filter($packets, static fn(Packet $p): bool => $p->streamId === $testStreamId && $p->isReply);

        $this->assertNotEmpty($replies, 'Expected a reply for test_done on the test stream');

        // Stream::sendReply() wraps the value in {"result": value}, so the wire payload is
        // {"result": {"result": true}}. Decoding gives the outer map; the inner map is the actual reply.
        /** @var array{result: array{result: bool}} $decoded */
        $decoded = CborCodec::decode(array_values($replies)[0]->payload);
        $this->assertArrayHasKey('result', $decoded, 'outer CBOR wrapper must have result key');
        $inner = $decoded['result'];
        $this->assertArrayHasKey('result', $inner, 'test_done reply must contain result key');
        $this->assertTrue($inner['result'], 'test_done reply result must be true');

        fclose($serverSock);
    }

    // Mutant 73: $testStream->close() after event loop
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_closes_test_stream_after_event_loop(): void
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
                'passed' => true,
                'test_cases' => 0,
                'valid_test_cases' => 0,
                'invalid_test_cases' => 0,
                'interesting_test_cases' => 0,
                'seed' => '0',
            ],
        ]);

        $runner = new Runner($conn);
        $runner->run(testFn: static fn(HegelTestCase $_tc): mixed => null, testCases: 1);

        fclose($clientSock);
        $packets = $this->drainPackets($serverSock);

        $closePackets = array_filter($packets, static fn(Packet $p): bool => $p->streamId === $testStreamId && $p->isCloseStream());

        $this->assertNotEmpty($closePackets, 'Runner must send a close-stream packet for the test stream');

        fclose($serverSock);
    }

    // Mutants 74-75: test_case acknowledgement reply format
    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_sends_result_key_in_test_case_acknowledgement(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;
        $caseStreamId = 4;

        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_case',
            'stream_id' => $caseStreamId,
            'is_final' => false,
        ]);
        $this->reply($serverSock, $caseStreamId, 1, ['result' => null]); // mark_complete reply
        $this->serverRequest($serverSock, $testStreamId, 2, [
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

        $runner = new Runner($conn);
        $runner->run(testFn: static fn(HegelTestCase $_tc): mixed => null, testCases: 1);

        fclose($clientSock);
        $packets = $this->drainPackets($serverSock);

        // Find replies on the test stream (test_case ack has msgId=1, test_done ack has msgId=2)
        $testStreamReplies = array_values(array_filter($packets, static fn(Packet $p): bool => $p->streamId === $testStreamId && $p->isReply));

        // The first reply on testStreamId should be the test_case ack
        $this->assertNotEmpty($testStreamReplies, 'Expected a reply for test_case on the test stream');

        /** @var array{result: null} $decoded */
        $decoded = CborCodec::decode($testStreamReplies[0]->payload);
        $this->assertArrayHasKey('result', $decoded, 'outer CBOR wrapper must have result key');
        $this->assertNull($decoded['result'], 'test_case ack result must be null');

        fclose($serverSock);
    }

    // Mutant 76: $caseStream->close() in runTestCase
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_closes_case_stream_after_test_case(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;
        $caseStreamId = 4;

        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_case',
            'stream_id' => $caseStreamId,
            'is_final' => false,
        ]);
        $this->reply($serverSock, $caseStreamId, 1, ['result' => null]); // mark_complete reply
        $this->serverRequest($serverSock, $testStreamId, 2, [
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

        $runner = new Runner($conn);
        $runner->run(testFn: static fn(HegelTestCase $_tc): mixed => null, testCases: 1);

        fclose($clientSock);
        $packets = $this->drainPackets($serverSock);

        $closePackets = array_filter($packets, static fn(Packet $p): bool => $p->streamId === $caseStreamId && $p->isCloseStream());

        $this->assertNotEmpty($closePackets, 'Runner must send a close-stream packet for each case stream');

        fclose($serverSock);
    }

    // Mutant 77: expected termination ConnectionException treated as VALID, not INTERESTING
    // We test this by throwing a ConnectionException with an expected-termination type directly
    // from the test function (bypassing generateFromSchema which converts StopTest to DataExhaustedException).
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function runner_treats_expected_termination_connection_exception_as_valid(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $testStreamId = 3;
        $caseStreamId = 4;

        $this->reply($serverSock, 0, 1, true);
        $this->serverRequest($serverSock, $testStreamId, 1, [
            'event' => 'test_case',
            'stream_id' => $caseStreamId,
            'is_final' => false,
        ]);
        $this->serverRequest($serverSock, $testStreamId, 2, [
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

        $runner = new Runner($conn);
        $result = $runner->run(
            testFn: static function (HegelTestCase $_tc): void {
                // Throw a ConnectionException with an expected-termination server error type directly.
                // This goes through the isExpectedTermination() branch in executeTestFn.
                throw new \Hegel\Exception\ConnectionException(
                    message: 'expected termination',
                    serverErrorType: \Hegel\Exception\ServerErrorType::Overflow,
                );
            },
            testCases: 1,
        );

        // The ConnectionException with expected termination should produce VALID (aborted), not INTERESTING
        $this->assertEmpty($result->finalErrors, 'Expected termination should not produce final errors');
        $this->assertTrue($result->passed);

        fclose($clientSock);
        fclose($serverSock);
    }
}
