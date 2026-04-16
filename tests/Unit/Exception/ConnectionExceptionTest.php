<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Exception;

use Hegel\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionExceptionTest extends TestCase
{
    #[Test]
    public function default_code_is_zero(): void
    {
        $exception = new ConnectionException('some message');

        $this->assertSame(0, $exception->getCode());
    }

    #[Test]
    public function message_is_set_correctly(): void
    {
        $exception = new ConnectionException('test error');

        $this->assertSame('test error', $exception->getMessage());
    }

    #[Test]
    public function server_error_type_is_null_by_default(): void
    {
        $exception = new ConnectionException('test error');

        $this->assertNull($exception->serverErrorType);
    }

    #[Test]
    public function custom_code_is_preserved(): void
    {
        $exception = new ConnectionException('msg', 42);

        $this->assertSame(42, $exception->getCode());
    }
}
