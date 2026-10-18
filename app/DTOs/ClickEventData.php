<?php

namespace App\DTOs;

use Illuminate\Http\Request;

readonly class ClickEventData
{
    public function __construct(
        public ?string $ipAddress,
        public ?string $referrer,
        public ?string $userAgent,
        public \DateTimeInterface $clickedAt,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            ipAddress: $request->ip(),
            referrer: $request->header('referer'),
            userAgent: $request->header('user-agent'),
            clickedAt: now(),
        );
    }
}
