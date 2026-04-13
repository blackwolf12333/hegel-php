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
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame($data, $result);
    }

    #[Test]
    public function roundtrip_list(): void
    {
        $data = [1, 2, 3];
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
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame($data, $result);
    }

    #[Test]
    public function roundtrip_empty_list(): void
    {
        $data = [];
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
        $result = CborCodec::decode(CborCodec::encode($data));
        $this->assertSame($data, $result);
    }
}
