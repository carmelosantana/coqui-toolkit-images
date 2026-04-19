<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages;

use CarmeloSantana\CoquiToolkitImages\Client\OllamaImageClient;
use CarmeloSantana\CoquiToolkitImages\Client\OpenAIImageClient;
use CarmeloSantana\CoquiToolkitImages\Contract\ImageGenerationRequest;
use CarmeloSantana\CoquiToolkitImages\Contract\ImageToolkitContext;
use CarmeloSantana\CoquiToolkitImages\Exception\ImageToolkitException;
use CarmeloSantana\CoquiToolkitImages\Support\FileNameHelper;
use CarmeloSantana\CoquiToolkitImages\Support\ImagePathResolver;
use CarmeloSantana\CoquiToolkitImages\Support\ImagePreviewFormatter;
use CarmeloSantana\CoquiToolkitImages\Support\ImageRecordStore;
use CarmeloSantana\CoquiToolkitImages\Support\PngMetadataWriter;

final class ImageToolkitRuntime
{
    private readonly ImageRecordStore $recordStore;
    private readonly ImagePathResolver $pathResolver;
    private readonly PngMetadataWriter $metadataWriter;
    private readonly ImagePreviewFormatter $previewFormatter;
    private readonly OpenAIImageClient $openAIClient;
    private readonly OllamaImageClient $ollamaClient;

    public function __construct(
        private readonly ImageToolkitContext $context,
        ?ImageRecordStore $recordStore = null,
        ?ImagePathResolver $pathResolver = null,
        ?PngMetadataWriter $metadataWriter = null,
        ?ImagePreviewFormatter $previewFormatter = null,
        ?OpenAIImageClient $openAIClient = null,
        ?OllamaImageClient $ollamaClient = null,
    ) {
        $this->recordStore = $recordStore ?? new ImageRecordStore($this->context->workspacePath);
        $this->pathResolver = $pathResolver ?? new ImagePathResolver($this->context->workspacePath);
        $this->metadataWriter = $metadataWriter ?? new PngMetadataWriter();
        $this->previewFormatter = $previewFormatter ?? new ImagePreviewFormatter();

        $vendors = is_array($this->context->imageConfig['vendors'] ?? null)
            ? $this->context->imageConfig['vendors']
            : [];

        $this->openAIClient = $openAIClient ?? OpenAIImageClient::fromSettings(
            is_array($vendors['openai'] ?? null) ? $vendors['openai'] : [],
        );
        $this->ollamaClient = $ollamaClient ?? new OllamaImageClient();
    }

    public static function fromContext(ImageToolkitContext $context): self
    {
        return new self($context);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function generateFromInput(array $input): array
    {
        $request = $this->buildRequestFromInput($input);

        $directory = $this->pathResolver->resolveDirectory($request->saveDirectory, $request->profile);
        $targetPath = $this->pathResolver->buildTargetPath($directory, $request->fileHint);

        $result = match ($request->vendor) {
            'openai' => $this->openAIClient->generate($request, $targetPath),
            'ollama' => $this->ollamaClient->generate($request, $targetPath),
            default => throw ImageToolkitException::providerFailure('Unsupported image vendor: ' . $request->vendor),
        };

        $recordId = 'img_' . bin2hex(random_bytes(8));
        $metadata = [
            'CoquiPrompt' => $request->prompt,
            'CoquiVendor' => $result->vendor,
            'CoquiModel' => $result->model,
            'CoquiProfile' => $request->profile,
            'CoquiOwner' => $request->ownerName,
            'CoquiTags' => implode(',', $request->tags),
            'CoquiCategory' => $request->category,
            'CoquiRecordId' => $recordId,
        ];

        $metadataEmbedded = $this->metadataWriter->write($result->filePath, $metadata);
        $previewResult = $this->previewFormatter->format($result->filePath);

        $record = [
            'id' => $recordId,
            'path' => $result->filePath,
            'vendor' => $result->vendor,
            'model' => $result->model,
            'prompt' => $request->prompt,
            'profile' => $request->profile,
            'owner_name' => $request->ownerName,
            'tags' => $request->tags,
            'category' => $request->category,
            'size' => $request->size,
            'quality' => $request->quality,
            'metadata_embedded' => $metadataEmbedded,
            'provider_payload' => $result->providerPayload,
            'created_at' => gmdate(DATE_ATOM),
            'updated_at' => gmdate(DATE_ATOM),
        ];

        $this->recordStore->saveRecord($record);
        $record['preview'] = $previewResult['preview'];
        $record['preview_unavailable_reason'] = $previewResult['unavailable_reason'];

        return $record;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function preflightGeneration(array $input): array
    {
        [$vendor, $model] = $this->resolveVendorAndModel(
            is_string($input['model'] ?? null) ? $input['model'] : null,
            is_string($input['vendor'] ?? null) ? $input['vendor'] : null,
        );

        $snapshot = [
            'vendor' => $vendor,
            'model' => $model,
            'effective_primary_model' => $this->context->primaryModel,
            'download_required' => false,
            'can_generate' => true,
            'download_command' => null,
            'reason' => null,
        ];

        if ($vendor === 'openai') {
            $snapshot['credentials_available'] = $this->openAIClient->hasCredentials();
            if (!$snapshot['credentials_available']) {
                $snapshot['can_generate'] = false;
                $snapshot['reason'] = 'OPENAI_API_KEY is required for OpenAI image generation.';
            }

            return $snapshot;
        }

        if ($vendor === 'ollama') {
            $cliAvailable = $this->ollamaClient->isCliAvailable();
            $snapshot['ollama_cli_available'] = $cliAvailable;
            if (!$cliAvailable) {
                $snapshot['can_generate'] = false;
                $snapshot['reason'] = 'The `ollama` CLI is required for Ollama image generation.';

                return $snapshot;
            }

            $modelAvailable = $this->ollamaClient->isModelAvailable($model);
            $snapshot['model_available_locally'] = $modelAvailable;
            if (!$modelAvailable) {
                $snapshot['can_generate'] = false;
                $snapshot['download_required'] = true;
                $snapshot['download_command'] = 'ollama pull ' . $model;
                $snapshot['reason'] = sprintf(
                    'Ollama image model "%s" is not available locally and must be pulled before generation.',
                    $model,
                );
            }
        }

        return $snapshot;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listImages(?string $profile = null, ?string $vendor = null): array
    {
        return $this->recordStore->list($profile, $vendor);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchImages(string $query = '', ?string $category = null): array
    {
        return $this->recordStore->search($query, $category);
    }

    /**
     * @param string[] $tags
     * @return array<string, mixed>
     */
    public function tagImage(string $id, array $tags, ?string $category = null): array
    {
        $record = $this->recordStore->updateTags($id, $tags, $category);

        $this->metadataWriter->write($record['path'], [
            'CoquiTags' => implode(',', $record['tags'] ?? []),
            'CoquiCategory' => $record['category'] ?? '',
        ]);

        return $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getImage(string $id): ?array
    {
        return $this->recordStore->get($id);
    }

    /**
     * @return array{deleted: bool, id: string, path?: string}
     */
    public function deleteImage(string $id): array
    {
        return $this->recordStore->delete($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function configSnapshot(): array
    {
        return [
            'workspace_path' => $this->context->workspacePath,
            'images_root' => $this->pathResolver->imagesRoot(),
            'active_profile' => $this->context->activeProfile,
            'default_owner_name' => $this->context->defaultOwnerName,
            'primary_model' => $this->context->primaryModel,
            'env_image_model' => getenv('COQUI_IMAGE_MODEL') ?: null,
            'config_path_hint' => 'agents.defaults.imageModel.primary',
            'image_config' => $this->context->imageConfig,
            'supported_vendors' => ['openai', 'ollama'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildRequestFromInput(array $input): ImageGenerationRequest
    {
        [$vendor, $model] = $this->resolveVendorAndModel(
            is_string($input['model'] ?? null) ? $input['model'] : null,
            is_string($input['vendor'] ?? null) ? $input['vendor'] : null,
        );

        $profile = $this->context->activeProfile ?? 'default';
        $ownerName = $this->normalizeString($input['owner_name'] ?? null) ?? $this->context->defaultOwnerName ?? $profile;
        $fileHint = $this->normalizeString($input['file_hint'] ?? null)
            ?? $this->deriveFileHint((string) ($input['prompt'] ?? 'image'));

        return new ImageGenerationRequest(
            vendor: $vendor,
            model: $model,
            prompt: (string) ($input['prompt'] ?? ''),
            fileHint: $fileHint,
            saveDirectory: $this->normalizeString($input['save_dir'] ?? null),
            profile: FileNameHelper::sanitizeSegment($profile, 'default'),
            ownerName: $ownerName,
            tags: $this->parseTags($input['tags_json'] ?? $input['tags'] ?? null),
            category: $this->normalizeString($input['category'] ?? null),
            size: $this->normalizeString($input['size'] ?? null),
            quality: $this->normalizeString($input['quality'] ?? null),
            negativePrompt: $this->normalizeString($input['negative_prompt'] ?? null),
            seed: $this->normalizeInt($input['seed'] ?? null),
            steps: $this->normalizeInt($input['steps'] ?? null),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveVendorAndModel(?string $modelInput, ?string $vendorInput): array
    {
        $resolved = $this->normalizeString($modelInput) ?? $this->context->primaryModel;

        if ($resolved === '') {
            $resolved = $this->inferFallbackModel();
        }

        if (!str_contains($resolved, '/')) {
            $vendor = $this->normalizeString($vendorInput);
            if ($vendor === null) {
                throw ImageToolkitException::invalidModel($resolved);
            }

            return [$vendor, $resolved];
        }

        [$vendor, $model] = explode('/', $resolved, 2);

        return [trim($vendor), trim($model)];
    }

    /**
     * Infer a usable model when none is configured.
     *
     * Prefers OpenAI when an API key is set; falls back to Ollama when the CLI is available.
     */
    private function inferFallbackModel(): string
    {
        if ($this->openAIClient->hasCredentials()) {
            return 'openai/gpt-image-1';
        }

        if ($this->ollamaClient->isCliAvailable()) {
            return 'ollama/gemma3';
        }

        throw ImageToolkitException::invalidModel('(none configured)');
    }

    private function deriveFileHint(string $prompt): string
    {
        $words = preg_split('/\s+/', strtolower(trim($prompt))) ?: [];
        $words = array_filter($words, static fn(string $word): bool => $word !== '');
        $hint = implode('-', array_slice($words, 0, 6));

        return FileNameHelper::sanitizeSegment($hint, 'image');
    }

    /**
     * @return string[]
     */
    private function parseTags(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*,\s*/', trim($value)) ?: [];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $tags = [];
        foreach ($value as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $normalized = FileNameHelper::sanitizeSegment($tag, '');
            if ($normalized !== '') {
                $tags[] = $normalized;
            }
        }

        return array_values(array_unique($tags));
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}