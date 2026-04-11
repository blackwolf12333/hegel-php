<?php

declare(strict_types=1);

namespace Hegel\Exception;

enum ServerErrorType: string
{
    case StopTest = 'StopTest';
    case Overflow = 'Overflow';
    case FlakyStrategyDefinition = 'FlakyStrategyDefinition';
    case FlakyReplay = 'FlakyReplay';

    public static function fromServerType(string $type): self
    {
        return self::tryFrom($type) ?? throw new \ValueError("Unknown server error type: {$type}");
    }

    public function isExpectedTermination(): bool
    {
        return match ($this) {
            self::StopTest, self::Overflow, self::FlakyStrategyDefinition, self::FlakyReplay => true,
        };
    }
}
