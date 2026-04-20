# Laravel Vector Schema

[![Tests](https://github.com/illuma-law/laravel-vector-schema/actions/workflows/run-tests.yml/badge.svg)](https://github.com/illuma-law/laravel-vector-schema/actions)
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://img.shields.io/packagist/v/illuma-law/laravel-vector-schema?label=Version)](https://packagist.org/packages/illuma-law/laravel-vector-schema)

**Portable vector columns and macros for Laravel**

This package provides Eloquent casts and Blueprint schema macros for vector search, supporting multiple databases with a single API.

- [Database Support Matrix](#database-support-matrix)
- [Installation](#installation)
- [Usage](#usage)
  - [Schema Migrations](#schema-migrations)
  - [Eloquent Casts](#eloquent-casts)
  - [Vector Querying](#vector-querying)
- [Testing](#testing)
- [Credits](#credits)
- [License](#license)

## Database Support Matrix

| Database | Support Level | Requirements | Distance Function |
| :--- | :--- | :--- | :--- |
| **PostgreSQL** | Native | `pgvector` extension | `<=>` (Cosine) |
| **MySQL (9.0+)** | Native | HeatWave or Enterprise Edition | `VECTOR_DISTANCE` |
| **MariaDB (11.7+)** | Native | None | `VEC_DISTANCE_COSINE` |
| **SQL Server** | Native | Azure SQL (Preview) | `VECTOR_DISTANCE` |
| **SingleStore** | Native | None | `DOT_PRODUCT` |
| **SQLite** | Native | `sqlite-vec` extension | `vec_distance_cosine` |

*Note: MySQL Community Edition (GPL) currently restricts the `VECTOR_DISTANCE` function to HeatWave/Enterprise users.*

## Installation

Require this package with composer using the following command:

```bash
composer require illuma-law/laravel-vector-schema
```

## Usage

### TL;DR

Create a vector column in your migration:
```php
$table->vectorColumn('embedding', 768)->nullable();
$table->hnswIndex('embedding');
```

Add the cast to your model:
```php
protected $casts = ['embedding' => VectorArray::class];
```

Search for similar vectors:
```php
$results = Document::query()
    ->whereHybridVectorSimilarTo('embedding', $queryVector, minSimilarity: 0.7)
    ->get();
```

### Schema Migrations

This package introduces macros to Laravel's `Blueprint` class, allowing you to define vector columns that automatically adapt to your database driver.

Use the `vectorColumn` macro to define portable vector columns. On PostgreSQL, MySQL, MariaDB, SQL Server, and SingleStore, this creates a native `vector` column. On SQLite, it safely falls back to a `BLOB` column compatible with `sqlite-vec`.

Use `hnswIndex` for high-performance approximate nearest neighbor (ANN) similarity search on PostgreSQL (this is gracefully ignored on other drivers).

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IllumaLaw\VectorSchema\VectorSchema;

return new class extends Migration {
    public function up(): void
    {
        // Automatically ensures pgvector exists on PostgreSQL
        VectorSchema::ensureExtension();

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            
            // Defines 'vector' type natively, or 'BLOB' on sqlite
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

Add the `VectorArray` cast to your model. This handles the serialization and deserialization of PHP arrays to the specific formats required by the database (e.g. vector string representation or SQLite's binary BLOB).

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

### Vector Querying

The package provides several Query and Eloquent Builder macros to perform vector mathematics and similarity searches portably.

#### `whereHybridVectorSimilarTo`

Filter results to only include those that meet a minimum cosine similarity threshold. You can also automatically order the results by similarity (closest first).

```php
$queryEmbedding = [...]; // Array of 768 floats

$results = Document::query()
    ->whereHybridVectorSimilarTo(
        column: 'embedding', 
        vector: $queryEmbedding, 
        minSimilarity: 0.7, 
        order: true
    )
    ->get();
```

#### `selectHybridVectorDistance`

Select the raw cosine distance between a stored vector and your query vector into a dynamically named alias. Distance is the inverse of similarity (where 0.0 is an exact match).

```php
$results = Document::query()
    ->select('id', 'content')
    ->selectHybridVectorDistance('embedding', $queryEmbedding, as: 'distance')
    ->orderBy('distance')
    ->get();

echo $results->first()->distance;
```

#### `whereHybridVectorDistanceLessThan`

For advanced use cases, directly filter by the raw distance.

```php
$results = Document::query()
    ->whereHybridVectorDistanceLessThan('embedding', $queryEmbedding, maxDistance: 0.3)
    ->get();
```

#### `orderByHybridVectorDistance`

If you only need to order results by distance without filtering or selecting the distance value:

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

For SQLite testing, ensure `sqlite-vec` is available in your environment. The CI workflow handles this automatically by downloading the pre-compiled `vec0.so` extension for Linux.

## Credits

- [illuma-law](https://github.com/illuma-law)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
