<?php

declare(strict_types=1);

namespace Hegel\Exception;

final class ConnectionException extends HegelException
{
    public readonly null|ServerErrorType $serverErrorType;

    public function __construct(
        string $message = '',
        int $code = 0,
        null|\Throwable $previous = null,
        null|ServerErrorType $serverErrorType = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->serverErrorType = $serverErrorType;
    }

    public static function serverError(string $type, string $error): self
    {
        $errorType = ServerErrorType::tryFrom($type);
        return new self(
            message: "Server error ({$type}): {$error}",
            serverErrorType: $errorType,
        );
    }
}
