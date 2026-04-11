<?php

declare(strict_types=1);

namespace Hegel;

use Hegel\Exception\AssumeRejectedException;
use Hegel\Exception\ConnectionException;
use Hegel\Exception\DataExhaustedException;
use Hegel\Generator\Generator;
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
        $this->stream->requestCbor([
            'command' => 'target',
            'value' => $value,
            'label' => $label,
        ]);
    }

    /**
     * Generate a value from a schema by sending a 'generate' command to the server.
     *
     * @throws DataExhaustedException
     */
    public function generateFromSchema(array $schema): mixed
    {
        try {
            return $this->stream->requestCbor([
                'command' => 'generate',
                'schema' => $schema,
            ]);
        } catch (ConnectionException $e) {
            if (str_contains($e->getMessage(), 'StopTest')) {
                throw new DataExhaustedException('Data exhausted', 0, $e);
            }
            throw $e;
        }
    }

    public function startSpan(int $label): void
    {
        $this->stream->requestCbor([
            'command' => 'start_span',
            'label' => $label,
        ]);
    }

    public function stopSpan(): void
    {
        $this->stream->requestCbor([
            'command' => 'stop_span',
            'discard' => false,
        ]);
    }

    public function discardSpan(): void
    {
        $this->stream->requestCbor([
            'command' => 'stop_span',
            'discard' => true,
        ]);
    }

    public function newCollection(int $minSize, null|int $maxSize): Collection
    {
        $data = ['command' => 'new_collection', 'min_size' => $minSize];
        if ($maxSize !== null) {
            $data['max_size'] = $maxSize;
        }

        $id = $this->stream->requestCbor($data);

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
