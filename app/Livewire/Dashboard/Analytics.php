<?php

namespace App\Livewire\Dashboard;

use App\Actions\AI\GenerateInsightAction;
use App\Models\AiInsight;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use App\Livewire\Concerns\LimitsAiUsage;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Statistik — Racunin')]
class Analytics extends Component
{
    use LimitsAiUsage;

    #[Url]
    public int $days = 30;

    #[Url]
    public ?int $linkId = null;

    // AI Insight state
    public ?array $aiInsight = null;
    public ?AiInsight $aiInsightModel = null;
    public bool $aiInsightLoading = false;
    public ?string $aiInsightError = null;

    public function mount(): void
    {
        $this->loadLatestInsight();
    }

    public function loadLatestInsight(): void
    {
        $latest = AiInsight::where('user_id', Auth::id())
            ->latest()
            ->first();

        if ($latest) {
            $this->aiInsightModel = $latest;
            $this->aiInsight = $latest->insight_data;
            
            if (!$latest->is_read) {
                $latest->update(['is_read' => true]);
            }
        }
    }

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $this->aiInsight = null; // Reset insight when date range changes
    }

    public function selectLink(?int $linkId): void
    {
        $this->linkId = $linkId ? (int) $linkId : null;
        // Don't reset AI insight since it's global for the user now
    }

    /**
     * AI Tool 4: Generate traffic insight and selling recommendations.
     */
    public function generateAiInsight(
        AnalyticsService $analyticsService,
        GenerateInsightAction $action
    ): void {
        $user = Auth::user();

        // Rate limiting: max 5 attempts per 10 minutes per user
        $rateLimitKey = 'ai-insight:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->aiInsightError = "Terlalu banyak permintaan analisis AI. Silakan tunggu {$seconds} detik lagi.";
            return;
        }

        RateLimiter::hit($rateLimitKey, 600);

        $this->aiInsightLoading = true;
        $this->aiInsightError = null;

        $isAdmin = $user->isAdmin();

        try {
            $input = $analyticsService->getInsightInput($user->id, $this->linkId, $this->days, $isAdmin);

            $this->aiInsight = $action->execute(
                $input['metrics'],
                $input['channels'],
                $input['devices'],
                $input['top_links'],
                $this->days
            );

            // Save to DB
            $this->aiInsightModel = AiInsight::create([
                'user_id' => $user->id,
                'type' => 'on_demand',
                'insight_data' => $this->aiInsight,
                'is_read' => true,
            ]);

            $this->dispatch('toast', message: 'Saran AI diperbarui.');
        } catch (\Exception $e) {
            $this->aiInsightError = $this->aiFailureMessage($e, 'Analisis');
        } finally {
            $this->aiInsightLoading = false;
        }
    }

    public function render(AnalyticsService $analyticsService)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $analytics = $analyticsService->getDashboardAnalytics(
            $user->id,
            $this->linkId,
            $this->days,
            $isAdmin
        );

        $topLinks = $analyticsService->getTopLinks(
            $user->id,
            10,
            $this->days,
            $isAdmin
        );

        $userLinks = $analyticsService->getUserLinksForFilter(
            $user->id,
            $isAdmin
        );

        return view('livewire.dashboard.analytics', [
            'user' => $user,
            'isAdmin' => $isAdmin,
            'linkId' => $this->linkId,
            'days' => $this->days,
            'aiInsight' => $this->aiInsight,
            'aiInsightModel' => $this->aiInsightModel,
            'aiInsightLoading' => $this->aiInsightLoading,
            'aiInsightError' => $this->aiInsightError,
            'metrics' => $analytics['metrics'],
            'trends' => $analytics['trends'],
            'maxDailyClicks' => $analytics['max_daily_clicks'],
            'channels' => $analytics['channels'],
            'devices' => $analytics['devices'],
            'topLinks' => $topLinks,
            'userLinks' => $userLinks,
        ]);
    }
}
