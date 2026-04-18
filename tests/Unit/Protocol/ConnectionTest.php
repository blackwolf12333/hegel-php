<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Protocol;

use Hegel\Codec\CborCodec;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\ProtocolException;
use Hegel\Protocol\Connection;
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

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \InvalidArgumentException
     */
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
        /** @var array{command: string} $decoded */
        $decoded = CborCodec::decode($packet->payload);
        $this->assertSame('generate', $decoded['command']);

        // Server sends reply
        $reply = CborCodec::encode(['result' => 42]);
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: $msgId,
            isReply: true,
            payload: $reply,
        ));

        /** @var mixed $result */
        $result = $stream->receiveReply($msgId);
        $this->assertSame(42, $result);

        fclose($clientSock);
        fclose($serverSock);
    }

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
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

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \InvalidArgumentException
     */
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
        /** @var mixed $result1 */
        $result1 = $s1->receiveReply($msg1);
        /** @var mixed $result2 */
        $result2 = $s2->receiveReply($msg2);

        assert(is_bool($result1), 'Stream 1 result must be a boolean');
        $this->assertTrue($result1);
        assert(is_int($result2), 'Stream 2 result must be an integer');
        $this->assertSame(99, $result2);

        fclose($clientSock);
        fclose($serverSock);
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function control_stream_has_id_zero(): void
    {
        [$sock, $peer] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($sock, $sock);

        $controlStream = $conn->controlStream();

        $this->assertSame(0, $controlStream->streamId());

        fclose($sock);
        fclose($peer);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
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

        $received = $stream->receiveRequest();
        $requestMsgId = $received[0];
        /** @var array{command: string} $request */
        $request = $received[1];
        $this->assertSame(1, $requestMsgId);
        $this->assertSame('mark_complete', $request['command']);

        fclose($clientSock);
        fclose($serverSock);
    }

    // --- Mutant: $this->streams[0] → $this->streams[1] — control stream must be at key 0 ---

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\ProtocolException
     */
    #[Test]
    public function packet_for_stream_zero_is_dispatched_to_control_stream(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $controlStream = $conn->controlStream();

        // Server sends a reply packet addressed to stream 0 (the control stream)
        PacketWriter::write($serverSock, new Packet(
            streamId: 0,
            messageId: 1,
            isReply: true,
            payload: 'control-reply',
        ));

        // dispatchPacket should route the packet to stream 0
        $packet = $conn->readPacket();
        $this->assertNotNull($packet);
        $conn->dispatchPacket($packet);

        // The control stream must now have the buffered reply available
        $reply = $controlStream->receiveRawReply(1);
        $this->assertSame('control-reply', $reply);

        fclose($clientSock);
        fclose($serverSock);
    }

    // --- Mutant 65: receiveRawReply && -> || (non-reply packet before correct reply) ---

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function receive_raw_reply_skips_non_reply_packets_and_returns_correct_reply(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $stream = $conn->newStream();
        $msgId = $stream->sendRawRequest('request-payload');

        // Read the request from server side so the socket buffer is free
        PacketReader::read($serverSock);

        // Server sends a non-reply packet first (isReply=false, same stream, same msgId)
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: $msgId,
            isReply: false,
            payload: 'not-a-reply',
        ));

        // Then sends the actual reply
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: $msgId,
            isReply: true,
            payload: 'correct-reply',
        ));

        $reply = $stream->receiveRawReply($msgId);

        // With the && -> || mutation, the non-reply would be returned instead.
        $this->assertSame('correct-reply', $reply);

        fclose($clientSock);
        fclose($serverSock);
    }

    // --- Mutant: return removal in bufferPacket for replies ---
    // When a reply is buffered, it must NOT also be added to the requests queue.
    // If the return is removed, the reply would be stored both as a response AND
    // appended to the requests list, so receiveRequest() would incorrectly dequeue it.

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\ProtocolException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function buffer_packet_reply_does_not_also_enqueue_as_request(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $stream = $conn->newStream();
        $msgId = $stream->sendRawRequest('req');

        // Drain the outbound request
        PacketReader::read($serverSock);

        // Server sends a reply
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: $msgId,
            isReply: true,
            payload: 'the-reply',
        ));

        // Manually read and buffer the packet via dispatchPacket (which calls bufferPacket)
        $packet = $conn->readPacket();
        $this->assertNotNull($packet);
        $conn->dispatchPacket($packet);

        // The reply must be retrievable via receiveRawReply
        $reply = $stream->receiveRawReply($msgId);
        $this->assertSame('the-reply', $reply);

        // Now send a non-reply (request) packet and verify receiveRequest gets it, not the stale reply
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: 99,
            isReply: false,
            payload: 'actual-request',
        ));

        $received = $stream->receiveRequest();
        $receivedMsgId = $received[0];
        /** @var mixed $receivedPayload */
        $receivedPayload = $received[1];
        $this->assertSame(99, $receivedMsgId);
        // The payload is raw bytes decoded by CborCodec — send a CBOR-encoded value so it roundtrips
        // We verify that the *request* (msgId=99) is returned, not the already-consumed reply
        $this->assertNotSame($msgId, $receivedMsgId, 'receiveRequest must not return the already-buffered reply');

        fclose($clientSock);
        fclose($serverSock);
    }

    // --- Mutant 66: bufferPacket return removal for replies ---

    /**
     * @throws \Hegel\Exception\ConnectionException|ProtocolException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function buffered_replies_are_retrieved_on_next_receive_raw_reply(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $stream = $conn->newStream();
        $msg1 = $stream->sendRawRequest('req1');
        $msg2 = $stream->sendRawRequest('req2');

        // Drain both requests from server side
        PacketReader::read($serverSock);
        PacketReader::read($serverSock);

        // Server sends reply for msg2 first, then msg1
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: $msg2,
            isReply: true,
            payload: 'reply-for-2',
        ));
        PacketWriter::write($serverSock, new Packet(
            streamId: $stream->streamId(),
            messageId: $msg1,
            isReply: true,
            payload: 'reply-for-1',
        ));

        // Requesting msg1 first should buffer msg2 and return msg1's reply
        $reply1 = $stream->receiveRawReply($msg1);
        $this->assertSame('reply-for-1', $reply1);

        // msg2 was buffered; we should get it without reading from the socket
        $reply2 = $stream->receiveRawReply($msg2);
        $this->assertSame('reply-for-2', $reply2);

        fclose($clientSock);
        fclose($serverSock);
    }

    // --- Mutant 67: $this->closed = true -> false in close() ---

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function close_sets_is_closed_to_true(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $stream = $conn->newStream();
        $this->assertFalse($stream->isClosed());

        $stream->close();

        $this->assertTrue($stream->isClosed());

        fclose($clientSock);
        fclose($serverSock);
    }

    // --- Mutant: unregisterStream removal from close() ---

    /**
     * @throws \Hegel\Exception\ProtocolException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function close_unregisters_stream_so_dispatch_throws_for_closed_stream(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        $stream = $conn->newStream();
        $streamId = $stream->streamId();

        $stream->close();

        // Drain the close packet from server side
        PacketReader::read($serverSock);

        // Dispatching a packet for the closed (unregistered) stream must throw.
        // If unregisterStream was not called (mutant), it would be silently buffered instead.
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage("closed stream {$streamId}");

        $conn->dispatchPacket(new Packet(
            streamId: $streamId,
            messageId: 1,
            isReply: true,
            payload: 'orphan-payload',
        ));

        fclose($clientSock);
        fclose($serverSock);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\ProtocolException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function dispatch_buffers_packets_for_not_yet_connected_streams(): void
    {
        [$clientSock, $serverSock] = $this->createSocketPair();
        $conn = Connection::fromRawStreams($clientSock, $clientSock);

        // Dispatch a packet for stream 4 before it's connected
        $conn->dispatchPacket(new Packet(
            streamId: 4,
            messageId: 1,
            isReply: false,
            payload: CborCodec::encode(['event' => 'test_case']),
        ));

        // Now connect the stream — pending packets should be flushed
        $stream = $conn->connectStream(4);
        $received = $stream->receiveRequest();
        $this->assertSame(1, $received[0]);

        fclose($clientSock);
        fclose($serverSock);
    }
}
