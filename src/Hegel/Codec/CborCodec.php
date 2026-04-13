<?php

declare(strict_types=1);

namespace Hegel\Codec;

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
use CBOR\UnsignedIntegerObject;

final class CborCodec
{
    public static function encode(mixed $value): string
    {
        return new Encoder()->encode($value);
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

        // Tags - handle tag 91 (Hegel string) by extracting inner value
        if ($object instanceof Tag) {
            return self::normalize($object->getValue());
        }

        // Collections - recurse
        if ($object instanceof ListObject || $object instanceof IndefiniteLengthListObject) {
            $result = [];
            foreach ($object as $item) {
                assert($item instanceof CBORObject);
                $result[] = self::normalize($item);
            }
            return $result;
        }

        if ($object instanceof MapObject || $object instanceof IndefiniteLengthMapObject) {
            $result = [];
            foreach ($object as $mapItem) {
                assert($mapItem instanceof \CBOR\MapItem);
                $key = self::normalize($mapItem->getKey());
                assert(is_string($key) || is_int($key));
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
