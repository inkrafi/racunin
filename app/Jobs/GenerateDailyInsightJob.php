<?php

namespace App\Jobs;

use App\Actions\AI\GenerateInsightAction;
use App\Models\AiInsight;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateDailyInsightJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    /** Prevent duplicate insights if the scheduler is triggered twice on the same day. */
    public int $uniqueFor = 43200;

    public function __construct(
        public int $userId,
        public int $days = 30,
    ) {}

    public function uniqueId(): string
    {
        return $this->userId . ':' . now()->format('Y-m-d');
    }

    public function handle(AnalyticsService $analyticsService, GenerateInsightAction $generateInsightAction): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $input = $analyticsService->getInsightInput($user->id, null, $this->days, $user->isAdmin());

        // No traffic, nothing to analyze: save the API cost
        if ($input['metrics']['total_clicks'] === 0) {
            return;
        }

        $insightData = $generateInsightAction->execute(
            $input['metrics'],
            $input['channels'],
            $input['devices'],
            $input['top_links'],
            $this->days
        );

        AiInsight::create([
            'user_id' => $user->id,
            'type' => 'daily',
            'insight_data' => $insightData,
            'is_read' => false,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Failed to generate daily AI insight for user {$this->userId}: " . $e->getMessage());
    }
}
