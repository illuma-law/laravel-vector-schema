<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema\Tests;

use IllumaLaw\VectorSchema\Support\VectorProcessor;

test('averageVectors returns empty for non-array or empty input', function (): void {
    $processor = new VectorProcessor;
    expect($processor->averageVectors([], 3))->toBe([])
        ->and($processor->averageVectors(null, 3))->toBe([])
        ->and($processor->averageVectors('not-array', 3))->toBe([]);
});

test('averageVectors drops vectors with wrong length and averages the rest', function (): void {
    $processor = new VectorProcessor;
    $ones = [1.0, 1.0, 1.0];
    $wrong = [9.0, 9.0];
    $threes = [3.0, 3.0, 3.0];

    $result = $processor->averageVectors([$ones, $wrong, $threes], 3);

    expect($result)->toBe([2.0, 2.0, 2.0]);
});

test('averageVectors excludes vectors containing nan', function (): void {
    $processor = new VectorProcessor;
    $clean = [2.0, 4.0, 6.0];
    $bad = [1.0, NAN, 1.0];

    $result = $processor->averageVectors([$bad, $clean], 3);

    expect($result)->toBe([2.0, 4.0, 6.0]);
});

test('averageVectors excludes vectors containing infinite values', function (): void {
    $processor = new VectorProcessor;
    $clean = [1.0, 1.0, 1.0];
    $bad = [INF, 0.0, 0.0];

    $result = $processor->averageVectors([$bad, $clean], 3);

    expect($result)->toBe([1.0, 1.0, 1.0]);
});

test('averageVectors returns empty when every vector is invalid', function (): void {
    $processor = new VectorProcessor;
    $badA = [NAN, 0.0, 0.0];
    $badB = [0.0, INF, 0.0];

    expect($processor->averageVectors([$badA, $badB], 3))->toBe([]);
});

test('normalizeVector accepts numeric strings', function (): void {
    $processor = new VectorProcessor;
    $vector = ['0.5', '1.5', '2.0'];

    expect($processor->normalizeVector($vector, 3))->toBe([0.5, 1.5, 2.0]);
});

test('normalizeVector returns empty when length does not match expected dimensions', function (): void {
    $processor = new VectorProcessor;
    expect($processor->normalizeVector([1.0, 2.0], 3))->toBe([]);
});
