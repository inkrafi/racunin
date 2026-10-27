<?php

namespace App\Livewire\Concerns;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Shared per-user quota for the dashboard AI tools (beautify, categorize, captions).
 */
trait LimitsAiUsage
{
    /**
     * Consume one AI request from the user's quota.
     *
     * @return string|null An error message when the quota is exhausted, null when allowed.
     */
    protected function consumeAiQuota(): ?string
    {
        $rateLimitKey = $this->aiQuotaKey();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 15)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return "Batas kuota fitur AI tercapai sementara. Harap tunggu {$seconds} detik lagi.";
        }

        RateLimiter::hit($rateLimitKey, 600);

        return null;
    }

    /**
     * Give back the request taken by consumeAiQuota() when the AI call failed.
     */
    protected function refundAiQuota(): void
    {
        if (RateLimiter::attempts($this->aiQuotaKey()) > 0) {
            RateLimiter::decrement($this->aiQuotaKey(), 600);
        }
    }

    protected function aiQuotaKey(): string
    {
        return 'ai-overview:' . Auth::id();
    }

    /**
     * Log an AI failure and turn it into a message the user can act on
     * (raw API errors are noise to users and may expose internals).
     */
    protected function aiFailureMessage(\Throwable $e, string $task): string
    {
        Log::warning("AI task failed ({$task}): " . $e->getMessage());

        if (! app(GeminiService::class)->isConfigured()) {
            return 'Fitur AI belum aktif. Admin perlu mengisi GEMINI_API_KEY di pengaturan server.';
        }

        return "{$task} belum berhasil karena layanan AI tidak merespons. Coba lagi dalam beberapa saat.";
    }
}
