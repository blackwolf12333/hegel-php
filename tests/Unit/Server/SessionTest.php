<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Server;

use Hegel\Server\Session;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        Session::reset();
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function global_returns_same_instance_on_repeated_calls(): void
    {
        $first = Session::global();
        $second = Session::global();

        $this->assertSame($first, $second);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function global_returns_new_instance_after_reset(): void
    {
        $before = Session::global();
        Session::reset();
        $after = Session::global();

        $this->assertNotSame($before, $after);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    #[Test]
    public function reset_clears_singleton_so_global_creates_fresh_instance(): void
    {
        // Populate the singleton
        $instance = Session::global();
        $this->assertSame($instance, Session::global());

        // Reset clears it
        Session::reset();

        // A new instance is created
        $newInstance = Session::global();
        $this->assertInstanceOf(Session::class, $newInstance);
        $this->assertNotSame($instance, $newInstance);
    }
}
