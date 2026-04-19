<?php

declare(strict_types=1);

namespace Hegel\Wire;

use Hegel\Exception\ConnectionException;

final class PacketWriter
{
    /**
     * @param resource $stream
     * @throws ConnectionException
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
        $checksum = crc32($header . $packet->payload) & 0xFFFF_FFFF;

        // Patch checksum into header
        $header = substr($header, 0, 4) . pack('N', $checksum) . substr($header, 8);

        $result = fwrite($stream, $header . $packet->payload . chr(Packet::TERMINATOR));
        if (!$result){
            throw new ConnectionException('Failed to write to server connection');
        }
        $result = fflush($stream);
        if (!$result){
            throw new ConnectionException('Failed to write to server connection');
        }
    }
}
