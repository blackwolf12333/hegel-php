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
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data), 'Failed to read stream contents');
        fclose($stream);

        // 20 byte header + 5 byte payload + 1 byte terminator = 26
        $this->assertSame(26, strlen($data));

        // Magic bytes
        $unpacked = unpack('N', substr($data, 0, 4));
        assert($unpacked !== false, 'Failed to unpack magic bytes');
        $magic = $unpacked[1];
        $this->assertSame(0x4845_474C, $magic);

        // Stream ID
        $unpacked = unpack('N', substr($data, 8, 4));
        assert($unpacked !== false, 'Failed to unpack stream ID');
        $streamId = $unpacked[1];
        $this->assertSame(3, $streamId);

        // Message ID (no reply bit)
        $unpacked = unpack('N', substr($data, 12, 4));
        assert($unpacked !== false, 'Failed to unpack message ID');
        $messageId = $unpacked[1];
        $this->assertSame(1, $messageId);

        // Payload length
        $unpacked = unpack('N', substr($data, 16, 4));
        assert($unpacked !== false, 'Failed to unpack payload length');
        $payloadLen = $unpacked[1];
        $this->assertSame(5, $payloadLen);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data), 'Failed to read stream contents');
        fclose($stream);

        // Extract checksum from header
        $unpacked = unpack('N', substr($data, 4, 4));
        assert($unpacked !== false, 'Failed to unpack checksum');
        $checksum = $unpacked[1];

        // Compute expected: header with checksum zeroed + payload
        $headerWithZeroed = substr($data, 0, 4) . "\x00\x00\x00\x00" . substr($data, 8, 12);
        $expected = crc32($headerWithZeroed . 'hello');
        // crc32() returns signed on 32-bit, but we compare as unsigned
        $expected &= 0xFFFF_FFFF;

        $this->assertSame($expected, $checksum);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data), 'Failed to read stream contents');
        fclose($stream);

        $this->assertSame(0x0A, ord($data[strlen($data) - 1]));
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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
        assert($stream !== false, 'Failed to open memory stream');
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

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $original);
        rewind($stream);
        $decoded = PacketReader::read($stream);
        fclose($stream);

        $this->assertNotNull($decoded);
        $this->assertSame('', $decoded->payload);
        $this->assertSame(0, $decoded->streamId);
        $this->assertSame(1, $decoded->messageId);
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $original);
        rewind($stream);
        $decoded = PacketReader::read($stream);
        fclose($stream);

        $this->assertNotNull($decoded);
        $this->assertTrue($decoded->isReply);
        $this->assertSame(7, $decoded->messageId);
        $this->assertSame('reply data', $decoded->payload);
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     */
    #[Test]
    public function read_rejects_bad_magic(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert($stream !== false, 'Failed to open memory stream');
        // Write a header with wrong magic
        fwrite($stream, pack('N', 0xDEAD_BEEF)); // bad magic
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

    /**
     * @throws \Hegel\Exception\ConnectionException
     */
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
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data), 'Failed to read stream contents');

        // Corrupt checksum byte
        $data[4] = chr(ord($data[4]) ^ 0xFF);

        $corrupted = fopen('php://memory', 'r+');
        assert($corrupted !== false, 'Failed to open memory stream');
        fwrite($corrupted, $data);
        rewind($corrupted);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageMatches('/checksum/i');
        PacketReader::read($corrupted);
        fclose($corrupted);
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     */
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
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data), 'Failed to read stream contents');

        // Replace terminator (last byte)
        $data[strlen($data) - 1] = "\xFF";

        $corrupted = fopen('php://memory', 'r+');
        assert($corrupted !== false, 'Failed to open memory stream');
        fwrite($corrupted, $data);
        rewind($corrupted);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessageMatches('/terminator/i');
        PacketReader::read($corrupted);
        fclose($corrupted);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
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

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function close_stream_packet_roundtrips(): void
    {
        $original = Packet::closeStream(streamId: 7);

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $original);
        rewind($stream);
        $decoded = PacketReader::read($stream);
        fclose($stream);

        $this->assertNotNull($decoded);
        $this->assertTrue($decoded->isCloseStream());
        $this->assertSame(7, $decoded->streamId);
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function read_returns_null_on_eof(): void
    {
        $stream = fopen('php://memory', 'r+');
        assert($stream !== false, 'Failed to open memory stream');
        // Empty stream
        $result = PacketReader::read($stream);
        fclose($stream);

        $this->assertNull($result);
    }

    // --- Mutant 89: isCloseStream() && -> || ---

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function is_close_stream_requires_matching_message_id(): void
    {
        // messageId does NOT match CLOSE_STREAM_MESSAGE_ID, payload matches
        $packet = new Packet(
            streamId: 3,
            messageId: 1,
            isReply: false,
            payload: Packet::CLOSE_STREAM_PAYLOAD,
        );
        $this->assertFalse($packet->isCloseStream());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function is_close_stream_requires_matching_payload(): void
    {
        // messageId matches CLOSE_STREAM_MESSAGE_ID, payload does NOT match
        $packet = new Packet(
            streamId: 3,
            messageId: Packet::CLOSE_STREAM_MESSAGE_ID,
            isReply: false,
            payload: 'not-close',
        );
        $this->assertFalse($packet->isCloseStream());
    }

    // --- Mutants 90-95: header field substr offsets ---

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function roundtrip_all_distinct_nonzero_header_fields(): void
    {
        // Use distinct non-zero values in every field so that a wrong offset
        // in unpack() would read the wrong value and fail an assertion.
        $original = new Packet(
            streamId: 0x0000_1234,
            messageId: 0x0000_0057,
            isReply: true,
            payload: 'distinct-payload',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $original);
        rewind($stream);
        $decoded = PacketReader::read($stream);
        fclose($stream);

        $this->assertNotNull($decoded);
        $this->assertSame(0x0000_1234, $decoded->streamId);
        $this->assertSame(0x0000_0057, $decoded->messageId);
        $this->assertTrue($decoded->isReply);
        $this->assertSame('distinct-payload', $decoded->payload);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function header_checksum_field_is_at_offset_4(): void
    {
        // Write a packet, read the raw bytes, and verify the checksum is
        // exactly at bytes 4-7 (not 0-3 or 8-11).
        $packet = new Packet(
            streamId: 0xAB_CD_EF_01,
            messageId: 0x0000_0023,
            isReply: false,
            payload: 'crc-position-check',
        );

        $stream = fopen('php://memory', 'r+');
        assert($stream !== false, 'Failed to open memory stream');
        PacketWriter::write($stream, $packet);
        rewind($stream);
        $data = stream_get_contents($stream);
        assert(is_string($data), 'Failed to read stream contents');
        fclose($stream);

        // Magic must be at offset 0
        /** @var array{1: int} $unpackedMagic */
        $unpackedMagic = unpack('N', substr($data, 0, 4));
        $this->assertSame(Packet::MAGIC, $unpackedMagic[1]);

        // Checksum is at offset 4 — if we zero it and recompute, it should match
        /** @var array{1: int} $unpackedCrc */
        $unpackedCrc = unpack('N', substr($data, 4, 4));
        $storedCrc = $unpackedCrc[1];

        $headerWithZeroed = substr($data, 0, 4) . "\x00\x00\x00\x00" . substr($data, 8, 12);
        $recomputed = crc32($headerWithZeroed . 'crc-position-check') & 0xFFFF_FFFF;
        $this->assertSame($recomputed, $storedCrc);

        // StreamId is at offset 8
        /** @var array{1: int} $unpackedStream */
        $unpackedStream = unpack('N', substr($data, 8, 4));
        $this->assertSame(0xAB_CD_EF_01, $unpackedStream[1]);

        // MessageId (raw, with reply bit) is at offset 12
        /** @var array{1: int} $unpackedMsg */
        $unpackedMsg = unpack('N', substr($data, 12, 4));
        $this->assertSame(0x0000_0023, $unpackedMsg[1]);

        // PayloadLen is at offset 16
        /** @var array{1: int} $unpackedLen */
        $unpackedLen = unpack('N', substr($data, 16, 4));
        $this->assertSame(strlen('crc-position-check'), $unpackedLen[1]);
    }
}
