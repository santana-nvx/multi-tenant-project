<?php

namespace App\DTOs\Gateway;

readonly class GatewayAuthResult
{
    public function __construct(
        public bool $status,
        public ?string $auth = null,
        public ?string $message = null) {}

    public static function fromResponse(array $data)
    {
        return new self(
            status: (bool) ($data['status'] ?? false),
            auth: $data['auth'] ?? null,
            message: $data['message'] ?? null,
        );
    }

    public function succeeded(): bool
    {
        return $this->status && ! empty($this->auth);
    }
}
