<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Tool;

use CarmeloSantana\CoquiToolkitImages\ImageToolkitRuntime;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class ImageLibraryTool
{
    public function __construct(
        private ImageToolkitRuntime $runtime,
    ) {}

    public function build(): ToolInterface
    {
        return new Tool(
            name: 'image_library',
            description: 'Manage the generated image library. Supports listing records, searching by prompt or metadata, viewing a specific record, updating tags or category, and deleting records.',
            parameters: [
                new EnumParameter('action', 'Library action to perform.', ['list', 'search', 'get', 'tag', 'delete'], true),
                new StringParameter('id', 'Image record ID. Required for get, tag, and delete.', required: false),
                new StringParameter('query', 'Search query for prompt, tags, owner, profile, model, vendor, or path.', required: false),
                new StringParameter('profile', 'Optional profile filter for list.', required: false),
                new StringParameter('vendor', 'Optional vendor filter for list.', required: false),
                new StringParameter('category', 'Optional category filter for search or replacement category for tag.', required: false),
                new StringParameter('tags_json', 'JSON array of tags used when action is tag.', required: false),
            ],
            callback: function (array $input): ToolResult {
                $action = (string) ($input['action'] ?? '');
                $json = static fn(mixed $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

                return match ($action) {
                    'list' => ToolResult::success($json(
                        $this->runtime->listImages(
                            is_string($input['profile'] ?? null) ? $input['profile'] : null,
                            is_string($input['vendor'] ?? null) ? $input['vendor'] : null,
                        ),
                    )),
                    'search' => ToolResult::success($json(
                        $this->runtime->searchImages(
                            is_string($input['query'] ?? null) ? $input['query'] : '',
                            is_string($input['category'] ?? null) ? $input['category'] : null,
                        ),
                    )),
                    'get' => ToolResult::success((function () use ($input, $json): string {
                        $id = (string) ($input['id'] ?? '');
                        $record = $this->runtime->getImage($id);

                        return $record !== null
                            ? $json($record)
                            : $json(['error' => 'Image record not found', 'id' => $id]);
                    })()),
                    'tag' => ToolResult::success($json(
                        $this->runtime->tagImage(
                            (string) ($input['id'] ?? ''),
                            $this->parseTags($input['tags_json'] ?? null),
                            is_string($input['category'] ?? null) ? $input['category'] : null,
                        ),
                    )),
                    'delete' => ToolResult::success((function () use ($input, $json): string {
                        $id = (string) ($input['id'] ?? '');
                        if ($id === '') {
                            return $json(['error' => 'Missing required id for delete action']);
                        }

                        return $json($this->runtime->deleteImage($id));
                    })()),
                    default => ToolResult::error('Unknown image_library action: ' . $action),
                };
            },
        );
    }

    /**
     * @return string[]
     */
    private function parseTags(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded)));
    }
}