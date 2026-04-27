<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\MariaDbGrammar;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Database\Schema\Grammars\SqlServerGrammar;
use Illuminate\Support\Facades\DB;
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
        $this->registerGrammarMacros();

        /** @var mixed $blueprintClass */
        $blueprintClass = Blueprint::class;
        $blueprintClass::macro('vectorColumn', function (string $column, int $dimensions): ColumnDefinition {
            /** @var mixed $self */
            $self = $this;
            if (! $self instanceof Blueprint) {
                /** @var ColumnDefinition $dummy */
                $dummy = new class extends ColumnDefinition {};

                return $dummy;
            }

            /** @var string $driver */
            $driver = (string) DB::connection()->getDriverName();

            if (in_array($driver, ['pgsql', 'mysql', 'mariadb', 'sqlsrv'])) {
                /** @var ColumnDefinition $def */
                $def = $self->addColumn('vectorWithDimensions', $column, compact('dimensions'));

                return $def;
            }

            if ($driver === 'singlestore') {
                /** @var ColumnDefinition $res */
                $res = $self->addColumn('vector', $column, ['length' => $dimensions]);

                return $res;
            }

            /** @var ColumnDefinition $res */
            $res = $self->binary($column);

            return $res;
        });

        $blueprintClass::macro('hnswIndex', function (string $column, int $m = 16, int $efConstruction = 64): void {
            /** @var mixed $self */
            $self = $this;
            if (! $self instanceof Blueprint) {
                return;
            }

            /** @var mixed $tableProp */
            $tableProp = (new \ReflectionClass($self))->getProperty('table')->getValue($self);
            /** @var string $table */
            $table = (string) $tableProp;
            VectorSchema::createHnswIndex($table, $column, $m, $efConstruction);
        });

        $blueprintClass::macro('dropHnswIndex', function (string $column): void {
            /** @var mixed $self */
            $self = $this;
            if (! $self instanceof Blueprint) {
                return;
            }

            /** @var mixed $tableProp */
            $tableProp = (new \ReflectionClass($self))->getProperty('table')->getValue($self);
            /** @var string $table */
            $table = (string) $tableProp;
            VectorSchema::dropHnswIndex($table, $column);
        });

        $this->registerSelectHybridVectorDistanceMacro();
        $this->registerWhereHybridVectorSimilarToMacro();
        $this->registerWhereHybridVectorDistanceLessThanMacro();
        $this->registerOrderByHybridVectorDistanceMacro();
    }

    private function registerGrammarMacros(): void
    {
        $grammars = [
            PostgresGrammar::class,
            MySqlGrammar::class,
            MariaDbGrammar::class,
            SqlServerGrammar::class,
        ];

        foreach ($grammars as $grammar) {
            if (class_exists($grammar)) {
                /** @var mixed $grammar */
                $grammar::macro('typeVectorWithDimensions', function ($column) {
                    /** @var mixed $column */
                    $dimensions = isset($column->dimensions) ? (string) $column->dimensions : '0';

                    return "vector({$dimensions})";
                });
            }
        }
    }

    private function registerSelectHybridVectorDistanceMacro(): void
    {
        /** @var mixed $builderClass */
        $builderClass = Builder::class;
        $builderClass::macro('selectHybridVectorDistance', function (string $column, array $vector, ?string $as = null): Builder {
            /** @var mixed $self */
            $self = $this;
            if (! $self instanceof Builder) {
                /** @var Builder $dummy */
                $dummy = DB::table('dummy');

                return $dummy;
            }

            /** @var string $driver */
            $driver = (string) $self->getConnection()->getDriverName();
            $alias = $as ?? "{$column}_distance";

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);
                $self->addBinding($vectorBlob, 'select');

                /** @var mixed $grammar */
                $grammar = $self->getGrammar();

                /** @var Builder $res */
                $res = $self->addSelect([
                    $self->raw("vec_distance_cosine({$grammar->wrap($column)}, ?) as {$grammar->wrap($alias)}"),
                ]);

                return $res;
            }

            if ($driver === 'mysql') {
                /** @var mixed $grammar */
                $grammar = $self->getGrammar();

                /** @var Builder $res */
                $res = $self->addSelect([
                    $self->raw("VECTOR_DISTANCE({$grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') as {$grammar->wrap($alias)}", [json_encode($vector)]),
                ]);

                return $res;
            }

            if ($driver === 'mariadb') {
                /** @var mixed $grammar */
                $grammar = $self->getGrammar();

                /** @var Builder $res */
                $res = $self->addSelect([
                    $self->raw("VEC_DISTANCE_COSINE({$grammar->wrap($column)}, VEC_FromText(?)) as {$grammar->wrap($alias)}", [json_encode($vector)]),
                ]);

                return $res;
            }

            if ($driver === 'sqlsrv') {
                /** @var mixed $grammar */
                $grammar = $self->getGrammar();

                /** @var Builder $res */
                $res = $self->addSelect([
                    $self->raw("VECTOR_DISTANCE('cosine', {$grammar->wrap($column)}, ?) as {$grammar->wrap($alias)}", [json_encode($vector)]),
                ]);

                return $res;
            }

            if ($driver === 'singlestore') {
                /** @var mixed $grammar */
                $grammar = $self->getGrammar();

                /** @var Builder $res */
                $res = $self->addSelect([
                    $self->raw("1 - DOT_PRODUCT({$grammar->wrap($column)}, JSON_ARRAY_PACK(?)) as {$grammar->wrap($alias)}", [json_encode($vector)]),
                ]);

                return $res;
            }

            $vectorLiteral = '['.implode(',', array_map(fn ($v) => (string) $v, $vector)).']';

            /** @var mixed $grammar */
            $grammar = $self->getGrammar();

            /** @var Builder $res */
            $res = $self->addSelect([
                $self->raw("{$column} <=> '{$vectorLiteral}'::vector as {$grammar->wrap($alias)}"),
            ]);

            return $res;
        });
    }

    private function registerWhereHybridVectorSimilarToMacro(): void
    {
        /** @var mixed $builderClass */
        $builderClass = Builder::class;
        $builderClass::macro('whereHybridVectorSimilarTo', function (string $column, array $vector, float $minSimilarity = 0.6, bool $order = true): Builder {
            /** @var mixed $self */
            $self = $this;
            if (! $self instanceof Builder) {
                /** @var Builder $dummy */
                $dummy = DB::table('dummy');

                return $dummy;
            }

            /** @var string $driver */
            $driver = (string) $self->getConnection()->getDriverName();
            $maxDistance = 1 - $minSimilarity;

            /** @var mixed $grammar */
            $grammar = $self->getGrammar();

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);
                $self->whereRaw("vec_distance_cosine({$grammar->wrap($column)}, ?) <= ?", [$vectorBlob, $maxDistance]);
                if ($order) {
                    $self->orderByRaw("vec_distance_cosine({$grammar->wrap($column)}, ?)", [$vectorBlob]);
                }

                return $self;
            }

            if ($driver === 'mysql') {
                $self->whereRaw("VECTOR_DISTANCE({$grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $self->orderByRaw("VECTOR_DISTANCE({$grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE')", [json_encode($vector)]);
                }

                return $self;
            }

            if ($driver === 'mariadb') {
                $self->whereRaw("VEC_DISTANCE_COSINE({$grammar->wrap($column)}, VEC_FromText(?)) <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $self->orderByRaw("VEC_DISTANCE_COSINE({$grammar->wrap($column)}, VEC_FromText(?))", [json_encode($vector)]);
                }

                return $self;
            }

            if ($driver === 'sqlsrv') {
                $self->whereRaw("VECTOR_DISTANCE('cosine', {$grammar->wrap($column)}, ?) <= ?", [json_encode($vector), $maxDistance]);
                if ($order) {
                    $self->orderByRaw("VECTOR_DISTANCE('cosine', {$grammar->wrap($column)}, ?)", [json_encode($vector)]);
                }

                return $self;
            }

            if ($driver === 'singlestore') {
                $self->whereRaw("DOT_PRODUCT({$grammar->wrap($column)}, JSON_ARRAY_PACK(?)) >= ?", [json_encode($vector), $minSimilarity]);
                if ($order) {
                    $self->orderByRaw("DOT_PRODUCT({$grammar->wrap($column)}, JSON_ARRAY_PACK(?)) DESC", [json_encode($vector)]);
                }

                return $self;
            }

            $vectorLiteral = '['.implode(',', array_map(fn ($v) => (string) $v, $vector)).']';
            $self->whereRaw("{$column} <=> '{$vectorLiteral}'::vector <= ?", [$maxDistance]);
            if ($order) {
                $self->orderByRaw("{$column} <=> '{$vectorLiteral}'::vector");
            }

            return $self;
        });
    }

    private function registerWhereHybridVectorDistanceLessThanMacro(): void
    {
        /** @var mixed $builderClass */
        $builderClass = Builder::class;
        $builderClass::macro('whereHybridVectorDistanceLessThan', function (string $column, array $vector, float $maxDistance, string $boolean = 'and'): Builder {
            /** @var mixed $self */
            $self = $this;
            if (! $self instanceof Builder) {
                /** @var Builder $dummy */
                $dummy = DB::table('dummy');

                return $dummy;
            }

            /** @var string $driver */
            $driver = (string) $self->getConnection()->getDriverName();
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';

            /** @var mixed $grammar */
            $grammar = $self->getGrammar();

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);

                /** @var Builder $res */
                $res = $self->{$method}("vec_distance_cosine({$grammar->wrap($column)}, ?) < ?", [$vectorBlob, $maxDistance]);

                return $res;
            }

            if ($driver === 'mysql') {
                /** @var Builder $res */
                $res = $self->{$method}("VECTOR_DISTANCE({$grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE') < ?", [json_encode($vector), $maxDistance]);

                return $res;
            }

            if ($driver === 'mariadb') {
                /** @var Builder $res */
                $res = $self->{$method}("VEC_DISTANCE_COSINE({$grammar->wrap($column)}, VEC_FromText(?)) < ?", [json_encode($vector), $maxDistance]);

                return $res;
            }

            if ($driver === 'sqlsrv') {
                /** @var Builder $res */
                $res = $self->{$method}("VECTOR_DISTANCE('cosine', {$grammar->wrap($column)}, ?) < ?", [json_encode($vector), $maxDistance]);

                return $res;
            }

            if ($driver === 'singlestore') {
                /** @var Builder $res */
                $res = $self->{$method}("1 - DOT_PRODUCT({$grammar->wrap($column)}, JSON_ARRAY_PACK(?)) < ?", [json_encode($vector), $maxDistance]);

                return $res;
            }

            $vectorLiteral = '['.implode(',', array_map(fn ($v) => (string) $v, $vector)).']';

            /** @var Builder $res */
            $res = $self->{$method}("{$column} <=> '{$vectorLiteral}'::vector < ?", [$maxDistance]);

            return $res;
        });
    }

    private function registerOrderByHybridVectorDistanceMacro(): void
    {
        /** @var mixed $builderClass */
        $builderClass = Builder::class;
        $builderClass::macro('orderByHybridVectorDistance', function (string $column, array $vector, string $direction = 'asc'): Builder {
            /** @var mixed $self */
            $self = $this;
            if (! $self instanceof Builder) {
                /** @var Builder $dummy */
                $dummy = DB::table('dummy');

                return $dummy;
            }

            /** @var string $driver */
            $driver = (string) $self->getConnection()->getDriverName();
            $dir = ($direction === 'desc' ? ' DESC' : '');

            /** @var mixed $grammar */
            $grammar = $self->getGrammar();

            if ($driver === 'sqlite') {
                $vectorBlob = VectorHelper::toBlob($vector);

                /** @var Builder $res */
                $res = $self->orderByRaw("vec_distance_cosine({$grammar->wrap($column)}, ?)".$dir, [$vectorBlob]);

                return $res;
            }

            if ($driver === 'mysql') {
                /** @var Builder $res */
                $res = $self->orderByRaw("VECTOR_DISTANCE({$grammar->wrap($column)}, STRING_TO_VECTOR(?), 'COSINE')".$dir, [json_encode($vector)]);

                return $res;
            }

            if ($driver === 'mariadb') {
                /** @var Builder $res */
                $res = $self->orderByRaw("VEC_DISTANCE_COSINE({$grammar->wrap($column)}, VEC_FromText(?))".$dir, [json_encode($vector)]);

                return $res;
            }

            if ($driver === 'sqlsrv') {
                /** @var Builder $res */
                $res = $self->orderByRaw("VECTOR_DISTANCE('cosine', {$grammar->wrap($column)}, ?)".$dir, [json_encode($vector)]);

                return $res;
            }

            if ($driver === 'singlestore') {
                $singlestoreDir = $direction === 'desc' ? ' ASC' : ' DESC';

                /** @var Builder $res */
                $res = $self->orderByRaw("DOT_PRODUCT({$grammar->wrap($column)}, JSON_ARRAY_PACK(?))".$singlestoreDir, [json_encode($vector)]);

                return $res;
            }

            $vectorLiteral = '['.implode(',', array_map(fn ($v) => (string) $v, $vector)).']';

            /** @var Builder $res */
            $res = $self->orderByRaw("{$column} <=> '{$vectorLiteral}'::vector".$dir);

            return $res;
        });
    }
}
