<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Database\Query\Processors\Processor;

dataset('drivers', [
    'sqlite',
    'mysql',
    'mariadb',
    'sqlsrv',
    'singlestore',
    'pgsql',
]);

function getMockBuilder(string $driver)
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn($driver);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('raw')->andReturnUsing(fn ($value) => new Expression($value));
    $connection->shouldReceive('addBinding');
    $grammar = match ($driver) {
        'sqlite' => new SQLiteGrammar($connection),
        'mysql', 'mariadb', 'singlestore' => new MySqlGrammar($connection),
        'sqlsrv' => new SqlServerGrammar($connection),
        'pgsql'  => new PostgresGrammar($connection),
        default  => new Grammar($connection),
    };

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn(Mockery::mock(Processor::class));

    return new Builder($connection, $grammar);
}

it('generates correct distance SQL for each driver', function (string $driver) {
    $builder = getMockBuilder($driver);
    $builder->from('test');

    $vector = [0.1, 0.2, 0.3];

    $builder->selectHybridVectorDistance('embedding', $vector, 'dist');
    $sql = $builder->toSql();

    if ($driver === 'sqlite') {
        expect($sql)->toContain('vec_distance_cosine("embedding", ?)');
        expect($builder->getRawBindings()['select'])->toHaveCount(1);
    } elseif ($driver === 'mysql') {
        expect($sql)->toContain('VECTOR_DISTANCE(`embedding`, STRING_TO_VECTOR(?), \'COSINE\')');
    } elseif ($driver === 'mariadb') {
        expect($sql)->toContain('VEC_DISTANCE_COSINE(`embedding`, VEC_FromText(?))');
    } elseif ($driver === 'sqlsrv') {
        expect($sql)->toContain("VECTOR_DISTANCE('cosine', [embedding], ?)");
    } elseif ($driver === 'singlestore') {
        expect($sql)->toContain('1 - DOT_PRODUCT(`embedding`, JSON_ARRAY_PACK(?))');
    } else {
        expect($sql)->toContain('embedding <=> \'[0.1,0.2,0.3]\'::vector');
    }
})->with('drivers');

it('generates correct similarity where SQL for each driver', function (string $driver) {
    $builder = getMockBuilder($driver);
    $builder->from('test');

    $vector = [0.1, 0.2, 0.3];

    $builder->whereHybridVectorSimilarTo('embedding', $vector, 0.7);
    $sql = strtolower($builder->toSql());

    if ($driver === 'sqlite') {
        expect($sql)->toContain('vec_distance_cosine("embedding", ?) <= ?');
        expect($sql)->toContain('order by vec_distance_cosine("embedding", ?)');
    } elseif ($driver === 'mysql') {
        expect($sql)->toContain('vector_distance(`embedding`, string_to_vector(?), \'cosine\') <= ?');
        expect($sql)->toContain('order by vector_distance(`embedding`, string_to_vector(?), \'cosine\')');
    } elseif ($driver === 'mariadb') {
        expect($sql)->toContain('vec_distance_cosine(`embedding`, vec_fromtext(?)) <= ?');
        expect($sql)->toContain('order by vec_distance_cosine(`embedding`, vec_fromtext(?))');
    } elseif ($driver === 'sqlsrv') {
        expect($sql)->toContain("vector_distance('cosine', [embedding], ?) <= ?");
        expect($sql)->toContain("order by vector_distance('cosine', [embedding], ?)");
    } elseif ($driver === 'singlestore') {
        expect($sql)->toContain('dot_product(`embedding`, json_array_pack(?)) >= ?');
        expect($sql)->toContain('order by dot_product(`embedding`, json_array_pack(?)) desc');
    } else {
        expect($sql)->toContain('embedding <=> \'[0.1,0.2,0.3]\'::vector <= ?');
        expect($sql)->toContain('order by embedding <=> \'[0.1,0.2,0.3]\'::vector');
    }
})->with('drivers');

it('generates correct distance less than SQL for each driver', function (string $driver) {
    $builder = getMockBuilder($driver);
    $builder->from('test');

    $vector = [0.1, 0.2, 0.3];

    $builder->whereHybridVectorDistanceLessThan('embedding', $vector, 0.3);
    $sql = strtolower($builder->toSql());

    if ($driver === 'sqlite') {
        expect($sql)->toContain('vec_distance_cosine("embedding", ?) < ?');
    } elseif ($driver === 'mysql') {
        expect($sql)->toContain('vector_distance(`embedding`, string_to_vector(?), \'cosine\') < ?');
    } elseif ($driver === 'mariadb') {
        expect($sql)->toContain('vec_distance_cosine(`embedding`, vec_fromtext(?)) < ?');
    } elseif ($driver === 'sqlsrv') {
        expect($sql)->toContain("vector_distance('cosine', [embedding], ?) < ?");
    } elseif ($driver === 'singlestore') {
        expect($sql)->toContain('1 - dot_product(`embedding`, json_array_pack(?)) < ?');
    } else {
        expect($sql)->toContain('embedding <=> \'[0.1,0.2,0.3]\'::vector < ?');
    }
})->with('drivers');

it('generates correct order by distance SQL for each driver', function (string $driver) {
    $builder = getMockBuilder($driver);
    $builder->from('test');

    $vector = [0.1, 0.2, 0.3];

    $builder->orderByHybridVectorDistance('embedding', $vector, 'desc');
    $sql = strtolower($builder->toSql());

    if ($driver === 'sqlite') {
        expect($sql)->toContain('order by vec_distance_cosine("embedding", ?) desc');
    } elseif ($driver === 'mysql') {
        expect($sql)->toContain('order by vector_distance(`embedding`, string_to_vector(?), \'cosine\') desc');
    } elseif ($driver === 'mariadb') {
        expect($sql)->toContain('order by vec_distance_cosine(`embedding`, vec_fromtext(?)) desc');
    } elseif ($driver === 'sqlsrv') {
        expect($sql)->toContain("order by vector_distance('cosine', [embedding], ?) desc");
    } elseif ($driver === 'singlestore') {
        expect($sql)->toContain('order by dot_product(`embedding`, json_array_pack(?)) asc');
    } else {
        expect($sql)->toContain('order by embedding <=> \'[0.1,0.2,0.3]\'::vector desc');
    }
})->with('drivers');
