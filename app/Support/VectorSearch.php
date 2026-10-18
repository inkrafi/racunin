<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class VectorSearch
{
    protected static ?bool $available = null;

    /**
     * Whether `links.embedding` is a pgvector column. Environments without the
     * pgvector extension fall back to a jsonb column (see the pgvector
     * migration), where similarity operators do not exist.
     */
    public static function isAvailable(): bool
    {
        if (static::$available !== null) {
            return static::$available;
        }

        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return static::$available = false;
            }

            $type = DB::table('information_schema.columns')
                ->where('table_schema', DB::raw('current_schema()'))
                ->where('table_name', 'links')
                ->where('column_name', 'embedding')
                ->value('udt_name');

            return static::$available = ($type === 'vector');
        } catch (\Throwable $e) {
            return static::$available = false;
        }
    }

    /**
     * Force the result, or pass null to re-detect (used by tests).
     */
    public static function override(?bool $available): void
    {
        static::$available = $available;
    }
}
