<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Jobs\GenerateDailyInsightJob;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RunAiAgentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'affiliate:run-ai-agent {--days=30 : Number of days of analytics to analyze}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue the autonomous AI agent to generate daily insights for all active affiliates';

    /**
     * Execute the console command.
     */
    public function handle(GeminiService $geminiService): int
    {
        if (! $geminiService->isConfigured()) {
            $this->error('GEMINI_API_KEY belum dikonfigurasi. AI Agent dilewati.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days - 1)->format('Y-m-d');
        $queued = 0;

        // Only users with traffic in the window (admins see global traffic, so always include them)
        User::query()
            ->where(function (Builder $query) use ($since) {
                $query->where('role', UserRole::ADMIN->value)
                    ->orWhereExists(function ($sub) use ($since) {
                        $sub->selectRaw('1')
                            ->from('link_click_summaries')
                            ->whereColumn('link_click_summaries.user_id', 'users.id')
                            ->where('link_click_summaries.date', '>=', $since)
                            ->where('link_click_summaries.total_clicks', '>', 0);
                    });
            })
            ->select('id')
            ->chunkById(500, function ($users) use ($days, &$queued) {
                foreach ($users as $user) {
                    GenerateDailyInsightJob::dispatch($user->id, $days);
                    $queued++;
                }
            });

        $this->info("AI Agent queued insight generation for {$queued} user(s).");

        return self::SUCCESS;
    }
}
