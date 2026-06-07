<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Generator;

use Hegel\Codec\CborCodec;
use Hegel\Generator\BasicGenerator;
use Hegel\Protocol\Connection;
use Hegel\SpanLabel;
use Hegel\TestCase as HegelTestCase;
use Hegel\Wire\Packet;
use Hegel\Wire\PacketReader;
use Hegel\Wire\PacketWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BasicGeneratorTest extends TestCase
{
    /**
     * @return array{Connection, resource, \Hegel\Protocol\Stream}
     */
    private function createStreamPair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert($pair !== false, 'stream_socket_pair() failed');
        [$clientSock, $serverSock] = $pair;
        $conn = Connection::fromRawStreams($clientSock, $clientSock);
        $stream = $conn->newStream();
        return [$conn, $serverSock, $stream];
    }

    /**
     * @param resource $serverSock
     */
    private function replyWithResult(mixed $serverSock, int $streamId, int $messageId, mixed $result): void
    {
        assert(is_resource($serverSock), 'Expected a resource for server socket');
        PacketWriter::write($serverSock, new Packet(
            streamId: $streamId,
            messageId: $messageId,
            isReply: true,
            payload: CborCodec::encode(['result' => $result]),
        ));
    }

    // Mutant: transform closure must be applied to the generated value
    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function draw_applies_transform_to_generated_value(): void
    {
        [, $serverSock, $stream] = $this->createStreamPair();
        $streamId = $stream->streamId();

        $this->replyWithResult($serverSock, $streamId, 1, 10);  // generate returns 10

        $schema = ['type' => 'integer', 'min_value' => 0, 'max_value' => 100];
        $gen = new BasicGenerator($schema, transform: static fn (mixed $v): mixed => (int) $v * 2);

        $tc = new HegelTestCase($stream);
        /** @var mixed $value */
        $value = $gen->draw($tc);
        $this->assertSame(20, $value);

        fclose($serverSock);
    }
}
