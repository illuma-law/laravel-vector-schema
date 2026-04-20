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

            // 1. Check for PostgreSQL vector string representation: "[1,2,3]"
            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                try {
                    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        return $this->validateVector($decoded);
                    }
                } catch (\JsonException) {
                }
            }

            // 2. Check for standard JSON representation
            // We only try this if it doesn't look like a potential blob (non-UTF8)
            $isProbablyBinary = ! mb_check_encoding($value, 'UTF-8');
            
            if (! $isProbablyBinary) {
                try {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        return $this->validateVector($decoded);
                    }
                } catch (\JsonException) {
                }
            }

            // 3. Last resort: Check for SQLite binary BLOB (float32 array)
            if (strlen($value) > 0 && strlen($value) % 4 === 0) {
                try {
                    $vector = VectorHelper::fromBlob($value);
                    if (count($vector) > 0) {
                        // For non-binary strings that reached here, we verify if the values are reasonable
                        // (binary data often produces extremely large or small floats)
                        if (! $isProbablyBinary) {
                            foreach ($vector as $val) {
                                if (abs($val) > 1e10 || (abs($val) < 1e-10 && $val != 0)) {
                                     return null;
                                }
                            }
                        }
                        return $this->validateVector($vector);
                    }
                } catch (\Throwable) {
                }
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
