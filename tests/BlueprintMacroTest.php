<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

it('registers the vectorColumn macro on blueprint', function () {
    expect(Blueprint::hasMacro('vectorColumn'))->toBeTrue();
});

it('can create a vector column in a migration on sqlite', function () {
    Schema::create('test_vectors_sqlite', function (Blueprint $table) {
        $table->id();
        $table->vectorColumn('embedding', 1536);
    });

    expect(Schema::hasColumn('test_vectors_sqlite', 'embedding'))->toBeTrue();
});

it('uses vectorWithDimensions type for pgsql, mysql, mariadb, sqlsrv', function (string $driver) {
    Config::set('database.default', $driver);
    Config::set("database.connections.{$driver}.driver", $driver);
    Config::set("database.connections.{$driver}.database", 'test');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn($driver);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new PostgresGrammar($connection));

    $blueprint = new Blueprint($connection, 'test_table');
    $column = $blueprint->vectorColumn('embedding', 1536);

    expect($column->type)->toBe('vectorWithDimensions');
    expect($column->dimensions)->toBe(1536);
})->with(['pgsql', 'mysql', 'mariadb', 'sqlsrv']);

it('uses vector type for singlestore', function () {
    $driver = 'singlestore';
    Config::set('database.default', $driver);
    Config::set("database.connections.{$driver}.driver", $driver);
    Config::set("database.connections.{$driver}.database", 'test');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn($driver);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new PostgresGrammar($connection));

    \Illuminate\Support\Facades\DB::shouldReceive('connection')->andReturn($connection);

    $blueprint = new Blueprint($connection, 'test_table');
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

    expect(true)->toBeTrue(); // If no exception, it's fine (it doesn't do anything on sqlite)
});

it('can call dropHnswIndex macro', function () {
    Schema::table('test_hnsw', function (Blueprint $table) {
        $table->dropHnswIndex('embedding');
    });

    expect(true)->toBeTrue();
});
