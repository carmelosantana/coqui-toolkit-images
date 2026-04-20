<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Support;

final class ImagePreviewFormatter
{
    private const array BLOCK_RAMP = ['░', '░', '▒', '▓', '█'];
    private const string PREVIEW_FORMAT = 'ansi_blocks';
    private const int DEFAULT_WIDTH = 40;
    private const int MAX_WIDTH = 120;
    private const int MAX_FILE_BYTES = 25 * 1024 * 1024;
    private const int MAX_PIXELS = 40_000_000;
    private const float CELL_ASPECT_RATIO = 0.5;

    public static function previewFormat(): string
    {
        return self::PREVIEW_FORMAT;
    }

    /**
     * @return array{preview: string|null, preview_format: string|null, unavailable_reason: string|null}
     */
    public function format(string $path, int $width = self::DEFAULT_WIDTH): array
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring')) {
            return [
                'preview' => null,
                'preview_format' => null,
                'unavailable_reason' => 'ext-gd is not installed. Install it for low-fidelity image previews.',
            ];
        }

        if (!is_file($path)) {
            return ['preview' => null, 'preview_format' => null, 'unavailable_reason' => 'Could not read image file for preview.'];
        }

        $fileSize = @filesize($path);
        if (is_int($fileSize) && $fileSize > self::MAX_FILE_BYTES) {
            return [
                'preview' => null,
                'preview_format' => null,
                'unavailable_reason' => sprintf('Image is too large to preview safely (%s MB limit).', (string) (self::MAX_FILE_BYTES / 1024 / 1024)),
            ];
        }

        $imageInfo = @getimagesize($path);
        if (!is_array($imageInfo)) {
            return ['preview' => null, 'preview_format' => null, 'unavailable_reason' => 'Image format not recognized by GD for preview rendering.'];
        }

        $sourceWidth = (int) $imageInfo[0];
        $sourceHeight = (int) $imageInfo[1];
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return ['preview' => null, 'preview_format' => null, 'unavailable_reason' => 'Image dimensions are invalid for preview rendering.'];
        }

        if (($sourceWidth * $sourceHeight) > self::MAX_PIXELS) {
            return ['preview' => null, 'preview_format' => null, 'unavailable_reason' => 'Image dimensions are too large to preview safely.'];
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            return ['preview' => null, 'preview_format' => null, 'unavailable_reason' => 'Could not read image file for preview.'];
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return ['preview' => null, 'preview_format' => null, 'unavailable_reason' => 'Image format not recognized by GD for preview rendering.'];
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $width = max(1, min($width, self::MAX_WIDTH));
        $height = max(1, (int) round(($sourceHeight / max($sourceWidth, 1)) * $width * self::CELL_ASPECT_RATIO));
        $previewImage = imagecreatetruecolor($width, $height);
        if ($previewImage === false) {
            imagedestroy($image);

            return [
                'preview' => null,
                'preview_format' => null,
                'unavailable_reason' => 'Could not allocate a preview buffer for image rendering.',
            ];
        }

        imagealphablending($previewImage, false);
        imagesavealpha($previewImage, true);

        if (!imagecopyresampled($previewImage, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
            imagedestroy($previewImage);
            imagedestroy($image);

            return [
                'preview' => null,
                'preview_format' => null,
                'unavailable_reason' => 'Could not resample the image for preview rendering.',
            ];
        }

        imagedestroy($image);

        $lines = [];

        for ($y = 0; $y < $height; $y++) {
            $line = '';
            for ($x = 0; $x < $width; $x++) {
                $colorIndex = imagecolorat($previewImage, $x, $y);
                if ($colorIndex === false) {
                    $red = 0;
                    $green = 0;
                    $blue = 0;
                } else {
                    $rgba = imagecolorsforindex($previewImage, $colorIndex);
                    $opacity = 1.0 - (((float) $rgba['alpha']) / 127.0);
                    $red = (int) round(((int) $rgba['red']) * $opacity);
                    $green = (int) round(((int) $rgba['green']) * $opacity);
                    $blue = (int) round(((int) $rgba['blue']) * $opacity);
                }

                $brightness = ($red * 0.299) + ($green * 0.587) + ($blue * 0.114);
                $line .= sprintf(
                    "\033[38;2;%d;%d;%dm%s",
                    $red,
                    $green,
                    $blue,
                    $this->brightnessBlock($brightness),
                );
            }

            $lines[] = $line . "\033[0m";
        }

        imagedestroy($previewImage);

        return [
            'preview' => implode("\n", $lines),
            'preview_format' => self::PREVIEW_FORMAT,
            'unavailable_reason' => null,
        ];
    }

    private function brightnessBlock(float $brightness): string
    {
        $maxIndex = count(self::BLOCK_RAMP) - 1;
        $index = (int) round(($brightness / 255) * $maxIndex);

        return self::BLOCK_RAMP[max(0, min($index, $maxIndex))];
    }
}