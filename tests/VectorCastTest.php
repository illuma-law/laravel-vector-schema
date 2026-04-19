<?php

use IllumaLaw\VectorSchema\Casts\VectorArray;
use IllumaLaw\VectorSchema\VectorHelper;
use Illuminate\Database\Eloquent\Model;

class TestVectorModel extends Model
{
    protected $casts = [
        'embedding' => VectorArray::class,
    ];
}

it('can cast a vector array to a blob for sqlite', function () {
    $model = new TestVectorModel;
    $vector = [1.0, 2.0, 3.0];

    $cast = new VectorArray;
    $result = $cast->set($model, 'embedding', $vector, []);

    expect($result)->toBe(VectorHelper::toBlob($vector));
});

it('can cast a blob back to a vector array', function () {
    $vector = [1.0, 2.0, 3.0];
    $blob = VectorHelper::toBlob($vector);

    $cast = new VectorArray;
    $model = new TestVectorModel;
    $result = $cast->get($model, 'embedding', $blob, []);

    expect($result)->toBe($vector);
});

it('can cast a postgres vector string back to a vector array', function () {
    $vector = [1.0, 2.0, 3.0];
    $pgString = '[1.0,2.0,3.0]';

    $cast = new VectorArray;
    $model = new TestVectorModel;
    $result = $cast->get($model, 'embedding', $pgString, []);

    expect($result)->toBe($vector);
});
