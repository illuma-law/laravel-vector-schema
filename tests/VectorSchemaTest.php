<?php

use IllumaLaw\VectorSchema\VectorSchema;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

it('ensures vector extension on postgres', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');

    $schemaBuilder = Mockery::mock();
    $schemaBuilder->shouldReceive('ensureVectorExtensionExists')->once();
    $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);

    DB::shouldReceive('connection')->andReturn($connection);
    DB::shouldReceive('getDefaultConnection')->andReturn('pgsql');

    VectorSchema::ensureExtension();
});

it('does not ensure extension on other drivers', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('sqlite');

    $schemaBuilder = Mockery::mock();
    $schemaBuilder->shouldNotReceive('ensureVectorExtensionExists');
    $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);

    DB::shouldReceive('connection')->andReturn($connection);
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');

    VectorSchema::ensureExtension();
});

it('creates hnsw index on postgres', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');

    DB::shouldReceive('connection')->andReturn($connection);
    DB::shouldReceive('getDefaultConnection')->andReturn('pgsql');

    DB::shouldReceive('statement')->once()->with(Mockery::on(function ($sql) {
        return str_contains($sql, 'CREATE INDEX test_table_embedding_hnsw_index ON test_table USING hnsw');
    }));

    VectorSchema::createHnswIndex('test_table', 'embedding');
});

it('does not create hnsw index on other drivers', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('sqlite');

    DB::shouldReceive('connection')->andReturn($connection);
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');

    DB::shouldNotReceive('statement');

    VectorSchema::createHnswIndex('test_table', 'embedding');
});

it('drops hnsw index on postgres', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');

    DB::shouldReceive('connection')->andReturn($connection);
    DB::shouldReceive('getDefaultConnection')->andReturn('pgsql');

    DB::shouldReceive('statement')->once()->with('DROP INDEX IF EXISTS test_table_embedding_hnsw_index');

    VectorSchema::dropHnswIndex('test_table', 'embedding');
});

it('does not drop hnsw index on other drivers', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('sqlite');

    DB::shouldReceive('connection')->andReturn($connection);
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');

    DB::shouldNotReceive('statement');

    VectorSchema::dropHnswIndex('test_table', 'embedding');
});
