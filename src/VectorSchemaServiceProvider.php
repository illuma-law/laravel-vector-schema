<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use IllumaLaw\VectorSchema\VectorHelper;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VectorSchemaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-vector-schema');
    }

    public function bootingPackage(): void
    {
        Blueprint::macro('vectorColumn', function (string $column, int $dimensions): ColumnDefinition {
            /** @var Blueprint $this */
            $driver = config('database.default');
            $driver = config("database.connections.{$driver}.driver");

            if (in_array($driver, ['pgsql', 'mysql', 'mariadb', 'sqlsrv', 'singlestore'])) {
                return $this->addColumn('vector', $column, ['length' => $dimensions]);
            }

            // SQLite (sqlite-vec): store as BLOB for vec_f32
            return $this->binary($column);
        });

        Blueprint::macro('hnswIndex', function (string $column, int $m = 16, int $efConstruction = 64): void {
            /** @var Blueprint $this */
            $table = $this->getTable();
            VectorSchema::createHnswIndex($table, $column, $m, $efConstruction);
        });

        Blueprint::macro('dropHnswIndex', function (string $column): void {
            /** @var Blueprint $this */
            $table = $this->getTable();
            VectorSchema::dropHnswIndex($table, $column);
        });

        $this->registerSelectHybridVectorDistanceMacro();
        $this->registerWhereHybridVectorSimilarToMacro();
        $this->registerWhereHybridVectorDistanceLessThanMacro();
        $this->registerOrderByHybridVectorDistanceMacro();
    }

    private function registerSelectHybridVectorDistanceMacro(): void
    {
        Builder::macro('selectHybridVectorDistance', function (string $column, array $vector, ?string $as = null): Builder {
            /** @var Builder $this */
            $driver = $this->connection->getDriverName();
            $alias = $as ?? "{$column}_distance";

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);
                $this->addBinding($vectorBlob, 'select');
                return $this->addSelect([
                    $this->raw("vec_distance_cosine({$this->grammar->wrap($column)}, ?) as {$this->grammar->wrap($alias)}"),
                ]);
            }

            if ($driver === 'mysql') {
                return $this->addSelect([
                    $this->raw("VECTOR_DISTANCE({$this->grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') as {$this->grammar->wrap($alias)}", [json_encode($vector)]),
                ]);
            }

            if ($driver === 'mariadb') {
                return $this->addSelect([
                    $this->raw("VEC_DISTANCE_COSINE({$this->grammar->wrap($column)}, VEC_FromText(?)) as {$this->grammar->wrap($alias)}", [json_encode($vector)]),
                ]);
            }

            if ($driver === 'sqlsrv') {
                return $this->addSelect([
                    $this->raw("VECTOR_DISTANCE('cosine', {$this->grammar->wrap($column)}, ?) as {$this->grammar->wrap($alias)}", [json_encode($vector)]),
                ]);
            }

            if ($driver === 'singlestore') {
                return $this->addSelect([
                    $this->raw("1 - DOT_PRODUCT({$this->grammar->wrap($column)}, JSON_ARRAY_PACK(?)) as {$this->grammar->wrap($alias)}", [json_encode($vector)]),
                ]);
            }

            $vectorLiteral = '['.implode(',', $vector).']';

            return $this->addSelect([
                $this->raw("{$column} <=> '{$vectorLiteral}'::vector as {$this->grammar->wrap($alias)}"),
            ]);
        });
    }

    private function registerWhereHybridVectorSimilarToMacro(): void
    {
        Builder::macro('whereHybridVectorSimilarTo', function (string $column, array $vector, float $minSimilarity = 0.6, bool $order = true): Builder {
            /** @var Builder $this */
            $driver = $this->connection->getDriverName();
            $maxDistance = 1 - $minSimilarity;

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);
                $this->whereRaw("vec_distance_cosine({$this->grammar->wrap($column)}, ?) <= ?", [$vectorBlob, $maxDistance]);
                if ($order) {
                    $this->orderByRaw("vec_distance_cosine({$this->grammar->wrap($column)}, ?)", [$vectorBlob]);
                }

                return $this;
            }

            if ($driver === 'mysql') {
                $this->whereRaw("VECTOR_DISTANCE({$this->grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $this->orderByRaw("VECTOR_DISTANCE({$this->grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE')", [json_encode($vector)]);
                }

                return $this;
            }

            if ($driver === 'mariadb') {
                $this->whereRaw("VEC_DISTANCE_COSINE({$this->grammar->wrap($column)}, VEC_FromText(?)) <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $this->orderByRaw("VEC_DISTANCE_COSINE({$this->grammar->wrap($column)}, VEC_FromText(?))", [json_encode($vector)]);
                }

                return $this;
            }

            if ($driver === 'sqlsrv') {
                $this->whereRaw("VECTOR_DISTANCE('cosine', {$this->grammar->wrap($column)}, ?) <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $this->orderByRaw("VECTOR_DISTANCE('cosine', {$this->grammar->wrap($column)}, ?)", [json_encode($vector)]);
                }

                return $this;
            }

            if ($driver === 'singlestore') {
                // Similarity directly from DOT_PRODUCT
                $this->whereRaw("DOT_PRODUCT({$this->grammar->wrap($column)}, JSON_ARRAY_PACK(?)) >= ?", [json_encode($vector), $minSimilarity]);
                if ($order) {
                    $this->orderByRaw("DOT_PRODUCT({$this->grammar->wrap($column)}, JSON_ARRAY_PACK(?)) DESC", [json_encode($vector)]);
                }

                return $this;
            }

            // pgvector uses distance operators directly. minSimilarity 0.6 => maxDistance 0.4.
            // pgvector distance is squared Euclidean or Cosine distance. <=> is cosine distance.
            $vectorLiteral = '['.implode(',', $vector).']';
            $this->whereRaw("{$column} <=> '{$vectorLiteral}'::vector <= ?", [$maxDistance]);
            if ($order) {
                $this->orderByRaw("{$column} <=> '{$vectorLiteral}'::vector");
            }

            return $this;
        });
    }

    private function registerWhereHybridVectorDistanceLessThanMacro(): void
    {
        Builder::macro('whereHybridVectorDistanceLessThan', function (string $column, array $vector, float $maxDistance, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $driver = $this->connection->getDriverName();
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);

                return $this->{$method}("vec_distance_cosine({$this->grammar->wrap($column)}, ?) < ?", [$vectorBlob, $maxDistance]);
            }

            if ($driver === 'mysql') {
                return $this->{$method}("VECTOR_DISTANCE({$this->grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') < ?", [json_encode($vector), $maxDistance]);
            }

            if ($driver === 'mariadb') {
                return $this->{$method}("VEC_DISTANCE_COSINE({$this->grammar->wrap($column)}, VEC_FromText(?)) < ?", [json_encode($vector), $maxDistance]);
            }

            if ($driver === 'sqlsrv') {
                return $this->{$method}("VECTOR_DISTANCE('cosine', {$this->grammar->wrap($column)}, ?) < ?", [json_encode($vector), $maxDistance]);
            }

            if ($driver === 'singlestore') {
                return $this->{$method}("1 - DOT_PRODUCT({$this->grammar->wrap($column)}, JSON_ARRAY_PACK(?)) < ?", [json_encode($vector), $maxDistance]);
            }

            $vectorLiteral = '['.implode(',', $vector).']';

            return $this->{$method}("{$column} <=> '{$vectorLiteral}'::vector < ?", [$maxDistance]);
        });
    }

    private function registerOrderByHybridVectorDistanceMacro(): void
    {
        Builder::macro('orderByHybridVectorDistance', function (string $column, array $vector, string $direction = 'asc'): Builder {
            /** @var Builder $this */
            $driver = $this->connection->getDriverName();
            $dir = ($direction === 'desc' ? ' DESC' : '');

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);

                return $this->orderByRaw("vec_distance_cosine({$this->grammar->wrap($column)}, ?)" . $dir, [$vectorBlob]);
            }

            if ($driver === 'mysql') {
                return $this->orderByRaw("VECTOR_DISTANCE({$this->grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE')" . $dir, [json_encode($vector)]);
            }

            if ($driver === 'mariadb') {
                return $this->orderByRaw("VEC_DISTANCE_COSINE({$this->grammar->wrap($column)}, VEC_FromText(?))" . $dir, [json_encode($vector)]);
            }

            if ($driver === 'sqlsrv') {
                return $this->orderByRaw("VECTOR_DISTANCE('cosine', {$this->grammar->wrap($column)}, ?)" . $dir, [json_encode($vector)]);
            }

            if ($driver === 'singlestore') {
                // ASC distance means DESC dot_product (similarity)
                $singlestoreDir = $direction === 'desc' ? ' ASC' : ' DESC';

                return $this->orderByRaw("DOT_PRODUCT({$this->grammar->wrap($column)}, JSON_ARRAY_PACK(?))" . $singlestoreDir, [json_encode($vector)]);
            }

            $vectorLiteral = '['.implode(',', $vector).']';

            return $this->orderByRaw("{$column} <=> '{$vectorLiteral}'::vector" . $dir);
        });
    }
}
