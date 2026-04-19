<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Support;

final class FileNameHelper
{
    public static function sanitizeSegment(string $value, string $fallback = 'image'): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? '';
        $value = trim($value, '-._');

        return $value !== '' ? $value : $fallback;
    }
}