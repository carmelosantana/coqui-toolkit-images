<?php

declare(strict_types=1);

final class ImageToolkitContextConfigStub
{
    /** @var array<string, mixed> */
    private array $values = [
        'agents.defaults.model.imageModel' => 'openai/gpt-image-1.5',
        'agents.defaults.model.imageFallbacks' => ['ollama/x/z-image-turbo:latest'],
        'images.providers' => [
            'openai' => [
                'model' => 'gpt-image-1.5',
                'baseUrl' => 'https://api.openai.com/v1',
            ],
        ],
        'images.ownerName' => 'caelum',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}