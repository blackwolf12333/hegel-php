<?php

declare(strict_types=1);

namespace Hegel\Wire;

final class PacketWriter
{
    /**
     * @param resource $stream
     */
    public static function write(mixed $stream, Packet $packet): void
    {
        $messageIdRaw = $packet->messageId;
        if ($packet->isReply) {
            $messageIdRaw |= Packet::REPLY_BIT;
        }

        $payloadLen = strlen($packet->payload);

        // Build header with checksum zeroed
        $header =
            pack('N', Packet::MAGIC)
            . "\x00\x00\x00\x00"
            . pack('N', $packet->streamId)
            . pack('N', $messageIdRaw)
            . pack('N', $payloadLen);

        // Compute CRC32 over header (checksum zeroed) + payload
        $checksum = crc32($header . $packet->payload) & 0xFFFFFFFF;

        // Patch checksum into header
        $header = substr($header, 0, 4) . pack('N', $checksum) . substr($header, 8);

        fwrite($stream, $header . $packet->payload . chr(Packet::TERMINATOR));
        fflush($stream);
    }
}
