<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class VectorSchema
{
    public static function ensureExtension(): void
    {
        $driver = self::driverName();

        if ($driver === 'pgsql') {
            Schema::ensureVectorExtensionExists();
        }
    }

    public static function createHnswIndex(string $table, string $column, int $m = 16, int $efConstruction = 64): void
    {
        $driver = self::driverName();

        if ($driver !== 'pgsql') {
            return;
        }

        $indexName = "{$table}_{$column}_hnsw_index";

        DB::statement(sprintf(
            'CREATE INDEX %s ON %s USING hnsw (%s vector_cosine_ops) WITH (m = %d, ef_construction = %d)',
            $indexName,
            $table,
            $column,
            $m,
            $efConstruction
        ));
    }

    public static function dropHnswIndex(string $table, string $column): void
    {
        $driver = self::driverName();

        if ($driver !== 'pgsql') {
            return;
        }

        $indexName = "{$table}_{$column}_hnsw_index";

        DB::statement("DROP INDEX IF EXISTS {$indexName}");
    }

    private static function driverName(): string
    {
        return DB::getDefaultConnection() === 'sqlite_testing'
            ? 'sqlite'
            : DB::connection()->getDriverName();
    }
}
