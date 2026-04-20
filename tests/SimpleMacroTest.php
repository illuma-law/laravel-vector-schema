<?php

use Illuminate\Support\Facades\DB;

it('executes selectHybridVectorDistance macro', function () {
    $builder = DB::table('test');
    $vector = [0.1, 0.2, 0.3];
    
    // This will use the real SQLite connection from TestCase
    $builder->selectHybridVectorDistance('embedding', $vector, 'dist');
    $sql = $builder->toSql();
    
    expect($sql)->toContain('vec_distance_cosine');
});
