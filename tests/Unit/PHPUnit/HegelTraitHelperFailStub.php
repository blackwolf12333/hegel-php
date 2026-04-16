<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\PHPUnit;

use Hegel\TestCase as TC;
use PHPUnit\Framework\TestCase;

/**
 * @internal Stub for HegelTraitHelper tests — test method always throws.
 */
final class HegelTraitHelperFailStub extends TestCase
{
    public function myTest(TC $_tc): void
    {
        throw new \RuntimeException('property violated');
    }
}
