<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Approximate-nearest-neighbour index for semantic catalog search.
     * Skipped where links.embedding is the jsonb fallback (no pgvector).
     */
    public function up(): void
    {
        if (! $this->embeddingIsVector()) {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS links_embedding_hnsw_idx ON links USING hnsw (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS links_embedding_hnsw_idx');
    }

    protected function embeddingIsVector(): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', 'links')
            ->where('column_name', 'embedding')
            ->value('udt_name') === 'vector';
    }
};
