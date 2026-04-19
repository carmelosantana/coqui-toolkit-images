<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Tool;

use CarmeloSantana\CoquiToolkitImages\ImageToolkitRuntime;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class ImageConfigTool
{
    public function __construct(
        private ImageToolkitRuntime $runtime,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'image_config',
            description: 'Inspect the resolved image-generation configuration, including workspace image root, active profile, primary image model, and vendor settings supplied by Coqui or environment variables.',
            parameters: [],
            callback: fn(array $input): ToolResult => ToolResult::success(
                json_encode($this->runtime->configSnapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
            ),
        );
    }
}