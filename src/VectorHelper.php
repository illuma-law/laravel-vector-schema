<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema;

final class VectorHelper
{
    /**
     * @param  array<int, float>  $vector
     */
    public static function toBlob(array $vector): string
    {
        return pack('f*', ...$vector);
    }

    /**
     * @return array<int, float>
     */
    public static function fromBlob(string $blob): array
    {
        /** @var array<int, float> */
        return array_values(unpack('f*', $blob) ?: []);
    }

    /**
     * @param  array<int, float>  $vector
     */
    public static function toPostgresLiteral(array $vector): string
    {
        return '['.implode(',', array_map(fn ($v) => (string) $v, $vector)).']';
    }
}
