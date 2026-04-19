<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema\Casts;

use IllumaLaw\VectorSchema\VectorHelper;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class VectorArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $this->validateVector($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $json = str_replace(['[', ']'], ['[', ']'], $trimmed);

                try {
                    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        return $this->validateVector($decoded);
                    }
                } catch (\JsonException) {
                }
            }

            if (strlen($value) % 4 === 0) {
                try {
                    $vector = VectorHelper::fromBlob($value);
                    if (count($vector) > 0) {
                        return $this->validateVector($vector);
                    }
                } catch (\Throwable) {
                }
            }

            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $this->validateVector($decoded);
                }
            } catch (\JsonException) {
                return null;
            }
        }

        return null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException(
                "The {$key} attribute must be an array of floats."
            );
        }

        $vector = $this->validateVector($value);

        $connection = $model->getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            return VectorHelper::toBlob($vector);
        }

        return $vector;
    }

    private function validateVector(array $vector): array
    {
        return array_map(
            fn ($v) => is_numeric($v) ? (float) $v : 0.0,
            $vector
        );
    }
}
