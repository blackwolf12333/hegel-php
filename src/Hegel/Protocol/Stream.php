<?php

declare(strict_types=1);

namespace Hegel\Protocol;

use Hegel\Codec\CborCodec;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\ProtocolException;
use Hegel\Protocol\Command\Command;
use Hegel\Wire\Packet;

final class Stream
{
    private int $nextMessageId = 1;

    /** @var array<int, string> Buffered reply payloads keyed by message ID */
    private array $responses = [];

    /** @var list<Packet> Buffered incoming request packets */
    private array $requests = [];

    private bool $closed = false;

    public function __construct(
        private readonly int $streamId,
        private readonly Connection $connection,
    ) {}

    public function streamId(): int
    {
        return $this->streamId;
    }

    /**
     * Send a raw (non-CBOR) request payload.
     * @return int The message ID assigned to this request.
     */
    public function sendRawRequest(string $payload): int
    {
        $msgId = $this->nextMessageId++;
        $packet = new Packet(
            streamId: $this->streamId,
            messageId: $msgId,
            isReply: false,
            payload: $payload,
        );
        $this->connection->sendPacket($packet);
        return $msgId;
    }

    /**
     * Send a CBOR-encoded request.
     * @return int The message ID assigned to this request.
     */
    public function sendRequest(mixed $data): int
    {
        /** @var mixed $payload */
        $payload = $data instanceof Command ? $data->toArray() : $data;
        return $this->sendRawRequest(CborCodec::encode($payload));
    }

    /**
     * Send a raw reply for a given message ID.
     */
    private function sendRawReply(int $messageId, string $payload): void
    {
        $packet = new Packet(
            streamId: $this->streamId,
            messageId: $messageId,
            isReply: true,
            payload: $payload,
        );
        $this->connection->sendPacket($packet);
    }

    /**
     * Send a CBOR-encoded reply for a given message ID.
     * Wraps the data in {"result": $data} as required by the protocol.
     */
    public function sendReply(int $messageId, mixed $data): void
    {
        $this->sendRawReply($messageId, CborCodec::encode(['result' => $data]));
    }

    /**
     * Receive a raw reply payload for a given message ID.
     * Blocks until the reply arrives, buffering any out-of-order packets.
     *
     * @throws ConnectionException
     * @throws \Hegel\Exception\ProtocolException
     */
    public function receiveRawReply(int $messageId): string
    {
        if (array_key_exists($messageId, $this->responses)) {
            $payload = $this->responses[$messageId];
            unset($this->responses[$messageId]);
            return $payload;
        }

        while (true) {
            $packet = $this->readNextOwnPacket('reply');

            if ($packet->isReply && $packet->messageId === $messageId) {
                return $packet->payload;
            }

            $this->bufferPacket($packet);
        }
    }

    /**
     * Receive a CBOR-decoded reply for a given message ID.
     * Checks for error responses and throws ConnectionException.
     *
     * @return mixed The 'result' field from the reply.
     * @throws ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
    public function receiveReply(int $messageId): mixed
    {
        $payload = $this->receiveRawReply($messageId);
        /** @var mixed $decoded */
        $decoded = CborCodec::decode($payload);

        if (is_array($decoded) && array_key_exists('error', $decoded)) {
            throw ConnectionException::serverError(
                type: (string) ($decoded['type'] ?? 'Unknown'),
                error: (string) $decoded['error'],
            );
        }

        if (is_array($decoded) && array_key_exists('result', $decoded)) {
            return $decoded['result'];
        }

        return $decoded;
    }

    /**
     * Send a CBOR request and wait for the decoded reply result.
     *
     * @throws ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
    public function requestCbor(mixed $data): mixed
    {
        $msgId = $this->sendRequest($data);
        return $this->receiveReply($msgId);
    }

    /**
     * Receive an incoming request (non-reply packet).
     * @return array{int, mixed} [messageId, decodedPayload]
     * @throws ConnectionException
     * @throws \Hegel\Exception\ProtocolException
     * @throws \InvalidArgumentException
     */
    public function receiveRequest(): array
    {
        if ($this->requests !== []) {
            $packet = array_shift($this->requests);
            return [$packet->messageId, CborCodec::decode($packet->payload)];
        }

        while (true) {
            $packet = $this->readNextOwnPacket('request');

            if (!$packet->isReply) {
                return [$packet->messageId, CborCodec::decode($packet->payload)];
            }

            $this->responses[$packet->messageId] = $packet->payload;
        }
    }

    /**
     * Buffer a packet destined for this stream.
     */
    public function bufferPacket(Packet $packet): void
    {
        if ($packet->isReply) {
            $this->responses[$packet->messageId] = $packet->payload;
            return;
        }

        $this->requests[] = $packet;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->connection->sendPacket(Packet::closeStream($this->streamId));
        $this->connection->unregisterStream($this->streamId);
    }

    public function markClosed(): void
    {
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Read the next packet destined for this stream, dispatching foreign packets.
     *
     * @throws ConnectionException If the connection is closed.
     * @throws \Hegel\Exception\ProtocolException If a packet arrives for a closed stream.
     */
    private function readNextOwnPacket(string $waitingFor): Packet
    {
        while (true) {
            $packet = $this->connection->readPacket();
            if ($packet === null) {
                throw new ConnectionException("Connection closed while waiting for {$waitingFor}");
            }

            if ($packet->isCloseStream()) {
                $this->connection->handleCloseStream($packet->streamId);
                continue;
            }

            if ($packet->streamId !== $this->streamId) {
                $this->connection->dispatchPacket($packet);
                continue;
            }

            return $packet;
        }
    }
}
