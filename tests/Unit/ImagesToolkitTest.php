<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\ImagesToolkit;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CoquiBot\Coqui\Contract\ToolkitCommandHelpProvider;
use CoquiBot\Coqui\Contract\ReplCommandProvider;

it('implements ToolkitInterface', function (): void {
    if (!interface_exists('CoquiBot\\Coqui\\Contract\\ReplCommandProvider')) {
        $this->markTestSkipped('Coqui REPL contract interfaces are not installed in this standalone toolkit environment.');
    }

    $toolkit = new ImagesToolkit(workspacePath: sys_get_temp_dir() . '/coqui-images-toolkit-test');

    expect($toolkit)->toBeInstanceOf(ToolkitInterface::class);
});

it('exposes the expected tools', function (): void {
    if (!interface_exists('CoquiBot\\Coqui\\Contract\\ReplCommandProvider')) {
        $this->markTestSkipped('Coqui REPL contract interfaces are not installed in this standalone toolkit environment.');
    }

    $toolkit = new ImagesToolkit(workspacePath: sys_get_temp_dir() . '/coqui-images-toolkit-test');
    $names = array_map(static fn($tool) => $tool->name(), $toolkit->tools());

    expect($names)->toBe(['image_preflight', 'image_generate', 'image_preview', 'image_library', 'image_config']);
});

it('produces valid function schemas', function (): void {
    if (!interface_exists('CoquiBot\\Coqui\\Contract\\ReplCommandProvider')) {
        $this->markTestSkipped('Coqui REPL contract interfaces are not installed in this standalone toolkit environment.');
    }

    $toolkit = new ImagesToolkit(workspacePath: sys_get_temp_dir() . '/coqui-images-toolkit-test');

    foreach ($toolkit->tools() as $tool) {
        $schema = $tool->toFunctionSchema();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBeString();
        expect($schema['function']['parameters'])->toBeArray();
    }
});

it('registers one structured repl command handler', function (): void {
    if (!interface_exists('CoquiBot\\Coqui\\Contract\\ReplCommandProvider')) {
        $this->markTestSkipped('Coqui REPL contract interfaces are not installed in this standalone toolkit environment.');
    }

    $toolkit = new ImagesToolkit(workspacePath: sys_get_temp_dir() . '/coqui-images-toolkit-test');

    expect($toolkit)->toBeInstanceOf(ReplCommandProvider::class);

    $handlers = $toolkit->commandHandlers();

    expect($handlers)->toHaveCount(1);
    expect($handlers[0])->toBeInstanceOf(ToolkitCommandHelpProvider::class);
    expect($handlers[0]->commandName())->toBe('image');
    expect($handlers[0]->description())->toBe('Generate, preview, and manage workspace images through the image toolkit.');
});