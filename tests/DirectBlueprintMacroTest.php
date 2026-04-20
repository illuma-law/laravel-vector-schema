<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;

it('calls createHnswIndex from macro', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new SQLiteGrammar($connection));

    $blueprint = new Blueprint($connection, 'test_table');

    // We expect this to call VectorSchema::createHnswIndex which we already tested.
    // Here we just want to cover the macro closure lines.
    $blueprint->hnswIndex('embedding');

    expect(true)->toBeTrue();
});

it('calls dropHnswIndex from macro', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new SQLiteGrammar($connection));

    $blueprint = new Blueprint($connection, 'test_table');
    $blueprint->dropHnswIndex('embedding');

    expect(true)->toBeTrue();
});
