<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Contract;

final readonly class ImageGenerationRequest
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public string $vendor,
        public string $model,
        public string $prompt,
        public string $fileHint,
        public ?string $saveDirectory,
        public string $profile,
        public string $ownerName,
        public array $tags,
        public ?string $category,
        public ?string $size,
        public ?string $quality,
        public ?string $negativePrompt,
        public ?int $seed,
        public ?int $steps,
    ) {}
}