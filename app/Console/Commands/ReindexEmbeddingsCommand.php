<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLinkEmbeddingJob;
use App\Models\Link;
use App\Services\GeminiService;
use App\Support\VectorSearch;
use Illuminate\Console\Command;

class ReindexEmbeddingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'affiliate:reindex-embeddings {--all : Re-embed every link, not only links without an embedding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue embedding generation for links (backfill after changing the embedding model)';

    /**
     * Execute the console command.
     */
    public function handle(GeminiService $geminiService): int
    {
        if (! $geminiService->isConfigured()) {
            $this->error('GEMINI_API_KEY belum dikonfigurasi.');

            return self::FAILURE;
        }

        if (! VectorSearch::isAvailable()) {
            $this->error('Kolom links.embedding bukan tipe pgvector (extension "vector" tidak terpasang). Pasang pgvector lalu jalankan ulang migrasi.');

            return self::FAILURE;
        }

        $query = Link::query()->select('id');

        if (! $this->option('all')) {
            $query->whereNull('embedding');
        }

        $count = 0;

        $query->chunkById(200, function ($links) use (&$count) {
            foreach ($links as $link) {
                GenerateLinkEmbeddingJob::dispatch($link->id);
                $count++;
            }
        });

        $this->info("Queued embedding generation for {$count} link(s).");

        return self::SUCCESS;
    }
}
