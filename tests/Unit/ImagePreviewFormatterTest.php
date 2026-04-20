<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\Support\ImagePreviewFormatter;

it('renders ASCII preview from a valid PNG', function (): void {
    if (!function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('ext-gd not available');
    }

    $workspace = sys_get_temp_dir() . '/coqui-preview-' . bin2hex(random_bytes(4));
    mkdir($workspace, 0755, true);
    $path = $workspace . '/test.png';

    // Create a real 4x4 PNG via GD
    $img = imagecreatetruecolor(4, 4);
    assert($img !== false);
    $color = imagecolorallocate($img, 255, 255, 255);
    assert($color !== false);
    imagefill($img, 0, 0, $color);
    imagepng($img, $path);
    imagedestroy($img);

    $formatter = new ImagePreviewFormatter();
    $result = $formatter->format($path, 10);

    expect($result['preview'])->toBeString();
    expect($result['preview'])->not->toBeEmpty();
    expect($result['unavailable_reason'])->toBeNull();
});

it('returns unavailable reason for nonexistent file', function (): void {
    if (!function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('ext-gd not available');
    }

    $formatter = new ImagePreviewFormatter();
    $result = @$formatter->format('/nonexistent/path/image.png');

    expect($result['preview'])->toBeNull();
    expect($result['unavailable_reason'])->toContain('Could not read');
});

it('returns unavailable reason for non-image file', function (): void {
    if (!function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('ext-gd not available');
    }

    $workspace = sys_get_temp_dir() . '/coqui-preview-bad-' . bin2hex(random_bytes(4));
    mkdir($workspace, 0755, true);
    $path = $workspace . '/bad.png';
    file_put_contents($path, 'not an image');

    $formatter = new ImagePreviewFormatter();
    $result = @$formatter->format($path);

    expect($result['preview'])->toBeNull();
    expect($result['unavailable_reason'])->toContain('not recognized');
});

it('returns unavailable reason for oversized files', function (): void {
    if (!function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('ext-gd not available');
    }

    $workspace = sys_get_temp_dir() . '/coqui-preview-large-' . bin2hex(random_bytes(4));
    mkdir($workspace, 0755, true);
    $path = $workspace . '/large.bin';
    file_put_contents($path, str_repeat('0', (25 * 1024 * 1024) + 1));

    $formatter = new ImagePreviewFormatter();
    $result = @$formatter->format($path);

    expect($result['preview'])->toBeNull();
    expect($result['unavailable_reason'])->toContain('too large');
});
