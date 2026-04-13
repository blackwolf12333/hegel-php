<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Protocol;

use Hegel\Codec\CborCodec;
use Hegel\Exception\ConnectionException;
use Hegel\Protocol\Connection;
use Hegel\Protocol\Stream;
use Hegel\Wire\Packet;
use Hegel\Wire\PacketReader;
use Hegel\Wire\PacketWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionTest extends TestCase
{
    /**
     * @return array{resource, resource}
     */
    private function createSocketPair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        return $pair;
    }

    #[Test]
    public function handshake_sends_ascii_and_receives_version(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();

        // Server side: read handshake request, send handshake reply
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        // Simulate server in a separate process isn't practical in PHP,
        // so we'll test the handshake packet format directly.
        // Write a handshake request from client side
        $stream = $conn->controlStream();
        $msgId = $stream->sendRawRequest('hegel_handshake_start');

        // Read the packet from the other end
        $packet = PacketReader::read($serverSock);
        $this->assertNotNull($packet);
        $this->assertSame(0, $packet->streamId);
        $this->assertSame('hegel_handshake_start', $packet->payload);
        $this->assertFalse($packet->isReply);

        // Server sends back version reply
        PacketWriter::write($serverSock, new Packet(
            streamId: 0,
            messageId: $msgId,
            isReply: true,
            payload: 'Hegel/0.10',
        ));

        $reply = $stream->receiveRawReply($msgId);
        $this->assertSame('Hegel/0.10', $reply);

        fclose($clientSock);
        fclose($serverSock);
    }

    #[Test]
    public function new_stream_assigns_odd_ids(): void
    {
        [$sock, $peer] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($sock, $sock);

        $s1 = $conn->newStream();
        $s2 = $conn->newStream();

        // First client stream: (1 << 1) | 1 = 3
        $this->assertSame(3, $s1->streamId());
        // Second: (2 << 1) | 1 = 5
        $this->assertSame(5, $s2->streamId());

        // Both should be odd
        $this->assertSame(1, $s1->streamId() % 2);
        $this->assertSame(1, $s2->streamId() % 2);

        fclose($sock);
        fclose($peer);
    }

    #[Test]
    public function stream_request_reply_roundtrip(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $stream = $conn->newStream();
        $request = ['command' => 'generate', 'schema' => ['type' => 'integer']];
        $msgId = $stream->sendRequest($request);

        // Read request from server side
        $packet = PacketReader::read($serverSock);
        $this->assertNotNull($packet);
        $decoded = CborCodec::decode($packet->payload);
        assert(is_array($decoded));
        $this->assertSame('generate', $decoded['command']);

        // Server sends reply
        $reply = CborCodec::encode(['result' => 42]);
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: $msgId,
            isReply: true,
            payload: $reply,
        ));

        $result = $stream->receiveReply($msgId);
        $this->assertSame(42, $result);

        fclose($clientSock);
        fclose($serverSock);
    }

    #[Test]
    public function stream_handles_error_reply(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $stream = $conn->newStream();
        $msgId = $stream->sendRequest(['command' => 'generate', 'schema' => ['type' => 'integer']]);

        // Read and discard the request from server
        PacketReader::read($serverSock);

        // Server sends error reply
        $errorReply = CborCodec::encode(['error' => 'Data exhausted', 'type' => 'StopTest']);
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: $msgId,
            isReply: true,
            payload: $errorReply,
        ));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageMatches('/StopTest/');
        $stream->receiveReply($msgId);

        fclose($clientSock);
        fclose($serverSock);
    }

    #[Test]
    public function packets_dispatched_to_correct_stream(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $s1 = $conn->newStream();
        $s2 = $conn->newStream();

        $msg1 = $s1->sendRequest(['command' => 'generate', 'schema' => ['type' => 'boolean']]);
        $msg2 = $s2->sendRequest(['command' => 'generate', 'schema' => ['type' => 'integer']]);

        // Read both from server side
        PacketReader::read($serverSock);
        PacketReader::read($serverSock);

        // Reply to stream 2 first (out of order)
        PacketWriter::write($serverSock, new Packet(
            streamId: $s2->streamId(),
            messageId: $msg2,
            isReply: true,
            payload: CborCodec::encode(['result' => 99]),
        ));

        // Reply to stream 1
        PacketWriter::write($serverSock, new Packet(
            streamId: $s1->streamId(),
            messageId: $msg1,
            isReply: true,
            payload: CborCodec::encode(['result' => true]),
        ));

        // Both should get correct results despite out-of-order delivery
        $result1 = $s1->receiveReply($msg1);
        $result2 = $s2->receiveReply($msg2);

        assert(is_bool($result1));
        $this->assertTrue($result1);
        assert(is_int($result2));
        $this->assertSame(99, $result2);

        fclose($clientSock);
        fclose($serverSock);
    }

    #[Test]
    public function close_stream_sends_close_packet(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $stream = $conn->newStream();
        $streamId = $stream->streamId();
        $stream->close();

        // Read the close packet from server
        $packet = PacketReader::read($serverSock);
        $this->assertNotNull($packet);
        $this->assertTrue($packet->isCloseStream());
        $this->assertSame($streamId, $packet->streamId);

        fclose($clientSock);
        fclose($serverSock);
    }

    #[Test]
    public function connect_stream_allows_receiving_on_server_stream(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        // Server creates even-numbered stream (e.g., 4)
        $serverStreamId = 4;
        $stream = $conn->connectStream($serverStreamId);
        $this->assertSame($serverStreamId, $stream->streamId());

        // Server sends a request on this stream
        PacketWriter::write($serverSock, new Packet(
            streamId: $serverStreamId,
            messageId: 1,
            isReply: false,
            payload: CborCodec::encode(['command' => 'mark_complete', 'status' => 'VALID']),
        ));

        [$requestMsgId, $request] = $stream->receiveRequest();
        assert(is_array($request));
        $this->assertSame(1, $requestMsgId);
        $this->assertSame('mark_complete', $request['command']);

        fclose($clientSock);
        fclose($serverSock);
    }
}
