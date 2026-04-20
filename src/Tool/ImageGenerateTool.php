<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Tool;

use CarmeloSantana\CoquiToolkitImages\ImageToolkitRuntime;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class ImageGenerateTool
{
    private const string OUTPUT_FORMAT_JSON = 'json';

    public function __construct(
        private ImageToolkitRuntime $runtime,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'image_generate',
            description: 'Generate an image from a natural-language prompt using the configured or explicitly selected image model. Saves the image into the workspace image library and returns the saved path, metadata summary, and an optional low-fidelity colored block preview.',
            parameters: [
                new StringParameter('prompt', 'Natural-language image prompt.', required: true),
                new StringParameter('model', 'Optional model string in vendor/model format or a vendor-local model name when vendor is supplied separately.', required: false),
                new StringParameter('vendor', 'Optional vendor override when model is provided without vendor prefix.', required: false),
                new StringParameter('file_hint', 'Optional filename hint used when building the saved image path.', required: false),
                new StringParameter('save_dir', 'Optional save directory inside the workspace. Relative paths are resolved from the workspace root.', required: false),
                new StringParameter('tags_json', 'Optional JSON array of tags to attach to the image record.', required: false),
                new StringParameter('category', 'Optional category label for library search and organization.', required: false),
                new StringParameter('owner_name', 'Optional explicit owner name stored in image metadata.', required: false),
                new StringParameter('size', 'Optional size hint such as 1024x1024.', required: false),
                new StringParameter('quality', 'Optional quality hint such as low, medium, or high.', required: false),
                new StringParameter('negative_prompt', 'Optional negative prompt, used when supported by the selected backend.', required: false),
                new NumberParameter('seed', 'Optional deterministic seed when supported by the selected backend.', required: false),
                new NumberParameter('steps', 'Optional generation step count when supported by the selected backend.', required: false),
                new StringParameter('output_format', 'Optional output format. Internal callers may request `json` for structured rendering.', required: false),
            ],
            callback: function (array $input): ToolResult {
                $record = $this->runtime->generateFromInput($input);
                $preview = is_string($record['preview'] ?? null) ? $record['preview'] : null;
                $previewFormat = is_string($record['preview_format'] ?? null) ? $record['preview_format'] : null;
                $previewReason = is_string($record['preview_unavailable_reason'] ?? null) ? $record['preview_unavailable_reason'] : null;
                $metadataReason = is_string($record['metadata_unavailable_reason'] ?? null) ? $record['metadata_unavailable_reason'] : null;

                if (($input['output_format'] ?? null) === self::OUTPUT_FORMAT_JSON) {
                    return ToolResult::success(json_encode([
                        'message' => 'Image generated successfully.',
                        'saved_path' => $record['path'],
                        'preview' => $preview,
                        'preview_format' => $previewFormat,
                        'preview_unavailable_reason' => $previewReason,
                        'metadata_unavailable_reason' => $metadataReason,
                        'record' => $record,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
                }

                unset($record['preview'], $record['preview_format'], $record['preview_unavailable_reason'], $record['metadata_unavailable_reason']);

                $filePath = $record['path'];
                $fileLink = 'file://' . $filePath;

                $message = "Image generated successfully.\n"
                    . 'Saved path: ' . $filePath . "\n"
                    . 'Open: ' . $fileLink . "\n"
                    . 'Record ID: ' . $record['id'] . "\n"
                    . 'Model: ' . $record['vendor'] . '/' . $record['model'] . "\n"
                    . 'Metadata embedded: ' . (($record['metadata_embedded'] ?? false) ? 'yes' : 'no');

                if ($metadataReason !== null) {
                    $message .= "\nMetadata note: " . $metadataReason;
                }

                if ($preview !== null && $preview !== '') {
                    if ($previewFormat !== null) {
                        $message .= "\nPreview format: " . $previewFormat;
                    }

                    $message .= "\n\nPreview:\n" . $preview;
                } elseif ($previewReason !== null) {
                    $message .= "\n\nPreview unavailable: " . $previewReason;
                }

                $message .= "\n\nRecord:\n" . json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                return ToolResult::success($message);
            },
        );
    }
}