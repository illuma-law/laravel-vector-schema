<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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

it('uses vector type for supported databases', function () {
    Config::set('database.default', 'pgsql');
    Config::set('database.connections.pgsql.driver', 'pgsql');

    $connection = Mockery::mock(\Illuminate\Database\Connection::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new \Illuminate\Database\Schema\Grammars\PostgresGrammar($connection));
    
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
