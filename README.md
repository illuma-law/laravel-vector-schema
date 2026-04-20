# Laravel Vector Schema

[![Tests](https://github.com/illuma-law/laravel-vector-schema/actions/workflows/run-tests.yml/badge.svg)](https://github.com/illuma-law/laravel-vector-schema/actions)
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://img.shields.io/packagist/v/illuma-law/laravel-vector-schema?label=Version)](https://packagist.org/packages/illuma-law/laravel-vector-schema)

Portable vector columns and search macros for Laravel Eloquent.

When building AI applications with RAG (Retrieval-Augmented Generation), you must store and query high-dimensional vector embeddings. However, PostgreSQL (`pgvector`), SQLite (`sqlite-vec`), SingleStore, and MySQL all use different syntax for vector data types, index creation, and cosine distance calculations.

This package provides Laravel Blueprint schema macros and Eloquent Builder macros to seamlessly abstract away these database differences, giving you a unified API for vector search across all supported databases.

## Features

- **Database Portability:** Write your migrations and vector queries once; the package compiles them to the correct syntax for your active database connection.
- **SQLite Binary Support:** Safely handles the complex float32 `BLOB` packing required by `sqlite-vec` while using native `vector` column types on Postgres/MySQL.
- **HNSW Index Support:** Provides a macro for creating high-performance Approximate Nearest Neighbor (ANN) indexes.
- **Cosine Similarity:** Provides fluent query macros to filter, sort, and select based on vector cosine similarity and distance.
- **Eloquent Cast:** Automatically serializes and deserializes PHP arrays to the correct database vector format.

## Database Support Matrix

| Database | Support Level | Requirements | Distance Function |
| :--- | :--- | :--- | :--- |
| **PostgreSQL** | Native | `pgvector` extension | `<=>` (Cosine) |
| **MySQL (9.0+)** | Native | HeatWave or Enterprise Edition | `VECTOR_DISTANCE` |
| **MariaDB (11.7+)** | Native | None | `VEC_DISTANCE_COSINE` |
| **SQL Server** | Native | Azure SQL (Preview) | `VECTOR_DISTANCE` |
| **SingleStore** | Native | None | `DOT_PRODUCT` |
| **SQLite** | Native | `sqlite-vec` extension | `vec_distance_cosine` |

*Note: MySQL Community Edition (GPL) currently restricts the `VECTOR_DISTANCE` function to HeatWave/Enterprise users. SQLite requires loading the `sqlite-vec` extension.*

## Installation

You can install the package via composer:

```bash
composer require illuma-law/laravel-vector-schema
```

The Service Provider will automatically register the `Blueprint` and `Builder` macros.

## Usage & Integration

### Schema Migrations

Use the `vectorColumn` macro to define portable vector columns. On PostgreSQL, MySQL, MariaDB, SQL Server, and SingleStore, this creates a native `vector` column. On SQLite, it safely falls back to a `BLOB` column compatible with `sqlite-vec`.

Use `hnswIndex` for high-performance approximate nearest neighbor (ANN) similarity search. This generates a native HNSW index on PostgreSQL and is gracefully ignored on other drivers.

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IllumaLaw\VectorSchema\VectorSchema;

return new class extends Migration {
    public function up(): void
    {
        // Ensures the pgvector extension is created on PostgreSQL databases
        VectorSchema::ensureExtension();

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            
            // Define 'embedding' column with 768 dimensions
            $table->vectorColumn('embedding', 768)->nullable();
            
            $table->timestamps();
        });

        // Creates an HNSW index on pgsql (ignored on others)
        Schema::table('documents', function (Blueprint $table) {
            $table->hnswIndex('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropHnswIndex('embedding');
        });
        Schema::dropIfExists('documents');
    }
};
```

### Eloquent Casts

Add the `VectorArray` cast to your Eloquent model. This handles the serialization and deserialization of plain PHP arrays into the specific formats required by your active database driver.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use IllumaLaw\VectorSchema\Casts\VectorArray;

class Document extends Model
{
    protected $casts = [
        'embedding' => VectorArray::class,
    ];
}
```

Now you can interact with the vector just like a normal array:

```php
$document = new Document();
$document->content = 'Hello world';
$document->embedding = [0.1, 0.5, -0.3, ...]; // Will be cast correctly on save
$document->save();
```

### Vector Math Utilities (`VectorProcessor`)

When dealing with high-dimensional vectors, you often need to perform common mathematical operations such as normalizing input data or averaging several vectors (e.g., to create a centroid for a cluster or a search query).

The package includes a domain-agnostic `VectorProcessor` class for these tasks.

#### `normalizeVector`

Ensures an input vector is a flat list of floats, filters out `NaN` or `INF` values, and validates the expected dimensionality. Returns an empty array if the input is invalid.

```php
use IllumaLaw\VectorSchema\Support\VectorProcessor;

$processor = new VectorProcessor();

// Returns a list<float> of length 3
$normalized = $processor->normalizeVector([0.5, '1.5', 2], expectedDimensions: 3);

if ($normalized === []) {
    // Handle invalid input or dimension mismatch
}
```

#### `averageVectors`

Computes the mathematical average (centroid) of a collection of vectors. This is useful for combining multiple embeddings into a single query vector.

```php
$processor = new VectorProcessor();

$vectors = [
    [1.0, 1.0, 1.0],
    [3.0, 3.0, 3.0],
    [NAN, 0.0, 0.0], // Automatically filtered out
];

// Returns [2.0, 2.0, 2.0]
$centroid = $processor->averageVectors($vectors, expectedDimensions: 3);
```

### Vector Querying (Semantic Search)

The package provides several macros attached to the Laravel Query Builder.

#### `whereHybridVectorSimilarTo`

Filter results to only include those that meet a minimum cosine similarity threshold. You can also ask the macro to automatically order the results by similarity (closest first).

```php
// Assuming $queryEmbedding is an array of 768 floats from your Embedding model (e.g., OpenAI)
$queryEmbedding = [...]; 

$results = Document::query()
    ->whereHybridVectorSimilarTo(
        column: 'embedding', 
        vector: $queryEmbedding, 
        minSimilarity: 0.7, 
        order: true // Automatically order by the closest match
    )
    ->take(10)
    ->get();
```

#### `selectHybridVectorDistance`

Select the raw cosine distance between a stored vector and your query vector into a dynamically named alias. Note that distance is the inverse of similarity (0.0 is an exact match).

```php
$results = Document::query()
    ->select('id', 'content')
    ->selectHybridVectorDistance('embedding', $queryEmbedding, as: 'distance')
    ->orderBy('distance', 'asc') // Closest first
    ->get();

echo $results->first()->distance;
```

#### `whereHybridVectorDistanceLessThan`

If you prefer thinking in distances rather than similarities, directly filter by the raw distance.

```php
$results = Document::query()
    ->whereHybridVectorDistanceLessThan('embedding', $queryEmbedding, maxDistance: 0.3)
    ->get();
```

#### `orderByHybridVectorDistance`

If you only need to order results by semantic proximity without filtering or selecting the specific distance value:

```php
$results = Document::query()
    ->orderByHybridVectorDistance('embedding', $queryEmbedding, 'asc')
    ->get();
```

## Testing

The package includes a comprehensive test suite using Pest.

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
