<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\Exception\ImageToolkitException;
use CarmeloSantana\CoquiToolkitImages\Support\ImagePathResolver;

it('resolves default directory from profile name', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-path-test-' . bin2hex(random_bytes(4));
    mkdir($workspace, 0755, true);
    $resolver = new ImagePathResolver($workspace);

    $dir = $resolver->resolveDirectory(null, 'alice');

    expect($dir)->toBe($resolver->imagesRoot() . '/alice');
});

it('resolves relative save directory within workspace', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-path-rel-' . bin2hex(random_bytes(4));
    mkdir($workspace . '/custom', 0755, true);
    $resolver = new ImagePathResolver($workspace);

    $dir = $resolver->resolveDirectory('custom', 'default');

    expect($dir)->toBe(realpath($workspace . '/custom'));
});

it('rejects absolute paths outside workspace', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-path-escape-' . bin2hex(random_bytes(4));
    mkdir($workspace, 0755, true);
    $resolver = new ImagePathResolver($workspace);

    $resolver->resolveDirectory('/tmp', 'default');
})->throws(ImageToolkitException::class);

it('builds target paths with timestamp', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-path-build-' . bin2hex(random_bytes(4));
    $resolver = new ImagePathResolver($workspace);

    $path = $resolver->buildTargetPath('/some/dir', 'fox-running', '20250101-120000');

    expect($path)->toBe('/some/dir/fox-running-20250101-120000.png');
});

it('returns correct images root', function (): void {
    $workspace = '/home/test/workspace';
    $resolver = new ImagePathResolver($workspace);

    expect($resolver->imagesRoot())->toBe('/home/test/workspace/images');
});
