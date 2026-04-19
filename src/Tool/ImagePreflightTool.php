<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Tool;

use CarmeloSantana\CoquiToolkitImages\ImageToolkitRuntime;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class ImagePreflightTool
{
    public function __construct(
        private ImageToolkitRuntime $runtime,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'image_preflight',
            description: 'Resolve the effective image vendor and model for a generation request, and report whether local prerequisites such as an Ollama pull or OpenAI credentials are still missing.',
            parameters: [
                new StringParameter('model', 'Optional model string in vendor/model format or a vendor-local model name when vendor is supplied separately.', required: false),
                new StringParameter('vendor', 'Optional vendor override when model is provided without vendor prefix.', required: false),
            ],
            callback: fn(array $input): ToolResult => ToolResult::success(
                json_encode($this->runtime->preflightGeneration($input), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
            ),
        );
    }
}