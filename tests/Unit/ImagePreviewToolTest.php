<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\Contract\ImageToolkitContext;
use CarmeloSantana\CoquiToolkitImages\ImageToolkitRuntime;
use CarmeloSantana\CoquiToolkitImages\Tool\ImagePreviewTool;

it('renders a preview for an existing workspace image', function (): void {
    if (!function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('ext-gd not available');
    }

    $workspace = sys_get_temp_dir() . '/coqui-preview-tool-' . bin2hex(random_bytes(4));
    mkdir($workspace . '/images', 0755, true);
    $path = $workspace . '/images/example.png';

    $img = imagecreatetruecolor(4, 4);
    assert($img !== false);
    $color = imagecolorallocate($img, 20, 120, 240);
    assert($color !== false);
    imagefill($img, 0, 0, $color);
    imagepng($img, $path);
    imagedestroy($img);

    $runtime = ImageToolkitRuntime::fromContext(ImageToolkitContext::fromArray([
        'workspacePath' => $workspace,
    ]));

    $result = (new ImagePreviewTool($runtime))->build()->execute([
        'path' => 'images/example.png',
        'width' => 8,
    ]);

    expect($result->status->value)->toBe('success');

    $payload = json_decode($result->content, true);

    expect($payload)->toBeArray();
    expect($payload['path'])->toBe(realpath($path));
    expect($payload['preview_format'])->toBe('ansi_blocks');
    expect($payload['preview'])->toContain("\033[38;2;");
});

it('rejects preview paths outside the workspace', function (): void {
    if (!function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('ext-gd not available');
    }

    $workspace = sys_get_temp_dir() . '/coqui-preview-tool-workspace-' . bin2hex(random_bytes(4));
    $outside = sys_get_temp_dir() . '/coqui-preview-tool-outside-' . bin2hex(random_bytes(4));
    mkdir($workspace, 0755, true);
    mkdir($outside, 0755, true);

    $outsidePath = $outside . '/outside.png';
    $img = imagecreatetruecolor(2, 2);
    assert($img !== false);
    $color = imagecolorallocate($img, 255, 0, 0);
    assert($color !== false);
    imagefill($img, 0, 0, $color);
    imagepng($img, $outsidePath);
    imagedestroy($img);

    $runtime = ImageToolkitRuntime::fromContext(ImageToolkitContext::fromArray([
        'workspacePath' => $workspace,
    ]));

    $result = (new ImagePreviewTool($runtime))->build()->execute([
        'path' => $outsidePath,
    ]);

    expect($result->status->value)->toBe('error');
    expect($result->content)->toContain('must stay inside the workspace');
});