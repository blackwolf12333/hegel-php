<?php

declare(strict_types=1);

namespace Hegel\Protocol;

use Hegel\Exception\ConnectionException;
use Hegel\Exception\ProtocolException;
use Hegel\Wire\Packet;
use Hegel\Wire\PacketReader;
use Hegel\Wire\PacketWriter;

final class Connection
{
    /** @var int Next stream counter (streams are (counter << 1) | 1) */
    private int $nextStreamCounter = 1;

    /** @var array<int, Stream> Registered streams keyed by stream ID */
    private array $streams = [];

    /** @var array<int, list<Packet>> Packets for streams not yet connected */
    private array $pendingPackets = [];

    /** @var array<int, true> Stream IDs that were explicitly closed */
    private array $closedStreams = [];

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
     * @throws ConnectionException|ProtocolException
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

        if (array_key_exists($streamId, $this->pendingPackets)) {
            foreach ($this->pendingPackets[$streamId] as $packet) {
                $stream->bufferPacket($packet);
            }
            unset($this->pendingPackets[$streamId]);
        }

        return $stream;
    }

    public function unregisterStream(int $streamId): void
    {
        unset($this->streams[$streamId]);
        $this->closedStreams[$streamId] = true;
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
     *
     * @throws ConnectionException
     */
    public function readPacket(): null|Packet
    {
        return PacketReader::read($this->reader);
    }

    /**
     * Dispatch a packet to the correct stream's buffer.
     *
     * @throws ProtocolException If the stream was explicitly closed.
     */
    public function dispatchPacket(Packet $packet): void
    {
        if (array_key_exists($packet->streamId, $this->streams)) {
            $this->streams[$packet->streamId]->bufferPacket($packet);
            return;
        }

        if (array_key_exists($packet->streamId, $this->closedStreams)) {
            throw new ProtocolException(
                sprintf('Received packet for closed stream %d', $packet->streamId),
            );
        }

        // Buffer packets for streams that haven't been connected yet
        $this->pendingPackets[$packet->streamId][] = $packet;
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

    /**
     * @throws ConnectionException|ProtocolException
     */
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
