<?php

declare(strict_types=1);

namespace Hegel\Wire;

use Hegel\Exception\ConnectionException;

final class PacketReader
{
    /**
     * @param resource $stream
     * @return Packet|null null on clean EOF (no bytes read)
     * @throws ConnectionException on protocol errors
     */
    public static function read(mixed $stream): null|Packet
    {
        $header = self::readExact($stream, Packet::HEADER_SIZE);
        if ($header === null) {
            return null; // Clean EOF
        }

        $magic = unpack('N', substr($header, 0, 4))[1];
        if ($magic !== Packet::MAGIC) {
            throw new ConnectionException(sprintf(
                'Invalid magic number: expected 0x%08X, got 0x%08X',
                Packet::MAGIC,
                $magic,
            ));
        }

        $checksum = unpack('N', substr($header, 4, 4))[1];
        $streamId = unpack('N', substr($header, 8, 4))[1];
        $messageIdRaw = unpack('N', substr($header, 12, 4))[1];
        $payloadLen = unpack('N', substr($header, 16, 4))[1];

        $isReply = ($messageIdRaw & Packet::REPLY_BIT) !== 0;
        $messageId = $messageIdRaw & ~Packet::REPLY_BIT;

        $payload = '';
        if ($payloadLen > 0) {
            $payload = self::readExact($stream, $payloadLen);
            if ($payload === null) {
                throw new ConnectionException('Unexpected EOF reading packet payload');
            }
        }

        $terminator = self::readExact($stream, 1);
        if ($terminator === null) {
            throw new ConnectionException('Unexpected EOF reading packet terminator');
        }
        if (ord($terminator) !== Packet::TERMINATOR) {
            throw new ConnectionException(sprintf(
                'Invalid terminator: expected 0x%02X, got 0x%02X',
                Packet::TERMINATOR,
                ord($terminator),
            ));
        }

        // Verify checksum: header with checksum field zeroed + payload
        $headerZeroed = substr($header, 0, 4) . "\x00\x00\x00\x00" . substr($header, 8);
        $computed = crc32($headerZeroed . $payload) & 0xFFFFFFFF;
        if ($computed !== $checksum) {
            throw new ConnectionException(sprintf(
                'Checksum mismatch: expected 0x%08X, got 0x%08X',
                $checksum,
                $computed,
            ));
        }

        return new Packet(
            streamId: $streamId,
            messageId: $messageId,
            isReply: $isReply,
            payload: $payload,
        );
    }

    /**
     * @param resource $stream
     * @return string|null null on clean EOF (zero bytes read)
     * @throws ConnectionException on partial read
     */
    private static function readExact(mixed $stream, int $length): null|string
    {
        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = fread($stream, $remaining);
            if (!$chunk) {
                if ($data === '') {
                    return null; // Clean EOF
                }
                throw new ConnectionException(sprintf('Unexpected EOF: read %d of %d bytes', strlen($data), $length));
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }
}
