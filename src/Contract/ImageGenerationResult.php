<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Contract;

final readonly class ImageGenerationResult
{
    /**
     * @param array<string, mixed> $providerPayload
     */
    public function __construct(
        public string $vendor,
        public string $model,
        public string $filePath,
        public array $providerPayload = [],
    ) {}
}