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

    // Mutant: startSpan removal — when spanLabel is set, start_span must be sent before generate
    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function draw_with_span_label_sends_start_span_command(): void
    {
        [, $serverSock, $stream] = $this->createStreamPair();
        $streamId = $stream->streamId();

        // Pre-write replies: start_span (msgId=1), generate (msgId=2), stop_span (msgId=3)
        $this->replyWithResult($serverSock, $streamId, 1, null);
        $this->replyWithResult($serverSock, $streamId, 2, 42);
        $this->replyWithResult($serverSock, $streamId, 3, null);

        $schema = ['type' => 'integer', 'min_value' => 0, 'max_value' => 100];
        $gen = new BasicGenerator($schema, spanLabel: SpanLabel::List_);

        $tc = new HegelTestCase($stream);
        /** @var mixed $value */
        $value = $gen->draw($tc);

        $this->assertSame(42, $value);

        // Read the three packets sent: start_span, generate, stop_span
        $p1 = PacketReader::read($serverSock);
        $this->assertNotNull($p1);
        /** @var array{command: string, label: string} $d1 */
        $d1 = CborCodec::decode($p1->payload);
        $this->assertSame('start_span', $d1['command'], 'First command must be start_span');
        $this->assertSame(SpanLabel::List_->value, $d1['label']);

        $p2 = PacketReader::read($serverSock);
        $this->assertNotNull($p2);
        /** @var array{command: string} $d2 */
        $d2 = CborCodec::decode($p2->payload);
        $this->assertSame('generate', $d2['command'], 'Second command must be generate');

        $p3 = PacketReader::read($serverSock);
        $this->assertNotNull($p3);
        /** @var array{command: string} $d3 */
        $d3 = CborCodec::decode($p3->payload);
        $this->assertSame('stop_span', $d3['command'], 'Third command must be stop_span');

        fclose($serverSock);
    }

    // Mutant: stopSpan removal in finally — stop_span must be sent even if generate returns normally
    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function draw_with_span_label_sends_stop_span_after_generate(): void
    {
        [, $serverSock, $stream] = $this->createStreamPair();
        $streamId = $stream->streamId();

        $this->replyWithResult($serverSock, $streamId, 1, null);  // start_span
        $this->replyWithResult($serverSock, $streamId, 2, 99);    // generate
        $this->replyWithResult($serverSock, $streamId, 3, null);  // stop_span

        $schema = ['type' => 'integer', 'min_value' => 0, 'max_value' => 100];
        $gen = new BasicGenerator($schema, spanLabel: SpanLabel::Mapped);

        $tc = new HegelTestCase($stream);
        $gen->draw($tc);

        // Consume start_span and generate packets
        PacketReader::read($serverSock);
        PacketReader::read($serverSock);

        // The third packet must be stop_span (from the finally block)
        $p3 = PacketReader::read($serverSock);
        $this->assertNotNull($p3, 'stop_span packet must be sent');
        /** @var array{command: string, discard: bool} $d3 */
        $d3 = CborCodec::decode($p3->payload);
        $this->assertSame('stop_span', $d3['command'], 'Finally block must send stop_span');
        $this->assertFalse($d3['discard']);

        fclose($serverSock);
    }

    // Mutant: startSpan/stopSpan must NOT be sent when spanLabel is null
    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function draw_without_span_label_sends_only_generate(): void
    {
        [, $serverSock, $stream] = $this->createStreamPair();
        $streamId = $stream->streamId();

        $this->replyWithResult($serverSock, $streamId, 1, 7);  // generate

        $schema = ['type' => 'integer', 'min_value' => 0, 'max_value' => 10];
        $gen = new BasicGenerator($schema);

        $tc = new HegelTestCase($stream);
        /** @var mixed $value */
        $value = $gen->draw($tc);
        $this->assertSame(7, $value);

        // Only one packet must have been sent (the generate command)
        $p1 = PacketReader::read($serverSock);
        $this->assertNotNull($p1);
        /** @var array{command: string} $d1 */
        $d1 = CborCodec::decode($p1->payload);
        $this->assertSame('generate', $d1['command']);

        // No further packets should be waiting
        stream_set_blocking($serverSock, false);
        $extra = PacketReader::read($serverSock);
        $this->assertNull($extra, 'No span commands must be sent when spanLabel is null');
        stream_set_blocking($serverSock, true);

        fclose($serverSock);
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
