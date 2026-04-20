<?php

use IllumaLaw\VectorSchema\VectorHelper;

it('converts vector to blob', function () {
    $vector = [1.0, 2.0, 3.0];
    $blob = VectorHelper::toBlob($vector);

    expect(strlen($blob))->toBe(12);
});

it('converts blob to vector', function () {
    $vector = [1.0, 2.0, 3.0];
    $blob = VectorHelper::toBlob($vector);
    $result = VectorHelper::fromBlob($blob);

    expect($result)->toBe($vector);
});

it('converts vector to postgres literal', function () {
    $vector = [1.0, 2.0, 3.0];
    $result = VectorHelper::toPostgresLiteral($vector);

    expect($result)->toBe('[1,2,3]');
});

it('handles empty blob in fromBlob', function () {
    $result = VectorHelper::fromBlob('');
    expect($result)->toBe([]);
});
