<?php

declare(strict_types=1);

namespace IllumaLaw\VectorSchema;

final class VectorHelper
{
    public static function toBlob(array $vector): string
    {
        return pack('f*', ...$vector);
    }

    public static function fromBlob(string $blob): array
    {
        return array_values(unpack('f*', $blob) ?: []);
    }

    public static function toPostgresLiteral(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
