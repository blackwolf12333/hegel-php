<?php

declare(strict_types=1);

namespace Hegel\PHPUnit;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Property
{
    /**
     * @param list<string> $suppressHealthChecks
     */
    public function __construct(
        public readonly int $testCases = 100,
        public readonly null|int $seed = null,
        public readonly array $suppressHealthChecks = [],
    ) {}
}
