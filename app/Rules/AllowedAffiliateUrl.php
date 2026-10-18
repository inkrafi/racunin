<?php

namespace App\Rules;

use App\Support\AffiliateUrlPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedAffiliateUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $policy = app(AffiliateUrlPolicy::class);

        if (! is_string($value) || ! $policy->isAllowed($value)) {
            $fail('Link ini bukan dari toko yang didukung. Racunin menerima link ' . implode(', ', $policy->platformLabels()) . '.');
        }
    }
}
