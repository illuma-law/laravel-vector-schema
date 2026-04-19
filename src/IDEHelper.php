<?php

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpUnused */

namespace Illuminate\Database\Schema {
    /**
     * @method \Illuminate\Database\Schema\ColumnDefinition vectorColumn(string $column, int $dimensions) Add a nullable vector column with appropriate type for the current driver (PostgreSQL, MySQL, MariaDB, SQL Server, SingleStore, or SQLite BLOB).
     * @method void hnswIndex(string $column, int $m = 16, int $efConstruction = 64) Create an HNSW index for vector similarity search (PostgreSQL only).
     * @method void dropHnswIndex(string $column) Drop an HNSW index if it exists.
     */
    class Blueprint {}
}

namespace Illuminate\Database\Query {
    /**
     * @method $this selectHybridVectorDistance(string $column, array $vector, ?string $as = null) Select the cosine distance between a vector column and a given vector array. Supports PostgreSQL, MySQL, MariaDB, SQL Server, SingleStore, and SQLite.
     * @method $this whereHybridVectorSimilarTo(string $column, array $vector, float $minSimilarity = 0.6, bool $order = true) Filter results by vector similarity. Supports PostgreSQL, MySQL, MariaDB, SQL Server, SingleStore, and SQLite.
     * @method $this whereHybridVectorDistanceLessThan(string $column, array $vector, float $maxDistance, string $boolean = 'and') Filter results by maximum cosine distance. Supports PostgreSQL, MySQL, MariaDB, SQL Server, SingleStore, and SQLite.
     * @method $this orderByHybridVectorDistance(string $column, array $vector, string $direction = 'asc') Order results by distance to a given vector array. Supports PostgreSQL, MySQL, MariaDB, SQL Server, SingleStore, and SQLite.
     */
    class Builder {}
}

namespace Illuminate\Database\Eloquent {
    /**
     * @method $this selectHybridVectorDistance(string $column, array $vector, ?string $as = null) Select the cosine distance between a vector column and a given vector array.
     * @method $this whereHybridVectorSimilarTo(string $column, array $vector, float $minSimilarity = 0.6, bool $order = true) Filter results by vector similarity.
     * @method $this whereHybridVectorDistanceLessThan(string $column, array $vector, float $maxDistance, string $boolean = 'and') Filter results by maximum cosine distance.
     * @method $this orderByHybridVectorDistance(string $column, array $vector, string $direction = 'asc') Order results by distance to a given vector array.
     */
    class Builder {}
}
