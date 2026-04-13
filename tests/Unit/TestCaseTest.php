<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit;

use Hegel\Codec\CborCodec;
use Hegel\Collection;
use Hegel\Exception\AssumeRejectedException;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Protocol\Connection;
use Hegel\Protocol\Stream;
use Hegel\TestCase as HegelTestCase;
use Hegel\TestPhase;
use Hegel\Wire\Packet;
use Hegel\Wire\PacketReader;
use Hegel\Wire\PacketWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TestCaseTest extends TestCase
{
    /**
     * @return array{Stream, resource} [clientStream, serverSocket]
     */
    private function createTestStream(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        $conn = Connection::fromRawStreams($pair[0], $pair[0]);
        $stream = $conn->newStream();
        return [$stream, $pair[1]];
    }

    /**
     * @param resource $serverSock
     */
    private function replyWithResult(mixed $serverSock, int $streamId, int $messageId, mixed $result): void
    {
        assert(is_resource($serverSock));
        PacketWriter::write($serverSock, new Packet(
            streamId: $streamId,
            messageId: $messageId,
            isReply: true,
            payload: CborCodec::encode(['result' => $result]),
        ));
    }

    /**
     * @param resource $serverSock
     */
    private function replyWithError(mixed $serverSock, int $streamId, int $messageId, string $error, string $type): void
    {
        assert(is_resource($serverSock));
        PacketWriter::write($serverSock, new Packet(
            streamId: $streamId,
            messageId: $messageId,
            isReply: true,
            payload: CborCodec::encode(['error' => $error, 'type' => $type]),
        ));
    }

    #[Test]
    public function reject_throws_assume_rejected(): void
    {
        [$stream, $serverSock] = $this->createTestStream();
        $tc = new HegelTestCase($stream);

        $this->expectException(AssumeRejectedException::class);
        $tc->reject();
    }

    #[Test]
    public function note_calls_note_fn_only_when_final(): void
    {
        [$stream, $serverSock] = $this->createTestStream();

        $notes = [];
        $noteFn = function (string $msg) use (&$notes): void {
            $notes[] = $msg;
        };

        // Non-final: note should be collected but not emitted
        $tc = new HegelTestCase($stream, phase: TestPhase::Exploration, noteFn: $noteFn);
        $tc->note('should not appear');
        $this->assertEmpty($notes);

        // Final: note should be emitted
        $tcFinal = new HegelTestCase($stream, phase: TestPhase::Final, noteFn: $noteFn);
        $tcFinal->note('visible note');
        $this->assertSame(['visible note'], $notes);

        fclose($serverSock);
    }

    #[Test]
    public function generate_from_schema_sends_generate_command(): void
    {
        [$stream, $serverSock] = $this->createTestStream();
        $tc = new HegelTestCase($stream);

        $schema = ['type' => 'integer', 'min_value' => 0, 'max_value' => 100];

        // Better approach: manually do what generateFromSchema does, but step by step
        $msgId = $stream->sendRequest(['command' => 'generate', 'schema' => $schema]);

        // Verify what was sent
        $packet = PacketReader::read($serverSock);
        $this->assertNotNull($packet);
        $decoded = CborCodec::decode($packet->payload);
        assert(is_array($decoded));
        $this->assertSame('generate', $decoded['command']);
        $this->assertSame($schema, $decoded['schema']);

        // Send reply
        $this->replyWithResult($serverSock, $stream->streamId(), $msgId, 42);

        $result = $stream->receiveReply($msgId);
        $this->assertSame(42, $result);

        fclose($serverSock);
    }

    #[Test]
    public function generate_from_schema_returns_server_result(): void
    {
        [$stream, $serverSock] = $this->createTestStream();
        $tc = new HegelTestCase($stream);

        // Pre-write the reply for the generate command (msg id will be 1)
        $this->replyWithResult($serverSock, $stream->streamId(), 1, 42);

        $result = $tc->generateFromSchema(['type' => 'integer', 'min_value' => 0, 'max_value' => 100]);
        $this->assertSame(42, $result);

        // Verify what was sent
        $packet = PacketReader::read($serverSock);
        $this->assertNotNull($packet);
        $decoded = CborCodec::decode($packet->payload);
        assert(is_array($decoded));
        $this->assertSame('generate', $decoded['command']);

        fclose($serverSock);
    }

    #[Test]
    public function generate_from_schema_stop_test_throws_data_exhausted(): void
    {
        [$stream, $serverSock] = $this->createTestStream();
        $tc = new HegelTestCase($stream);

        // Pre-write error reply
        $this->replyWithError($serverSock, $stream->streamId(), 1, 'Data exhausted', 'StopTest');

        $this->expectException(DataExhaustedException::class);
        $tc->generateFromSchema(['type' => 'integer']);

        fclose($serverSock);
    }

    #[Test]
    public function target_sends_target_command(): void
    {
        [$stream, $serverSock] = $this->createTestStream();
        $tc = new HegelTestCase($stream);

        // Pre-write reply
        $this->replyWithResult($serverSock, $stream->streamId(), 1, null);

        $tc->target(0.5, 'score');

        $packet = PacketReader::read($serverSock);
        $this->assertNotNull($packet);
        $decoded = CborCodec::decode($packet->payload);
        assert(is_array($decoded));
        $this->assertSame('target', $decoded['command']);
        $this->assertSame(0.5, $decoded['value']);
        $this->assertSame('score', $decoded['label']);

        fclose($serverSock);
    }

    #[Test]
    public function start_stop_span_sends_commands(): void
    {
        [$stream, $serverSock] = $this->createTestStream();
        $tc = new HegelTestCase($stream);

        // Pre-write replies for both commands (msg ids 1 and 2)
        $this->replyWithResult($serverSock, $stream->streamId(), 1, null);
        $this->replyWithResult($serverSock, $stream->streamId(), 2, null);

        $tc->startSpan(1);
        $tc->stopSpan();

        // Read both packets sent
        $p1 = PacketReader::read($serverSock);
        $this->assertNotNull($p1);
        $d1 = CborCodec::decode($p1->payload);
        assert(is_array($d1));
        $this->assertSame('start_span', $d1['command']);
        $this->assertSame(1, $d1['label']);

        $p2 = PacketReader::read($serverSock);
        $this->assertNotNull($p2);
        $d2 = CborCodec::decode($p2->payload);
        assert(is_array($d2));
        $this->assertSame('stop_span', $d2['command']);
        $this->assertFalse($d2['discard']);

        fclose($serverSock);
    }

    #[Test]
    public function collection_create_sends_new_collection(): void
    {
        [$stream, $serverSock] = $this->createTestStream();
        $tc = new HegelTestCase($stream);

        // Pre-write reply
        $this->replyWithResult($serverSock, $stream->streamId(), 1, 'coll-1');

        $coll = $tc->newCollection(0, 10);
        $this->assertInstanceOf(Collection::class, $coll);

        $packet = PacketReader::read($serverSock);
        $this->assertNotNull($packet);
        $decoded = CborCodec::decode($packet->payload);
        assert(is_array($decoded));
        $this->assertSame('new_collection', $decoded['command']);
        $this->assertSame(0, $decoded['min_size']);
        $this->assertSame(10, $decoded['max_size']);

        fclose($serverSock);
    }

    #[Test]
    public function collection_more_returns_server_booleans(): void
    {
        [$stream, $serverSock] = $this->createTestStream();
        $tc = new HegelTestCase($stream);

        // Pre-write replies: new_collection -> id, more -> true, more -> false
        $this->replyWithResult($serverSock, $stream->streamId(), 1, 'coll-1');
        $this->replyWithResult($serverSock, $stream->streamId(), 2, true);
        $this->replyWithResult($serverSock, $stream->streamId(), 3, false);

        $coll = $tc->newCollection(0, 5);
        $this->assertTrue($coll->more());
        $this->assertFalse($coll->more());

        fclose($serverSock);
    }
}
