<?php

declare(strict_types=1);

namespace Hegel\Codec;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Decoder;
use CBOR\Encoder;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthMapObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\Normalizable;
use CBOR\StringStream;
use CBOR\Tag;
use CBOR\Tag\NegativeBigIntegerTag;
use CBOR\Tag\UnsignedBigIntegerTag;
use CBOR\UnsignedIntegerObject;

final class CborCodec
{
    private const CBOR_UINT32_MAX = 0xFFFF_FFFE;

    public static function encode(mixed $value): string
    {
        return new Encoder()->encode(self::prepareLargeInts($value));
    }

    /**
     * Recursively convert integers that exceed CBOR's 32-bit range into
     * big-integer tag objects so the encoder doesn't throw.
     */
    private static function prepareLargeInts(mixed $value): mixed
    {
        if (is_int($value)) {
            if ($value >= 0 && $value > self::CBOR_UINT32_MAX) {
                return UnsignedBigIntegerTag::create(
                    ByteStringObject::create(self::intToBytes($value)),
                );
            }
            if ($value < 0 && (-1 - $value) > self::CBOR_UINT32_MAX) {
                return NegativeBigIntegerTag::create(
                    ByteStringObject::create(self::intToBytes(-1 - $value)),
                );
            }
            return $value;
        }

        if (is_array($value)) {
            return array_map(self::prepareLargeInts(...), $value);
        }

        return $value;
    }

    /**
     * Convert a non-negative integer to a minimal big-endian byte string.
     */
    private static function intToBytes(int $value): string
    {
        $hex = dechex($value);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        return (string) hex2bin($hex);
    }

    public static function decode(string $data): mixed
    {
        $decoder = Decoder::create();

        try {
            $stream = StringStream::create($data);
            $object = $decoder->decode($stream);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Failed to decode CBOR: ' . $e->getMessage(), 0, $e);
        }

        return self::normalize($object);
    }

    private static function normalize(CBORObject $object): mixed
    {
        // Integers - cbor-php normalize() returns strings, we want ints
        if ($object instanceof UnsignedIntegerObject || $object instanceof NegativeIntegerObject) {
            return (int) $object->normalize();
        }

        // Big integer tags - normalize() returns string, cast to int
        if ($object instanceof UnsignedBigIntegerTag || $object instanceof NegativeBigIntegerTag) {
            return (int) $object->normalize();
        }

        // Tags - handle tag 91 (Hegel string) by extracting inner value
        if ($object instanceof Tag) {
            return self::normalize($object->getValue());
        }

        // Collections - recurse
        if ($object instanceof ListObject || $object instanceof IndefiniteLengthListObject) {
            $result = [];
            foreach ($object as $item) {
                assert($item instanceof CBORObject, 'List item must be a CBORObject');
                $result[] = self::normalize($item);
            }
            return $result;
        }

        if ($object instanceof MapObject || $object instanceof IndefiniteLengthMapObject) {
            $result = [];
            foreach ($object as $mapItem) {
                assert($mapItem instanceof \CBOR\MapItem, 'Map entry must be a MapItem');
                /** @var mixed $key */
                $key = self::normalize($mapItem->getKey());
                assert(is_string($key) || is_int($key), 'Map key must be a string or int');
                $result[$key] = self::normalize($mapItem->getValue());
            }
            return $result;
        }

        // Everything else (bool, null, float, text string, byte string) - use normalize()
        if ($object instanceof Normalizable) {
            return $object->normalize();
        }

        return null;
    }
}
