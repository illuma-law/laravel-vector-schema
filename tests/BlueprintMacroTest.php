<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

it('registers the vectorColumn macro on blueprint', function () {
    /** @var mixed $blueprintClass */
    $blueprintClass = Blueprint::class;
    expect($blueprintClass::hasMacro('vectorColumn'))->toBe(true);
});

it('can create a vector column in a migration on sqlite', function () {
    Schema::create('test_vectors_sqlite', function (Blueprint $table) {
        $table->id();
        $table->vectorColumn('embedding', 1536);
    });

    expect(Schema::hasColumn('test_vectors_sqlite', 'embedding'))->toBe(true);
});

it('uses vectorWithDimensions type for pgsql, mysql, mariadb, sqlsrv', function (string $driver) {
    Config::set('database.default', $driver);
    Config::set("database.connections.{$driver}.driver", $driver);
    Config::set("database.connections.{$driver}.database", 'test');

    /** @var Connection&MockInterface $connection */
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn($driver);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new PostgresGrammar($connection));

    $blueprint = new Blueprint($connection, 'test_table');
    /** @var mixed $column */
    $column = $blueprint->vectorColumn('embedding', 1536);

    expect($column->type)->toBe('vectorWithDimensions');
    expect($column->dimensions)->toBe(1536);
})->with(['pgsql', 'mysql', 'mariadb', 'sqlsrv']);

it('uses vector type for singlestore', function () {
    $driver = 'singlestore';
    Config::set('database.default', $driver);
    Config::set("database.connections.{$driver}.driver", $driver);
    Config::set("database.connections.{$driver}.database", 'test');

    /** @var Connection&MockInterface $connection */
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn($driver);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new PostgresGrammar($connection));

    DB::shouldReceive('connection')->andReturn($connection);

    $blueprint = new Blueprint($connection, 'test_table');
    /** @var mixed $column */
    $column = $blueprint->vectorColumn('embedding', 1536);

    expect($column->type)->toBe('vector');
    expect($column->length)->toBe(1536);
});

it('can call hnswIndex macro', function () {
    Schema::create('test_hnsw', function (Blueprint $table) {
        $table->id();
        $table->vectorColumn('embedding', 3);
        $table->hnswIndex('embedding');
    });

    expect(true)->toBe(true);
});

it('can call dropHnswIndex macro', function () {
    Schema::table('test_hnsw', function (Blueprint $table) {
        $table->dropHnswIndex('embedding');
    });

    expect(true)->toBe(true);
});
