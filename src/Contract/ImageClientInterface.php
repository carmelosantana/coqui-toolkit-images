<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Contract;

interface ImageClientInterface
{
    public function generate(ImageGenerationRequest $request, string $targetPath): ImageGenerationResult;
}