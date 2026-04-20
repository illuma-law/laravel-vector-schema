<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema\Support;

use Illuminate\Support\Collection;

class VectorProcessor
{
    /**
     * @return list<float>
     */
    public function normalizeVector(mixed $vector, int $expectedDimensions): array
    {
        if (! is_array($vector) || $vector === [] || count($vector) !== $expectedDimensions) {
            return [];
        }

        $normalized = [];

        foreach ($vector as $value) {
            if (! is_int($value) && ! is_float($value) && ! is_numeric($value)) {
                return [];
            }

            $floatValue = (float) $value;

            if (is_nan($floatValue) || is_infinite($floatValue)) {
                return [];
            }

            $normalized[] = $floatValue;
        }

        return $normalized;
    }

    /**
     * @return list<float>
     */
    public function averageVectors(mixed $vectors, int $expectedDimensions): array
    {
        if (! is_array($vectors) || $vectors === []) {
            return [];
        }

        /** @var Collection<int, list<float>> $normalizedVectors */
        $normalizedVectors = collect($vectors)
            ->map(fn (mixed $vector): array => $this->normalizeVector($vector, $expectedDimensions))
            ->filter(static fn (array $vector): bool => $vector !== [])
            ->values();

        if ($normalizedVectors->isEmpty()) {
            return [];
        }

        $sums = array_fill(0, $expectedDimensions, 0.0);
        $contributingVectors = 0;

        foreach ($normalizedVectors as $vector) {
            $contributingVectors++;

            foreach ($vector as $index => $value) {
                $sums[$index] += $value;
            }
        }

        if ($contributingVectors === 0) {
            return [];
        }

        return array_values(collect($sums)
            ->map(static fn (float $sum): float => $sum / $contributingVectors)
            ->all());
    }
}
