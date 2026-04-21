<?php

declare(strict_types=1);

require_once __DIR__ . '/../Support/ImageToolkitContextConfigStub.php';

use CarmeloSantana\CoquiToolkitImages\Contract\ImageToolkitContext;

it('extracts image config from the new core schema', function (): void {
    $config = new ImageToolkitContextConfigStub();

    $context = ImageToolkitContext::fromArray([
        'workspacePath' => sys_get_temp_dir() . '/coqui-image-context-test',
        'config' => $config,
    ]);

    expect($context->primaryModel)->toBe('openai/gpt-image-1.5');
    expect($context->defaultOwnerName)->toBe('caelum');
    expect($context->imageConfig)->toBe([
        'primary' => 'openai/gpt-image-1.5',
        'fallbacks' => ['ollama/x/z-image-turbo:latest'],
        'providers' => [
            'openai' => [
                'model' => 'gpt-image-1.5',
                'baseUrl' => 'https://api.openai.com/v1',
            ],
        ],
        'ownerName' => 'caelum',
    ]);
});