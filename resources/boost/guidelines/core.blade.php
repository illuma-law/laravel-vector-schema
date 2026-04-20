# illuma-law/laravel-vector-schema

Portable vector columns and macros for Laravel. Supports PostgreSQL (pgvector), MySQL (HeatWave), MariaDB, SQL Server, SingleStore, and SQLite (sqlite-vec).

## Schema Migrations

```php
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    // Portable vector column (native vector or BLOB on sqlite)
    $table->vectorColumn('embedding', 768)->nullable();
});

// High-performance HNSW index (pgsql only, ignored on others)
Schema::table('documents', function (Blueprint $table) {
    $table->hnswIndex('embedding');
});
```

## Eloquent Casts

```php
use IllumaLaw\VectorSchema\Casts\VectorArray;

class Document extends Model {
    protected $casts = ['embedding' => VectorArray::class];
}
```

## Vector Querying

### Similarity Filter

```php
$results = Document::query()
    ->whereHybridVectorSimilarTo('embedding', $queryVector, minSimilarity: 0.7, order: true)
    ->get();
```

### Distance Selection

```php
$results = Document::query()
    ->selectHybridVectorDistance('embedding', $queryVector, as: 'distance')
    ->orderBy('distance')
    ->get();
```

### Distance Ordering

```php
$results = Document::query()->orderByHybridVectorDistance('embedding', $queryVector)->get();
```

## Database Requirements

- **PostgreSQL**: `pgvector` extension.
- **SQLite**: `sqlite-vec` extension.
- **MySQL**: HeatWave or Enterprise Edition (for `VECTOR_DISTANCE`).
