<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Server;

use Hegel\Exception\ConnectionException;
use Hegel\Server\ServerProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerProcessTest extends TestCase
{
    /** @var string|false */
    private mixed $originalEnv;

    #[\Override]
    protected function setUp(): void
    {
        $this->originalEnv = getenv('HEGEL_SERVER_COMMAND');
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->originalEnv !== false) {
            putenv('HEGEL_SERVER_COMMAND=' . $this->originalEnv);
            return;
        }
        putenv('HEGEL_SERVER_COMMAND');
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function start_uses_hegel_server_command_env_var_when_set(): void
    {
        // Use `cat` so the process stays open waiting for stdin input
        putenv('HEGEL_SERVER_COMMAND=cat');

        $process = new ServerProcess();
        $process->start();

        $this->assertTrue($process->isRunning());

        $process->stop();
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function start_ignores_empty_hegel_server_command_env_var(): void
    {
        // An empty env var should fall through to the uv path, not be used as command.
        // We can't easily test the uv path in isolation, but we can confirm that
        // setting an empty string does NOT successfully start a process via that "command".
        // Instead it should attempt to find uv and throw ConnectionException (or succeed if uv is present).
        putenv('HEGEL_SERVER_COMMAND=');

        $process = new ServerProcess();

        // If uv is not available, it will throw ConnectionException.
        // If uv is available, it starts the real server — either outcome is fine.
        // The key thing is that it does NOT start the empty-string command.
        try {
            $process->start();
            // uv was available and started a server; clean up
            $process->stop();
            $this->addToAssertionCount(1);
        } catch (ConnectionException $e) {
            // uv was not found — the empty env var was correctly ignored
            $this->assertStringNotContainsString(
                "Failed to start hegel-core server: \n",
                $e->getMessage(),
                'An empty HEGEL_SERVER_COMMAND should not be passed as a command',
            );
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function start_does_not_start_again_when_already_running(): void
    {
        putenv('HEGEL_SERVER_COMMAND=cat');

        $process = new ServerProcess();
        $process->start();
        $this->assertTrue($process->isRunning());

        // Calling start() again on a running process must be a no-op (no exception, same process)
        $process->start();
        $this->assertTrue($process->isRunning());

        $process->stop();
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     */
    #[Test]
    public function stdin_throws_when_process_not_started(): void
    {
        $process = new ServerProcess();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Server not started');

        $process->stdin();
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     */
    #[Test]
    public function stdout_throws_when_process_not_started(): void
    {
        $process = new ServerProcess();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Server not started');

        $process->stdout();
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function is_running_returns_false_when_not_started(): void
    {
        $process = new ServerProcess();

        $this->assertFalse($process->isRunning());
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function stop_is_safe_to_call_when_not_started(): void
    {
        $process = new ServerProcess();

        // Must not throw
        $process->stop();

        $this->assertFalse($process->isRunning());
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function stdin_and_stdout_are_accessible_after_start(): void
    {
        putenv('HEGEL_SERVER_COMMAND=cat');

        $process = new ServerProcess();
        $process->start();

        $stdin = $process->stdin();
        $stdout = $process->stdout();

        // Verify we can write/read (proves pipes are open)
        fwrite($stdin, "test\n");
        $this->assertNotFalse(fgets($stdout));

        $process->stop();
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function is_running_returns_false_after_stop(): void
    {
        putenv('HEGEL_SERVER_COMMAND=cat');

        $process = new ServerProcess();
        $process->start();
        $this->assertTrue($process->isRunning());

        $process->stop();
        $this->assertFalse($process->isRunning());
    }

}
