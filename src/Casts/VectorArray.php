<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema\Casts;

use IllumaLaw\VectorSchema\VectorHelper;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<array<int, float>|null, array<int, float>|null>
 */
class VectorArray implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, float>|null
     */
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
                try {
                    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        return $this->validateVector($decoded);
                    }
                } catch (\JsonException) {
                }
            }

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

            if (strlen($value) > 0 && strlen($value) % 4 === 0) {
                try {
                    $vector = VectorHelper::fromBlob($value);
                    if (count($vector) > 0) {
                        if (! $isProbablyBinary) {
                            foreach ($vector as $val) {
                                if (abs((float) $val) > 1e10 || (abs((float) $val) < 1e-10 && $val != 0)) {
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

    /**
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("The {$key} attribute must be an array of floats.");
        }

        $vector = $this->validateVector($value);

        $connection = $model->getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return VectorHelper::toBlob($vector);
        }

        if ($driver === 'pgsql') {
            return VectorHelper::toPostgresLiteral($vector);
        }

        if (in_array($driver, ['mysql', 'mariadb', 'sqlsrv', 'singlestore'])) {
            return json_encode($vector);
        }

        return $vector;
    }

    /**
     * @param  array<mixed, mixed>  $vector
     * @return array<int, float>
     */
    private function validateVector(array $vector): array
    {
        return array_values(array_map(
            fn ($v) => is_numeric($v) ? (float) $v : 0.0,
            $vector
        ));
    }
}
