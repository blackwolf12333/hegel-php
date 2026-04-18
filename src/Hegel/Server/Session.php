<?php

declare(strict_types=1);

namespace Hegel\Server;

use Hegel\Exception\ConnectionException;
use Hegel\Protocol\Connection;

final class Session
{
    private static null|self $instance = null;

    private null|ServerProcess $process = null;
    private null|Connection $connection = null;

    private function __construct() {}

    public static function global(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Reset the global session (for testing).
     */
    public static function reset(): void
    {
        if (self::$instance !== null) {
            self::$instance->stop();
            self::$instance = null;
        }
    }

    /**
     * @throws ConnectionException
     */
    public function connection(): Connection
    {
        if ($this->connection !== null && $this->process !== null && $this->process->isRunning()) {
            return $this->connection;
        }

        $this->start();
        assert($this->connection !== null, 'Connection should be established after start()');
        return $this->connection;
    }

    /**
     * @throws ConnectionException
     */
    private function start(): void
    {
        // Clean up stale session
        if ($this->process !== null) {
            $this->stop();
        }

        $this->process = new ServerProcess();
        $this->process->start();

        try {
            $this->connection = Connection::fromStreams($this->process->stdout(), $this->process->stdin());
        } catch (\Throwable $e) {
            $this->stop();
            throw new ConnectionException('Failed to establish connection with hegel-core: ' . $e->getMessage(), 0, $e);
        }
    }

    private function stop(): void
    {
        $this->connection = null;
        if ($this->process !== null) {
            $this->process->stop();
            $this->process = null;
        }
    }
}
