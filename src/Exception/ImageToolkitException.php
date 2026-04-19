<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Exception;

final class ImageToolkitException extends \RuntimeException
{
    public static function invalidModel(string $model): self
    {
        return new self(sprintf('Invalid image model "%s". Expected vendor/model format.', $model));
    }

    public static function saveOutsideWorkspace(string $directory): self
    {
        return new self(sprintf('Image save directory must stay inside the workspace. Rejected: %s', $directory));
    }

    public static function recordNotFound(string $id): self
    {
        return new self(sprintf('Image record "%s" was not found.', $id));
    }

    public static function openAiCredentialsMissing(): self
    {
        return new self('OPENAI_API_KEY is required for OpenAI image generation.');
    }

    public static function providerFailure(string $message): self
    {
        return new self($message);
    }
}