<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Contract;

final readonly class ImageToolkitContext
{
    /**
     * @param array<string, mixed> $imageConfig
     */
    public function __construct(
        public string $workspacePath,
        public ?string $activeProfile,
        public ?string $defaultOwnerName,
        public string $primaryModel,
        public array $imageConfig,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public static function fromArray(array $context = []): self
    {
        $workspacePath = self::stringValue($context['workspacePath'] ?? null)
            ?? self::stringValue(getenv('COQUI_WORKSPACE'))
            ?? (string) getcwd();

        $activeProfile = self::normalizeNullableString(
            $context['activeProfile'] ?? getenv('COQUI_ACTIVE_PROFILE') ?: null,
        );

        $imageConfig = self::extractImageConfig($context['config'] ?? null);
        $primaryModel = self::resolvePrimaryModel($context, $imageConfig);
        $defaultOwnerName = self::normalizeNullableString(
            $context['ownerName']
                ?? $imageConfig['ownerName']
                ?? getenv('COQUI_IMAGE_OWNER')
                ?: $activeProfile,
        );

        return new self(
            workspacePath: rtrim($workspacePath, '/'),
            activeProfile: $activeProfile,
            defaultOwnerName: $defaultOwnerName,
            primaryModel: $primaryModel,
            imageConfig: $imageConfig,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $imageConfig
     */
    private static function resolvePrimaryModel(array $context, array $imageConfig): string
    {
        $directModel = self::normalizeNullableString($context['imageModel'] ?? null);
        if ($directModel !== null) {
            return $directModel;
        }

        $configModel = self::normalizeNullableString($imageConfig['primary'] ?? null);
        if ($configModel !== null) {
            return $configModel;
        }

        $envModel = self::normalizeNullableString(getenv('COQUI_IMAGE_MODEL') ?: null);
        if ($envModel !== null) {
            return $envModel;
        }

        if (is_object($context['config'] ?? null) && method_exists($context['config'], 'getImageModel')) {
            $value = $context['config']->getImageModel();
            $resolved = self::normalizeNullableString($value);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return 'openai/gpt-image-1.5';
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractImageConfig(mixed $config): array
    {
        if (is_object($config) && method_exists($config, 'get')) {
            $value = $config->get('agents.defaults.imageModel', []);
            return is_array($value) ? $value : [];
        }

        return [];
    }

    private static function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}