<?php

declare(strict_types=1);

namespace Hegel\Wire;

final readonly class Packet
{
    public const int MAGIC = 0x4845_474C;
    public const int REPLY_BIT = 1 << 31;
    public const int TERMINATOR = 0x0A;
    public const int HEADER_SIZE = 20;
    public const int CLOSE_STREAM_MESSAGE_ID = (1 << 31) - 1;
    public const string CLOSE_STREAM_PAYLOAD = "\xFE";

    public function __construct(
        public int $streamId,
        public int $messageId,
        public bool $isReply,
        public string $payload,
    ) {}

    public static function closeStream(int $streamId): self
    {
        return new self(
            streamId: $streamId,
            messageId: self::CLOSE_STREAM_MESSAGE_ID,
            isReply: false,
            payload: self::CLOSE_STREAM_PAYLOAD,
        );
    }

    public function isCloseStream(): bool
    {
        return (
            $this->messageId === self::CLOSE_STREAM_MESSAGE_ID
            && $this->payload === self::CLOSE_STREAM_PAYLOAD
            && !$this->isReply
        );
    }
}
