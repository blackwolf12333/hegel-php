<?php

declare(strict_types=1);

namespace Hegel\Protocol;

use Hegel\Exception\ConnectionException;
use Hegel\Wire\Packet;
use Hegel\Wire\PacketReader;
use Hegel\Wire\PacketWriter;

final class Connection
{
    /** @var int Next stream counter (streams are (counter << 1) | 1) */
    private int $nextStreamCounter = 1;

    /** @var array<int, Stream> Registered streams keyed by stream ID */
    private array $streams = [];

    private Stream $controlStream;

    /**
     * @param resource $reader
     * @param resource $writer
     */
    private function __construct(
        private mixed $reader,
        private mixed $writer,
    ) {
        $this->controlStream = new Stream(0, $this);
        $this->streams[0] = $this->controlStream;
    }

    /**
     * Create a connection with handshake.
     *
     * @param resource $reader Stream resource to read packets from
     * @param resource $writer Stream resource to write packets to
     */
    public static function fromStreams(mixed $reader, mixed $writer): self
    {
        $conn = new self($reader, $writer);
        $conn->performHandshake();
        return $conn;
    }

    /**
     * Create a connection without handshake (for testing).
     *
     * @param resource $reader Stream resource to read packets from
     * @param resource $writer Stream resource to write packets to
     */
    public static function fromRawStreams(mixed $reader, mixed $writer): self
    {
        return new self($reader, $writer);
    }

    public function controlStream(): Stream
    {
        return $this->controlStream;
    }

    /**
     * Allocate a new client-created stream (odd ID).
     */
    public function newStream(): Stream
    {
        $streamId = ($this->nextStreamCounter << 1) | 1;
        $this->nextStreamCounter++;

        $stream = new Stream($streamId, $this);
        $this->streams[$streamId] = $stream;
        return $stream;
    }

    /**
     * Connect to a server-created stream (even ID).
     */
    public function connectStream(int $streamId): Stream
    {
        $stream = new Stream($streamId, $this);
        $this->streams[$streamId] = $stream;
        return $stream;
    }

    public function unregisterStream(int $streamId): void
    {
        unset($this->streams[$streamId]);
    }

    /**
     * Send a packet through the writer.
     */
    public function sendPacket(Packet $packet): void
    {
        PacketWriter::write($this->writer, $packet);
    }

    /**
     * Read one packet from the reader.
     */
    public function readPacket(): null|Packet
    {
        return PacketReader::read($this->reader);
    }

    /**
     * Dispatch a packet to the correct stream's buffer.
     */
    public function dispatchPacket(Packet $packet): void
    {
        if (array_key_exists($packet->streamId, $this->streams)) {
            $this->streams[$packet->streamId]->bufferPacket($packet);
        }

        // Packets for unknown streams are silently dropped
    }

    /**
     * Handle a close stream packet.
     */
    public function handleCloseStream(int $streamId): void
    {
        if (array_key_exists($streamId, $this->streams)) {
            $this->streams[$streamId]->markClosed();
            unset($this->streams[$streamId]);
        }
    }

    private function performHandshake(): void
    {
        $ctrl = $this->controlStream;
        $msgId = $ctrl->sendRawRequest('hegel_handshake_start');
        $reply = $ctrl->receiveRawReply($msgId);

        if (!str_starts_with($reply, 'Hegel/')) {
            throw new ConnectionException("Invalid handshake reply: {$reply}");
        }
    }
}
