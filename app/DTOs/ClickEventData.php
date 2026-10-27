<?php

namespace App\DTOs;

use Illuminate\Http\Request;

readonly class ClickEventData
{
    /** UTM parameters captured from the short link's query string, keyed without the "utm_" prefix. */
    public const UTM_KEYS = ['source', 'medium', 'campaign', 'content'];

    /**
     * @param  array<string, string>  $utm  Subset of UTM_KEYS => value
     */
    public function __construct(
        public ?string $ipAddress,
        public ?string $referrer,
        public ?string $userAgent,
        public \DateTimeInterface $clickedAt,
        public array $utm = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            ipAddress: $request->ip(),
            referrer: $request->header('referer'),
            userAgent: $request->header('user-agent'),
            clickedAt: now(),
            utm: self::utmFromQuery($request->query()),
        );
    }

    /**
     * Public input: keep only string values, trimmed and capped to the column length.
     *
     * @return array<string, string>
     */
    public static function utmFromQuery(array $query): array
    {
        $utm = [];

        foreach (self::UTM_KEYS as $key) {
            $value = $query["utm_{$key}"] ?? null;

            if (is_string($value) && ($value = mb_substr(trim($value), 0, 100)) !== '') {
                $utm[$key] = $value;
            }
        }

        return $utm;
    }
}
