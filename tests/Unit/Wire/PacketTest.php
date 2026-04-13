<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Wire;

use Hegel\Exception\ConnectionException;
use Hegel\Wire\Packet;
use Hegel\Wire\PacketReader;
use Hegel\Wire\PacketWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PacketTest extends TestCase
{
    #[Test]
    public function write_packet_produces_correct_header_bytes(): void
    {
        $packet = new Packet(
            streamId: 3,
            messageId: 1,
            isReply: false,
            payload: 'hello',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data));
        fclose($stream);

        // 20 byte header + 5 byte payload + 1 byte terminator = 26
        $this->assertSame(26, strlen($data));

        // Magic bytes
        $unpacked = unpack('N', substr($data, 0, 4));
        assert($unpacked !== false);
        $magic = $unpacked[1];
        $this->assertSame(0x4845474C, $magic);

        // Stream ID
        $unpacked = unpack('N', substr($data, 8, 4));
        assert($unpacked !== false);
        $streamId = $unpacked[1];
        $this->assertSame(3, $streamId);

        // Message ID (no reply bit)
        $unpacked = unpack('N', substr($data, 12, 4));
        assert($unpacked !== false);
        $messageId = $unpacked[1];
        $this->assertSame(1, $messageId);

        // Payload length
        $unpacked = unpack('N', substr($data, 16, 4));
        assert($unpacked !== false);
        $payloadLen = $unpacked[1];
        $this->assertSame(5, $payloadLen);
    }

    #[Test]
    public function write_packet_crc32_computed_over_header_with_zeroed_checksum_plus_payload(): void
    {
        $packet = new Packet(
            streamId: 3,
            messageId: 1,
            isReply: false,
            payload: 'hello',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data));
        fclose($stream);

        // Extract checksum from header
        $unpacked = unpack('N', substr($data, 4, 4));
        assert($unpacked !== false);
        $checksum = $unpacked[1];

        // Compute expected: header with checksum zeroed + payload
        $headerWithZeroed = substr($data, 0, 4) . "\x00\x00\x00\x00" . substr($data, 8, 12);
        $expected = crc32($headerWithZeroed . 'hello');
        // crc32() returns signed on 32-bit, but we compare as unsigned
        $expected &= 0xFFFFFFFF;

        $this->assertSame($expected, $checksum);
    }

    #[Test]
    public function write_packet_appends_0x0a_terminator(): void
    {
        $packet = new Packet(
            streamId: 0,
            messageId: 1,
            isReply: false,
            payload: 'test',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data));
        fclose($stream);

        $this->assertSame(0x0A, ord($data[strlen($data) - 1]));
    }

    #[Test]
    public function roundtrip_basic_payload(): void
    {
        $original = new Packet(
            streamId: 5,
            messageId: 42,
            isReply: false,
            payload: 'some cbor data here',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $original);
        rewind($stream);
        $decoded = PacketReader::read($stream);
        fclose($stream);

        $this->assertNotNull($decoded);
        $this->assertSame($original->streamId, $decoded->streamId);
        $this->assertSame($original->messageId, $decoded->messageId);
        $this->assertSame($original->isReply, $decoded->isReply);
        $this->assertSame($original->payload, $decoded->payload);
    }

    #[Test]
    public function roundtrip_empty_payload(): void
    {
        $original = new Packet(
            streamId: 0,
            messageId: 1,
            isReply: false,
            payload: '',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $original);
        rewind($stream);
        $decoded = PacketReader::read($stream);
        fclose($stream);

        $this->assertNotNull($decoded);
        $this->assertSame('', $decoded->payload);
        $this->assertSame(0, $decoded->streamId);
        $this->assertSame(1, $decoded->messageId);
    }

    #[Test]
    public function roundtrip_reply_flag(): void
    {
        $original = new Packet(
            streamId: 3,
            messageId: 7,
            isReply: true,
            payload: 'reply data',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $original);
        rewind($stream);
        $decoded = PacketReader::read($stream);
        fclose($stream);

        $this->assertNotNull($decoded);
        $this->assertTrue($decoded->isReply);
        $this->assertSame(7, $decoded->messageId);
        $this->assertSame('reply data', $decoded->payload);
    }

    #[Test]
    public function read_rejects_bad_magic(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        // Write a header with wrong magic
        fwrite($stream, pack('N', 0xDEADBEEF)); // bad magic
        fwrite($stream, pack('N', 0)); // checksum
        fwrite($stream, pack('N', 0)); // stream id
        fwrite($stream, pack('N', 1)); // message id
        fwrite($stream, pack('N', 0)); // payload len
        fwrite($stream, "\x0A"); // terminator
        rewind($stream);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageMatches('/magic/i');
        PacketReader::read($stream);
    }

    #[Test]
    public function read_rejects_bad_checksum(): void
    {
        // Build a valid packet then corrupt the checksum
        $packet = new Packet(
            streamId: 1,
            messageId: 1,
            isReply: false,
            payload: 'data',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data));

        // Corrupt checksum byte
        $data[4] = chr(ord($data[4]) ^ 0xFF);

        $corrupted = fopen('php://memory', 'r+');
        assert($corrupted !== false);
        fwrite($corrupted, $data);
        rewind($corrupted);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageMatches('/checksum/i');
        PacketReader::read($corrupted);
        fclose($corrupted);
    }

    #[Test]
    public function read_rejects_bad_terminator(): void
    {
        $packet = new Packet(
            streamId: 1,
            messageId: 1,
            isReply: false,
            payload: 'data',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data));

        // Replace terminator (last byte)
        $data[strlen($data) - 1] = "\xFF";

        $corrupted = fopen('php://memory', 'r+');
        assert($corrupted !== false);
        fwrite($corrupted, $data);
        rewind($corrupted);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageMatches('/terminator/i');
        PacketReader::read($corrupted);
        fclose($corrupted);
    }

    #[Test]
    public function close_stream_packet_detection(): void
    {
        $packet = Packet::closeStream(streamId: 5);

        $this->assertTrue($packet->isCloseStream());
        $this->assertSame(5, $packet->streamId);
        $this->assertSame(Packet::CLOSE_STREAM_MESSAGE_ID, $packet->messageId);
        $this->assertSame("\xFE", $packet->payload);
        $this->assertFalse($packet->isReply);
    }

    #[Test]
    public function regular_packet_is_not_close_stream(): void
    {
        $packet = new Packet(
            streamId: 5,
            messageId: 1,
            isReply: false,
            payload: 'data',
        );
        $this->assertFalse($packet->isCloseStream());
    }

    #[Test]
    public function close_stream_packet_roundtrips(): void
    {
        $original = Packet::closeStream(streamId: 7);

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        PacketWriter::write($stream, $original);
        rewind($stream);
        $decoded = PacketReader::read($stream);
        fclose($stream);

        $this->assertNotNull($decoded);
        $this->assertTrue($decoded->isCloseStream());
        $this->assertSame(7, $decoded->streamId);
    }

    #[Test]
    public function read_returns_null_on_eof(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        // Empty stream
        $result = PacketReader::read($stream);
        fclose($stream);

        $this->assertNull($result);
    }
}
