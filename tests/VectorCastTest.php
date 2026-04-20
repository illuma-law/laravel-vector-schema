<?php

use IllumaLaw\VectorSchema\Casts\VectorArray;
use IllumaLaw\VectorSchema\VectorHelper;
use Illuminate\Database\Connection;
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

it('returns null for null value in get', function () {
    $cast = new VectorArray;
    $model = new TestVectorModel;
    $result = $cast->get($model, 'embedding', null, []);

    expect($result)->toBeNull();
});

it('validates vector if it is already an array in get', function () {
    $cast = new VectorArray;
    $model = new TestVectorModel;
    $result = $cast->get($model, 'embedding', [1, 'invalid', 3], []);

    expect($result)->toBe([1.0, 0.0, 3.0]);
});

it('handles invalid json string in get', function () {
    $cast = new VectorArray;
    $model = new TestVectorModel;

    // String looks like PG vector but is invalid JSON
    $result = $cast->get($model, 'embedding', '[1.0, invalid]', []);
    expect($result)->toBeNull();

    // Standard invalid JSON
    $result = $cast->get($model, 'embedding', '{"invalid":}', []);
    expect($result)->toBeNull();

    // Valid UTF-8 string that unpacks to "unreasonable" (tiny) floats
    $tiny = str_repeat("\x01", 16);
    $result = $cast->get($model, 'embedding', $tiny, []);
    expect($result)->toBeNull();
});

it('handles non-array json in get', function () {
    $cast = new VectorArray;
    $model = new TestVectorModel;
    $result = $cast->get($model, 'embedding', '"just a string"', []);

    expect($result)->toBeNull();
});

it('handles invalid blob length in get', function () {
    $cast = new VectorArray;
    $model = new TestVectorModel;
    // Length not divisible by 4
    $result = $cast->get($model, 'embedding', 'abc', []);

    expect($result)->toBeNull();
});

it('returns null for other types in get', function () {
    $cast = new VectorArray;
    $model = new TestVectorModel;
    $result = $cast->get($model, 'embedding', 123, []);

    expect($result)->toBeNull();
});

it('returns null for null value in set', function () {
    $cast = new VectorArray;
    $model = new TestVectorModel;
    $result = $cast->set($model, 'embedding', null, []);

    expect($result)->toBeNull();
});

it('throws exception for non-array value in set', function () {
    $cast = new VectorArray;
    $model = new TestVectorModel;

    expect(fn () => $cast->set($model, 'embedding', 'not an array', []))
        ->toThrow(InvalidArgumentException::class, 'The embedding attribute must be an array of floats.');
});

it('returns raw vector for non-sqlite drivers in set', function () {
    $cast = new VectorArray;
    $model = Mockery::mock(TestVectorModel::class)->makePartial();
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $model->shouldReceive('getConnection')->andReturn($connection);

    $vector = [1.0, 2.0];
    $result = $cast->set($model, 'embedding', $vector, []);

    expect($result)->toBe($vector);
});
