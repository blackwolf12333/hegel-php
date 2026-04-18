<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Exception\AssumeRejectedException;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Exception\ProtocolException;
use Hegel\Exception\ServerErrorType;
use Hegel\Generator\Generator;
use Hegel\Protocol\Command\GenerateCommand;
use Hegel\Protocol\Command\NewCollectionCommand;
use Hegel\Protocol\Command\StartSpanCommand;
use Hegel\Protocol\Command\StopSpanCommand;
use Hegel\Protocol\Command\TargetCommand;
use Hegel\Protocol\Stream;

final class TestCase
{
    /** @var (\Closure(string): void)|null */
    private null|\Closure $noteFn;

    public function __construct(
        private readonly Stream $stream,
        private readonly TestPhase $phase = TestPhase::Exploration,
        null|\Closure $noteFn = null,
    ) {
        $this->noteFn = $noteFn;
    }

    /**
     * @template T
     * @param Generator<T> $generator
     * @return T
     * @throws ConnectionException|ProtocolException
     * @throws DataExhaustedException
     * @throws \InvalidArgumentException
     */
    public function draw(Generator $generator): mixed
    {
        return $generator->draw($this);
    }

    /**
     * Reject the current test case (marks it as INVALID).
     *
     * @throws AssumeRejectedException
     */
    public function reject(): never
    {
        throw new AssumeRejectedException('Assumption rejected');
    }

    public function note(string $message): void
    {
        if ($this->phase === TestPhase::Final && $this->noteFn !== null) {
            ($this->noteFn)($message);
        }
    }

    /**
     * @throws ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
    public function target(float $value, string $label): void
    {
        $this->stream->requestCbor(new TargetCommand($value, $label));
    }

    /**
     * Generate a value from a schema by sending a 'generate' command to the server.
     *
     * @param array<string, mixed> $schema
     * @throws DataExhaustedException
     * @throws ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
    public function generateFromSchema(array $schema): mixed
    {
        try {
            return $this->stream->requestCbor(new GenerateCommand($schema));
        } catch (ConnectionException $e) {
            if ($e->serverErrorType === ServerErrorType::StopTest) {
                throw new DataExhaustedException('Data exhausted', 0, $e);
            }
            throw $e;
        }
    }

    /**
     * @throws ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
    public function startSpan(SpanLabel $label): void
    {
        $this->stream->requestCbor(new StartSpanCommand($label->value));
    }

    /**
     * @throws ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
    public function stopSpan(): void
    {
        $this->stream->requestCbor(new StopSpanCommand(false));
    }

    /**
     * @throws ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
    public function discardSpan(): void
    {
        $this->stream->requestCbor(new StopSpanCommand(true));
    }

    /**
     * @throws ConnectionException|ProtocolException
     * @throws \InvalidArgumentException
     */
    public function newCollection(int $minSize, null|int $maxSize): Collection
    {
        /** @var mixed $id */
        $id = $this->stream->requestCbor(new NewCollectionCommand($minSize, $maxSize));
        assert(is_int($id) || is_string($id), 'Collection ID must be an int or string');

        return new Collection($id, $this->stream);
    }

    public function stream(): Stream
    {
        return $this->stream;
    }

    public function isFinal(): bool
    {
        return $this->phase === TestPhase::Final;
    }
}
