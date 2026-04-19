<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('registers the vectorColumn macro on blueprint', function () {
    expect(Blueprint::hasMacro('vectorColumn'))->toBeTrue();
});

it('can create a vector column in a migration', function () {
    Schema::create('test_vectors', function (Blueprint $table) {
        $table->id();
        $table->vectorColumn('embedding', 1536);
    });

    expect(Schema::hasColumn('test_vectors', 'embedding'))->toBeTrue();
});
