<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('executes selectHybridVectorDistance macro', function () {
    $builder = DB::table('test');
    $vector = [0.1, 0.2, 0.3];

    $builder->selectHybridVectorDistance('embedding', $vector, 'dist');
    $sql = $builder->toSql();
    $driver = DB::connection()->getDriverName();

    if ($driver === 'sqlite') {
        expect($sql)->toContain('vec_distance_cosine');
    } elseif ($driver === 'mysql') {
        expect($sql)->toContain('VECTOR_DISTANCE');
    } elseif ($driver === 'mariadb') {
        expect($sql)->toContain('VEC_DISTANCE_COSINE');
    } elseif ($driver === 'sqlsrv') {
        expect($sql)->toContain('VECTOR_DISTANCE');
    } elseif ($driver === 'singlestore') {
        expect($sql)->toContain('DOT_PRODUCT');
    } else {
        expect($sql)->toContain('<=>');
    }
});
