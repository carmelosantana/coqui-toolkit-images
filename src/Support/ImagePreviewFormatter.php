<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Support;

final class ImagePreviewFormatter
{
    private const string CHARSET = ' .:-=+*#%@';
    private const int MAX_FILE_BYTES = 25 * 1024 * 1024;
    private const int MAX_PIXELS = 40_000_000;

    /**
     * @return array{preview: string|null, unavailable_reason: string|null}
     */
    public function format(string $path, int $width = 40): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return ['preview' => null, 'unavailable_reason' => 'ext-gd is not installed. Install it for ASCII image previews.'];
        }

        if (!is_file($path)) {
            return ['preview' => null, 'unavailable_reason' => 'Could not read image file for preview.'];
        }

        $fileSize = @filesize($path);
        if (is_int($fileSize) && $fileSize > self::MAX_FILE_BYTES) {
            return ['preview' => null, 'unavailable_reason' => sprintf('Image is too large to preview safely (%s MB limit).', (string) (self::MAX_FILE_BYTES / 1024 / 1024))];
        }

        $imageInfo = @getimagesize($path);
        if (!is_array($imageInfo)) {
            return ['preview' => null, 'unavailable_reason' => 'Image format not recognized by GD for preview rendering.'];
        }

        $sourceWidth = (int) $imageInfo[0];
        $sourceHeight = (int) $imageInfo[1];
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return ['preview' => null, 'unavailable_reason' => 'Image dimensions are invalid for preview rendering.'];
        }

        if (($sourceWidth * $sourceHeight) > self::MAX_PIXELS) {
            return ['preview' => null, 'unavailable_reason' => 'Image dimensions are too large to preview safely.'];
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            return ['preview' => null, 'unavailable_reason' => 'Could not read image file for preview.'];
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return ['preview' => null, 'unavailable_reason' => 'Image format not recognized by GD for preview rendering.'];
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $height = max(1, (int) round(($sourceHeight / $sourceWidth) * $width * 0.5));
        $lines = [];
        $maxIndex = strlen(self::CHARSET) - 1;

        for ($y = 0; $y < $height; $y++) {
            $line = '';
            for ($x = 0; $x < $width; $x++) {
                $sampleX = min($sourceWidth - 1, (int) floor(($x / $width) * $sourceWidth));
                $sampleY = min($sourceHeight - 1, (int) floor(($y / $height) * $sourceHeight));
                $rgb = imagecolorat($image, $sampleX, $sampleY);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $brightness = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
                $index = (int) round(($brightness / 255) * $maxIndex);
                $line .= self::CHARSET[$index];
            }
            $lines[] = rtrim($line);
        }

        imagedestroy($image);

        return ['preview' => implode("\n", $lines), 'unavailable_reason' => null];
    }
}