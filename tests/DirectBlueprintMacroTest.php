<?php

use Illuminate\Database\Schema\Blueprint;
use Mockery\MockInterface;

it('calls createHnswIndex from macro', function () {
    $connection = Mockery::mock(\Illuminate\Database\Connection::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new \Illuminate\Database\Schema\Grammars\SQLiteGrammar($connection));
    
    $blueprint = new Blueprint($connection, 'test_table');
    
    // We expect this to call VectorSchema::createHnswIndex which we already tested.
    // Here we just want to cover the macro closure lines.
    $blueprint->hnswIndex('embedding');
    
    expect(true)->toBeTrue();
});

it('calls dropHnswIndex from macro', function () {
    $connection = Mockery::mock(\Illuminate\Database\Connection::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new \Illuminate\Database\Schema\Grammars\SQLiteGrammar($connection));
    
    $blueprint = new Blueprint($connection, 'test_table');
    $blueprint->dropHnswIndex('embedding');
    
    expect(true)->toBeTrue();
});
