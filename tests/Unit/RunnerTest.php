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
    private function reply(mixed $sock, int $streamId, int $messageId, mixed $payload): void
    {
        PacketWriter::write($sock, new Packet(
            streamId: $streamId,
            messageId: $messageId,
            isReply: true,
            payload: CborCodec::encode($payload),
        ));
    }

    private function serverRequest(mixed $sock, int $streamId, int $messageId, mixed $payload): void
    {
        PacketWriter::write($sock, new Packet(
            streamId: $streamId,
            messageId: $messageId,
            isReply: false,
            payload: CborCodec::encode($payload),
        ));
    }

    /**
     * Read all available packets from a socket (with a short timeout).
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
     * @return list<array<string,mixed>>
     */
    private function findMarkComplete(array $packets, int $streamId): array
    {
        $results = [];
        foreach ($packets as $p) {
            if ($p->streamId === $streamId && !$p->isReply && !$p->isCloseStream()) {
                try {
                    $decoded = CborCodec::decode($p->payload);
                    if (is_array($decoded) && ($decoded['command'] ?? '') === 'mark_complete') {
                        $results[] = $decoded;
                    }
                } catch (\Throwable $e) {
                    error_log('[test] skipping non-decodable packet: ' . $e->getMessage());
                }
            }
        }
        return $results;
    }

    #[Test]
    public function runner_valid_test_sends_mark_complete_valid(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
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
            testFn: function (HegelTestCase $tc): void {
                $n = $tc->generateFromSchema(['type' => 'integer', 'min_value' => 0, 'max_value' => 100]);
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

    #[Test]
    public function runner_assertion_failure_sends_mark_complete_interesting(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
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
            testFn: function (HegelTestCase $tc): void {
                $n = $tc->generateFromSchema(['type' => 'integer', 'min_value' => 0, 'max_value' => 100]);
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

    #[Test]
    public function runner_assume_rejected_sends_mark_complete_invalid(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
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
            testFn: function (HegelTestCase $tc): void {
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

    #[Test]
    public function runner_handles_health_check_failure(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
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
            testFn: fn(HegelTestCase $tc): mixed => null,
            testCases: 100,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('filter_too_much', $result->healthCheckFailure);

        fclose($clientSock);
        fclose($serverSock);
    }

    #[Test]
    public function runner_handles_flaky_detection(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
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
            testFn: fn(HegelTestCase $tc): mixed => null,
            testCases: 10,
        );

        $this->assertFalse($result->passed);
        $this->assertSame('Test gave inconsistent results', $result->flaky);

        fclose($clientSock);
        fclose($serverSock);
    }
}
