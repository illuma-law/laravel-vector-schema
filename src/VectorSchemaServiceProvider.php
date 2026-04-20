<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
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
        /** @phpstan-ignore-next-line */
        Blueprint::macro('vectorColumn', function (string $column, int $dimensions): ColumnDefinition {
            /** @var mixed $self */
            $self = $this;
            $driver = config('database.default');
            $driver = config("database.connections.{$driver}.driver");

            if (in_array($driver, ['pgsql', 'mysql', 'mariadb', 'sqlsrv', 'singlestore'])) {
                return $self->addColumn('vector', $column, ['length' => $dimensions]);
            }

            // SQLite (sqlite-vec): store as BLOB for vec_f32
            return $self->binary($column);
        });

        /** @phpstan-ignore-next-line */
        Blueprint::macro('hnswIndex', function (string $column, int $m = 16, int $efConstruction = 64): void {
            /** @var mixed $self */
            $self = $this;
            $table = $self->table;
            VectorSchema::createHnswIndex($table, $column, $m, $efConstruction);
        });

        /** @phpstan-ignore-next-line */
        Blueprint::macro('dropHnswIndex', function (string $column): void {
            /** @var mixed $self */
            $self = $this;
            $table = $self->table;
            VectorSchema::dropHnswIndex($table, $column);
        });

        $this->registerSelectHybridVectorDistanceMacro();
        $this->registerWhereHybridVectorSimilarToMacro();
        $this->registerWhereHybridVectorDistanceLessThanMacro();
        $this->registerOrderByHybridVectorDistanceMacro();
    }

    private function registerSelectHybridVectorDistanceMacro(): void
    {
        /** @phpstan-ignore-next-line */
        Builder::macro('selectHybridVectorDistance', function (string $column, array $vector, ?string $as = null): Builder {
            /** @var mixed $self */
            $self = $this;
            $driver = $self->getConnection()->getDriverName();
            $alias = $as ?? "{$column}_distance";

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);
                $self->addBinding($vectorBlob, 'select');

                return $self->addSelect([
                    $self->raw("vec_distance_cosine({$self->getGrammar()->wrap($column)}, ?) as {$self->getGrammar()->wrap($alias)}"),
                ]);
            }

            if ($driver === 'mysql') {
                return $self->addSelect([
                    $self->raw("VECTOR_DISTANCE({$self->getGrammar()->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') as {$self->getGrammar()->wrap($alias)}", [json_encode($vector)]),
                ]);
            }

            if ($driver === 'mariadb') {
                return $self->addSelect([
                    $self->raw("VEC_DISTANCE_COSINE({$self->getGrammar()->wrap($column)}, VEC_FromText(?)) as {$self->getGrammar()->wrap($alias)}", [json_encode($vector)]),
                ]);
            }

            if ($driver === 'sqlsrv') {
                return $self->addSelect([
                    $self->raw("VECTOR_DISTANCE('cosine', {$self->getGrammar()->wrap($column)}, ?) as {$self->getGrammar()->wrap($alias)}", [json_encode($vector)]),
                ]);
            }

            if ($driver === 'singlestore') {
                return $self->addSelect([
                    $self->raw("1 - DOT_PRODUCT({$self->getGrammar()->wrap($column)}, JSON_ARRAY_PACK(?)) as {$self->getGrammar()->wrap($alias)}", [json_encode($vector)]),
                ]);
            }

            $vectorLiteral = '['.implode(',', $vector).']';

            return $self->addSelect([
                $self->raw("{$column} <=> '{$vectorLiteral}'::vector as {$self->getGrammar()->wrap($alias)}"),
            ]);
        });
    }

    private function registerWhereHybridVectorSimilarToMacro(): void
    {
        /** @phpstan-ignore-next-line */
        Builder::macro('whereHybridVectorSimilarTo', function (string $column, array $vector, float $minSimilarity = 0.6, bool $order = true): Builder {
            /** @var mixed $self */
            $self = $this;
            $driver = $self->getConnection()->getDriverName();
            $maxDistance = 1 - $minSimilarity;

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);
                $self->whereRaw("vec_distance_cosine({$self->getGrammar()->wrap($column)}, ?) <= ?", [$vectorBlob, $maxDistance]);
                if ($order) {
                    $self->orderByRaw("vec_distance_cosine({$self->getGrammar()->wrap($column)}, ?)", [$vectorBlob]);
                }

                return $self;
            }

            if ($driver === 'mysql') {
                $self->whereRaw("VECTOR_DISTANCE({$self->getGrammar()->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $self->orderByRaw("VECTOR_DISTANCE({$self->getGrammar()->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE')", [json_encode($vector)]);
                }

                return $self;
            }

            if ($driver === 'mariadb') {
                $self->whereRaw("VEC_DISTANCE_COSINE({$self->getGrammar()->wrap($column)}, VEC_FromText(?)) <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $self->orderByRaw("VEC_DISTANCE_COSINE({$self->getGrammar()->wrap($column)}, VEC_FromText(?))", [json_encode($vector)]);
                }

                return $self;
            }

            if ($driver === 'sqlsrv') {
                $self->whereRaw("VECTOR_DISTANCE('cosine', {$self->getGrammar()->wrap($column)}, ?) <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $self->orderByRaw("VECTOR_DISTANCE('cosine', {$self->getGrammar()->wrap($column)}, ?)", [json_encode($vector)]);
                }

                return $self;
            }

            if ($driver === 'singlestore') {
                // Similarity directly from DOT_PRODUCT
                $self->whereRaw("DOT_PRODUCT({$self->getGrammar()->wrap($column)}, JSON_ARRAY_PACK(?)) >= ?", [json_encode($vector), $minSimilarity]);
                if ($order) {
                    $self->orderByRaw("DOT_PRODUCT({$self->getGrammar()->wrap($column)}, JSON_ARRAY_PACK(?)) DESC", [json_encode($vector)]);
                }

                return $self;
            }

            // pgvector uses distance operators directly. minSimilarity 0.6 => maxDistance 0.4.
            // pgvector distance is squared Euclidean or Cosine distance. <=> is cosine distance.
            $vectorLiteral = '['.implode(',', $vector).']';
            $self->whereRaw("{$column} <=> '{$vectorLiteral}'::vector <= ?", [$maxDistance]);
            if ($order) {
                $self->orderByRaw("{$column} <=> '{$vectorLiteral}'::vector");
            }

            return $self;
        });
    }

    private function registerWhereHybridVectorDistanceLessThanMacro(): void
    {
        /** @phpstan-ignore-next-line */
        Builder::macro('whereHybridVectorDistanceLessThan', function (string $column, array $vector, float $maxDistance, string $boolean = 'and'): Builder {
            /** @var mixed $self */
            $self = $this;
            $driver = $self->getConnection()->getDriverName();
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);

                return $self->{$method}("vec_distance_cosine({$self->getGrammar()->wrap($column)}, ?) < ?", [$vectorBlob, $maxDistance]);
            }

            if ($driver === 'mysql') {
                return $self->{$method}("VECTOR_DISTANCE({$self->getGrammar()->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') < ?", [json_encode($vector), $maxDistance]);
            }

            if ($driver === 'mariadb') {
                return $self->{$method}("VEC_DISTANCE_COSINE({$self->getGrammar()->wrap($column)}, VEC_FromText(?)) < ?", [json_encode($vector), $maxDistance]);
            }

            if ($driver === 'sqlsrv') {
                return $self->{$method}("VECTOR_DISTANCE('cosine', {$self->getGrammar()->wrap($column)}, ?) < ?", [json_encode($vector), $maxDistance]);
            }

            if ($driver === 'singlestore') {
                return $self->{$method}("1 - DOT_PRODUCT({$self->getGrammar()->wrap($column)}, JSON_ARRAY_PACK(?)) < ?", [json_encode($vector), $maxDistance]);
            }

            $vectorLiteral = '['.implode(',', $vector).']';

            return $self->{$method}("{$column} <=> '{$vectorLiteral}'::vector < ?", [$maxDistance]);
        });
    }

    private function registerOrderByHybridVectorDistanceMacro(): void
    {
        /** @phpstan-ignore-next-line */
        Builder::macro('orderByHybridVectorDistance', function (string $column, array $vector, string $direction = 'asc'): Builder {
            /** @var mixed $self */
            $self = $this;
            $driver = $self->getConnection()->getDriverName();
            $dir = ($direction === 'desc' ? ' DESC' : '');

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);

                return $self->orderByRaw("vec_distance_cosine({$self->getGrammar()->wrap($column)}, ?)".$dir, [$vectorBlob]);
            }

            if ($driver === 'mysql') {
                return $self->orderByRaw("VECTOR_DISTANCE({$self->getGrammar()->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE')".$dir, [json_encode($vector)]);
            }

            if ($driver === 'mariadb') {
                return $self->orderByRaw("VEC_DISTANCE_COSINE({$self->getGrammar()->wrap($column)}, VEC_FromText(?))".$dir, [json_encode($vector)]);
            }

            if ($driver === 'sqlsrv') {
                return $self->orderByRaw("VECTOR_DISTANCE('cosine', {$self->getGrammar()->wrap($column)}, ?)".$dir, [json_encode($vector)]);
            }

            if ($driver === 'singlestore') {
                // ASC distance means DESC dot_product (similarity)
                $singlestoreDir = $direction === 'desc' ? ' ASC' : ' DESC';

                return $self->orderByRaw("DOT_PRODUCT({$self->getGrammar()->wrap($column)}, JSON_ARRAY_PACK(?))".$singlestoreDir, [json_encode($vector)]);
            }

            $vectorLiteral = '['.implode(',', $vector).']';

            return $self->orderByRaw("{$column} <=> '{$vectorLiteral}'::vector".$dir);
        });
    }
}
