<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Tool;

use CarmeloSantana\CoquiToolkitImages\Exception\ImageToolkitException;
use CarmeloSantana\CoquiToolkitImages\ImageToolkitRuntime;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class ImagePreviewTool
{
    public function __construct(
        private ImageToolkitRuntime $runtime,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'image_preview',
            description: 'Render an existing workspace image as a low-fidelity colored block preview for terminal display. Use this when you want to show or inspect an image in the REPL without opening an external viewer.',
            parameters: [
                new StringParameter('path', 'Path to an existing image file inside the workspace. Relative paths are resolved from the workspace root.', required: true),
                new NumberParameter('width', 'Optional preview width in terminal cells. Defaults to 40.', required: false),
            ],
            callback: function (array $input): ToolResult {
                try {
                    $payload = $this->runtime->previewFromInput($input);
                } catch (ImageToolkitException $e) {
                    return ToolResult::error($e->getMessage());
                }

                $preview = is_string($payload['preview'] ?? null) ? $payload['preview'] : null;
                if ($preview === null || trim($preview) === '') {
                    return ToolResult::error((string) ($payload['preview_unavailable_reason'] ?? 'Could not render image preview.'));
                }

                return ToolResult::json([
                    'message' => 'Image preview generated successfully.',
                    'path' => $payload['path'],
                    'preview' => $preview,
                    'preview_format' => $payload['preview_format'],
                ]);
            },
        );
    }
}