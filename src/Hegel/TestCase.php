<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Exception\AssumeRejectedException;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
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

    public function target(float $value, string $label): void
    {
        $this->stream->requestCbor(new TargetCommand($value, $label));
    }

    /**
     * Generate a value from a schema by sending a 'generate' command to the server.
     *
     * @throws DataExhaustedException
     */
    /**
     * @param array<string, mixed> $schema
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

    public function startSpan(int $label): void
    {
        $this->stream->requestCbor(new StartSpanCommand($label));
    }

    public function stopSpan(): void
    {
        $this->stream->requestCbor(new StopSpanCommand(false));
    }

    public function discardSpan(): void
    {
        $this->stream->requestCbor(new StopSpanCommand(true));
    }

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
