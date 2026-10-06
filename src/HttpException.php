<?php
declare(strict_types=1);

class HttpException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 400, private readonly string $errorCode = 'request_error')
    {
        parent::__construct($message);
    }

    public function status(): int { return $this->status; }
    public function errorCode(): string { return $this->errorCode; }
}

final class ValidationException extends HttpException
{
    public function __construct(string $message) { parent::__construct($message, 422, 'validation_error'); }
}

final class MissingPriceException extends HttpException
{
    public function __construct(string $message, private readonly array $details = [])
    {
        parent::__construct($message, 422, 'missing_price');
    }

    public function details(): array { return $this->details; }
}

final class ConflictException extends HttpException
{
    public function __construct(string $message) { parent::__construct($message, 409, 'conflict'); }
}

final class NotFoundException extends HttpException
{
    public function __construct(string $message) { parent::__construct($message, 404, 'not_found'); }
}

final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Für diese Aktion fehlen die erforderlichen Rechte.') { parent::__construct($message, 403, 'forbidden'); }
}
