<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\ImagesToolkit;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;

it('implements ToolkitInterface', function (): void {
    $toolkit = new ImagesToolkit(workspacePath: sys_get_temp_dir() . '/coqui-images-toolkit-test');

    expect($toolkit)->toBeInstanceOf(ToolkitInterface::class);
});

it('exposes the expected tools', function (): void {
    $toolkit = new ImagesToolkit(workspacePath: sys_get_temp_dir() . '/coqui-images-toolkit-test');
    $names = array_map(static fn($tool) => $tool->name(), $toolkit->tools());

    expect($names)->toBe(['image_preflight', 'image_generate', 'image_library', 'image_config']);
});

it('produces valid function schemas', function (): void {
    $toolkit = new ImagesToolkit(workspacePath: sys_get_temp_dir() . '/coqui-images-toolkit-test');

    foreach ($toolkit->tools() as $tool) {
        $schema = $tool->toFunctionSchema();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBeString();
        expect($schema['function']['parameters'])->toBeArray();
    }
});