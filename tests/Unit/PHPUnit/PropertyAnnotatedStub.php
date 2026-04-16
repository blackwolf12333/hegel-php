<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\PHPUnit;

use Hegel\PHPUnit\Property;

/**
 * @internal Stub for reflection tests only.
 */
final class PropertyAnnotatedStub
{
    #[Property(testCases: 200)]
    public function myTest(): void
    {
    }

    public function normalTest(): void
    {
    }
}
