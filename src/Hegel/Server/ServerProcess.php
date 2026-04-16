<?php

declare(strict_types=1);

namespace Hegel\Server;

use Hegel\Exception\ConnectionException;

final class ServerProcess
{
    public const string HEGEL_SERVER_VERSION = '0.4.0';

    /** @var resource|null */
    private mixed $process = null;

    /** @var resource|null stdin pipe */
    private mixed $stdin = null;

    /** @var resource|null stdout pipe */
    private mixed $stdout = null;

    public function start(): void
    {
        if ($this->process !== null) {
            return;
        }

        $command = $this->resolveCommand();

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr (discard)
        ];

        $currentEnv = getenv();
        $env = array_merge($currentEnv, ['PYTHONUNBUFFERED' => '1']);

        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, null, $env);
        if (!$process) {
            throw new ConnectionException("Failed to start hegel-core server: {$command}");
        }

        $this->process = $process;
        $this->stdin = $pipes[0] ?? null;
        $this->stdout = $pipes[1] ?? null;

        // Close stderr pipe to avoid blocking
        if (($pipes[2] ?? null) !== null) {
            fclose($pipes[2]);
        }
    }

    /** @return resource */
    public function stdin(): mixed
    {
        if ($this->stdin === null) {
            throw new ConnectionException('Server not started');
        }
        return $this->stdin;
    }

    /** @return resource */
    public function stdout(): mixed
    {
        if ($this->stdout === null) {
            throw new ConnectionException('Server not started');
        }
        return $this->stdout;
    }

    public function isRunning(): bool
    {
        if ($this->process === null) {
            return false;
        }
        $status = proc_get_status($this->process);
        return $status['running'];
    }

    // @mago-expect lint:halstead
    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        if ($this->stdin === null && $this->stdout === null) {
            return;
        }

        if ($this->stdin !== null) {
            try {
                fclose($this->stdin);
            } catch (\Throwable) {
                // @mago-expect lint:no-empty-catch-clause
            }
        }
        $this->stdin = null;

        if ($this->stdout !== null) {
            try {
                fclose($this->stdout);
            } catch (\Throwable) {
                // @mago-expect lint:no-empty-catch-clause
            }
        }
        $this->stdout = null;

        proc_terminate($this->process);
        proc_close($this->process);
        $this->process = null;
    }

    private function resolveCommand(): string
    {
        $envCommand = getenv('HEGEL_SERVER_COMMAND');
        if (is_string($envCommand) && $envCommand !== '') {
            return $envCommand;
        }

        $uv = $this->findUv();
        return sprintf(
            '%s tool run --from "hegel-core==%s" hegel --stdio --verbosity normal',
            $uv,
            self::HEGEL_SERVER_VERSION,
        );
    }

    private function findUv(): string
    {
        // Check PATH
        $which = trim((string) shell_exec('which uv 2>/dev/null'));
        if ($which !== '') {
            return $which;
        }

        // Check common locations
        $homeEnv = getenv('HOME');
        $home = is_string($homeEnv) ? $homeEnv : '/root';
        $xdgEnv = getenv('XDG_CACHE_HOME');
        $candidates = [
            (is_string($xdgEnv) ? $xdgEnv : "{$home}/.cache") . '/hegel/uv',
            "{$home}/.local/bin/uv",
            "{$home}/.cargo/bin/uv",
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        throw new ConnectionException(
            'Could not find uv. Install it from https://docs.astral.sh/uv/ or set HEGEL_SERVER_COMMAND.',
        );
    }
}
