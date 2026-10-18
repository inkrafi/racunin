<?php

namespace App\Jobs;

use App\Models\Link;
use App\Services\GeminiService;
use App\Support\VectorSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateLinkEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $linkId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GeminiService $geminiService): void
    {
        // Semantic search is optional; without an API key or pgvector the catalog falls back to keyword search,
        // so don't spend API quota on embeddings that can't be queried.
        if (! $geminiService->isConfigured() || ! VectorSearch::isAvailable()) {
            return;
        }

        $link = Link::find($this->linkId);

        if (! $link) {
            return;
        }

        try {
            $textToEmbed = "Produk: {$link->title}\n";
            $textToEmbed .= "Kategori: {$link->category}\n";
            if ($link->discount_tag) {
                $textToEmbed .= "Tag Promo: {$link->discount_tag}\n";
            }
            if ($link->description) {
                $textToEmbed .= "Deskripsi: {$link->description}\n";
            }

            $embedding = $geminiService->generateEmbedding($textToEmbed, GeminiService::TASK_DOCUMENT);

            if (empty($embedding)) {
                Log::warning("GenerateLinkEmbeddingJob: Empty embedding returned for Link ID {$link->id}");
                return;
            }

            // Update directly using DB facade since we are writing vector syntax
            DB::table('links')
                ->where('id', $link->id)
                ->update([
                    'embedding' => json_encode($embedding)
                ]);
        } catch (\Exception $e) {
            Log::error("GenerateLinkEmbeddingJob failed for Link ID {$this->linkId}: " . $e->getMessage());
            throw $e;
        }
    }
}
