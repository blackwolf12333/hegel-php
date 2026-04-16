<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Codec;

use Hegel\Codec\CborCodec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CborCodecTest extends TestCase
{
    #[Test]
    public function roundtrip_integer(): void
    {
        $this->assertSame(42, CborCodec::decode(CborCodec::encode(42)));
    }

    #[Test]
    public function roundtrip_negative_integer(): void
    {
        $this->assertSame(-100, CborCodec::decode(CborCodec::encode(-100)));
    }

    #[Test]
    public function roundtrip_string(): void
    {
        $this->assertSame('hello world', CborCodec::decode(CborCodec::encode('hello world')));
    }

    #[Test]
    public function roundtrip_boolean(): void
    {
        $this->assertTrue(CborCodec::decode(CborCodec::encode(true)));
        $this->assertFalse(CborCodec::decode(CborCodec::encode(false)));
    }

    #[Test]
    public function roundtrip_null(): void
    {
        $this->assertNull(CborCodec::decode(CborCodec::encode(null)));
    }

    #[Test]
    public function roundtrip_float(): void
    {
        $this->assertSame(3.14, CborCodec::decode(CborCodec::encode(3.14)));
    }

    #[Test]
    public function roundtrip_map(): void
    {
        $data = ['command' => 'generate', 'schema' => ['type' => 'integer', 'min_value' => 0, 'max_value' => 100]];
        /** @var mixed $result */
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame($data, $result);
    }

    #[Test]
    public function roundtrip_list(): void
    {
        $data = [1, 2, 3];
        /** @var mixed $result */
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame($data, $result);
    }

    #[Test]
    public function roundtrip_nested_structure(): void
    {
        $data = [
            'command' => 'run_test',
            'test_cases' => 100,
            'stream_id' => 3,
            'suppress_health_check' => ['filter_too_much', 'too_slow'],
        ];
        /** @var mixed $result */
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame($data, $result);
    }

    #[Test]
    public function decode_invalid_cbor_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CborCodec::decode("\xFF\xFF\xFF");
    }

    #[Test]
    public function roundtrip_zero(): void
    {
        $this->assertSame(0, CborCodec::decode(CborCodec::encode(0)));
    }

    #[Test]
    public function roundtrip_map_with_integer_values(): void
    {
        $data = ['a' => 1, 'b' => 2];
        /** @var mixed $result */
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame($data, $result);
    }

    #[Test]
    public function roundtrip_empty_list(): void
    {
        $data = [];
        /** @var mixed $result */
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame([], $result);
    }

    #[Test]
    public function roundtrip_large_positive_integer(): void
    {
        $this->assertSame(PHP_INT_MAX, CborCodec::decode(CborCodec::encode(PHP_INT_MAX)));
    }

    #[Test]
    public function roundtrip_large_negative_integer(): void
    {
        $this->assertSame(PHP_INT_MIN, CborCodec::decode(CborCodec::encode(PHP_INT_MIN)));
    }

    #[Test]
    public function roundtrip_map_with_large_integers(): void
    {
        $data = ['min_value' => PHP_INT_MIN, 'max_value' => PHP_INT_MAX];
        /** @var mixed $result */
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame($data, $result);
    }

    // Mutant 1: >=0 → >0 — value 0 must not be treated as big-int
    #[Test]
    public function encode_zero_is_not_big_int_tagged(): void
    {
        $encoded = CborCodec::encode(0);
        // CBOR tag 2 (unsigned big-int) starts with 0xC2; value 0 must not start with it
        $this->assertStringNotContainsString("\xC2", $encoded);
        $this->assertSame(0, CborCodec::decode($encoded));
    }

    // Mutant 2: > CBOR_UINT32_MAX → >= CBOR_UINT32_MAX — value exactly at boundary (0xFFFF_FFFE)
    // must NOT be big-int tagged, should encode as a normal 4-byte uint (first byte 0x1A)
    #[Test]
    public function encode_value_at_uint32_max_is_not_big_int_tagged(): void
    {
        $value = 0xFFFF_FFFE; // exactly CBOR_UINT32_MAX
        $encoded = CborCodec::encode($value);
        // Must not contain CBOR tag-2 marker (0xC2)
        $this->assertStringNotContainsString("\xC2", $encoded);
        // Must decode back correctly
        $this->assertSame($value, CborCodec::decode($encoded));
    }

    // Mutant 2 counterpart: value just above boundary (0xFFFF_FFFF) MUST be big-int tagged
    #[Test]
    public function encode_value_above_uint32_max_is_big_int_tagged(): void
    {
        $value = 0xFFFF_FFFF; // CBOR_UINT32_MAX + 1
        $encoded = CborCodec::encode($value);
        // CBOR tag 2 (unsigned big-int) must be present; wire byte 0xC2
        $this->assertStringContainsString("\xC2", $encoded);
        $this->assertSame($value, CborCodec::decode($encoded));
    }

    // Mutant 3: && → || — small positive value like 5 must NOT be big-int tagged
    // With &&: (5 >= 0 && 5 > CBOR_UINT32_MAX) = false → skip (correct)
    // With ||: (5 >= 0 || 5 > CBOR_UINT32_MAX) = true → incorrectly big-int tags 5
    #[Test]
    public function encode_small_positive_integer_is_not_big_int_tagged(): void
    {
        $encoded = CborCodec::encode(5);
        $this->assertStringNotContainsString("\xC2", $encoded);
        $this->assertSame(5, CborCodec::decode($encoded));
    }

    // Mutant 4: negative boundary — value -0xFFFF_FFFF: (-1 - (-0xFFFF_FFFF)) = 0xFFFF_FFFE
    // 0xFFFF_FFFE > 0xFFFF_FFFE is false → must NOT be negative big-int tagged
    #[Test]
    public function encode_negative_value_at_boundary_is_not_big_int_tagged(): void
    {
        $value = -0xFFFF_FFFF; // -4294967295
        $encoded = CborCodec::encode($value);
        // CBOR tag 3 (negative big-int) starts with 0xC3
        $this->assertStringNotContainsString("\xC3", $encoded);
        $this->assertSame($value, CborCodec::decode($encoded));
    }

    // Mutant 5 counterpart: value -0x1_0000_0000: (-1 - (-0x1_0000_0000)) = 0xFFFF_FFFF
    // 0xFFFF_FFFF > 0xFFFF_FFFE is true → MUST be negative big-int tagged
    #[Test]
    public function encode_negative_value_above_boundary_is_big_int_tagged(): void
    {
        $value = -0x1_0000_0000; // -4294967296
        $encoded = CborCodec::encode($value);
        // CBOR tag 3 (negative big-int) must be present; wire byte 0xC3
        $this->assertStringContainsString("\xC3", $encoded);
        $this->assertSame($value, CborCodec::decode($encoded));
    }

    // Mutant 9: return $value removed — normal ints must encode to CBOR, not null/empty
    #[Test]
    public function encode_normal_integer_produces_non_empty_cbor(): void
    {
        $encoded = CborCodec::encode(42);
        $this->assertNotEmpty($encoded);
        // Without the return, the int falls through to array check and returns null;
        // null encodes to CBOR null (0xF6), not an integer
        // CBOR major type 0 (uint): first byte 0x18 for 1-byte ext, value 42 = 0x2A
        $this->assertSame("\x18\x2A", $encoded);
    }

    // Mutant 10: strlen($hex) % 2 → % 1 — % 1 is always 0, so padding never happens.
    // Value 0x1_0000_0000 (dechex → "100000000", 9 hex digits, odd length) must encode/decode correctly.
    #[Test]
    public function encode_big_int_with_odd_hex_length_roundtrips(): void
    {
        // 0x1_0000_0000 has hex "100000000" (9 chars, odd) — requires padding to "0100000000"
        $value = 0x1_0000_0000; // 4294967296
        $this->assertSame($value, CborCodec::decode(CborCodec::encode($value)));
    }

    // Mutants 11-15: decode exception format
    #[Test]
    public function decode_invalid_cbor_exception_has_correct_message_prefix(): void
    {
        try {
            CborCodec::decode("\xFF\xFF\xFF");
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringStartsWith('Failed to decode CBOR:', $e->getMessage());
            $this->assertSame(0, $e->getCode());
            $this->assertNotNull($e->getPrevious());
        }
    }

    // Mutant: concat removal — inner exception message must be appended after the prefix
    #[Test]
    public function decode_invalid_cbor_exception_includes_inner_message(): void
    {
        try {
            CborCodec::decode("\xFF\xFF\xFF");
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $previous = $e->getPrevious();
            $this->assertNotNull($previous, 'Previous exception must be set');
            // The wrapper message must contain the inner exception's message text,
            // not just the bare prefix 'Failed to decode CBOR: '
            $this->assertStringContainsString($previous->getMessage(), $e->getMessage());
            $this->assertNotSame('Failed to decode CBOR: ', $e->getMessage());
        }
    }
}
