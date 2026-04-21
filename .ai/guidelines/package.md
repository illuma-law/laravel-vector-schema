---
description: Portable vector columns and cosine similarity query macros for Laravel Eloquent — pgvector, sqlite-vec, MySQL, MariaDB
---

# laravel-vector-schema

Portable vector column schema macros and Eloquent query macros for vector similarity search. Abstracts differences between pgvector, sqlite-vec, MySQL, MariaDB, SingleStore, and SQL Server.

## Namespace

`IllumaLaw\VectorSchema`

## Key Macros

- `Blueprint::vectorColumn(name, dimensions)` — portable vector column
- `Blueprint::hnswIndex(name, column)` — HNSW ANN index
- `Builder::nearestNeighbors(column, vector, limit)` — cosine similarity search
- `Builder::whereCosineSimilarity(column, vector, threshold)` — similarity filter

## Eloquent Cast

`IllumaLaw\VectorSchema\Casts\VectorCast` — serializes/deserializes PHP `float[]` to DB format.

## Schema Migration

```php
Schema::create('embeddings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('document_id');
    $table->vectorColumn('embedding', 1536); // 1536-dim for OpenAI
    $table->hnswIndex('embedding_hnsw_idx', 'embedding');
    $table->timestamps();
});
```

## Model

```php
use IllumaLaw\VectorSchema\Casts\VectorCast;

class Embedding extends Model
{
    protected $casts = [
        'embedding' => VectorCast::class,
    ];
}
```

## Querying

```php
// Find 10 most similar documents to a query vector
$similar = Embedding::query()
    ->nearestNeighbors('embedding', $queryVector, limit: 10)
    ->with('document')
    ->get();

// Filter by minimum similarity threshold
$relevant = Embedding::query()
    ->whereCosineSimilarity('embedding', $queryVector, threshold: 0.8)
    ->get();
```

## Database Support

| Database | Requirements |
|---|---|
| PostgreSQL | `pgvector` extension |
| MySQL 9.0+ | HeatWave / Enterprise |
| MariaDB 11.7+ | None |
| SQLite | `sqlite-vec` extension |
| SingleStore | None |
| SQL Server | Azure SQL (Preview) |

**Note:** For SQLite, uses binary BLOB format compatible with `sqlite-vec`. HNSW index is gracefully ignored on non-Postgres drivers.
