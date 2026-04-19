<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Support;

use CarmeloSantana\CoquiToolkitImages\Exception\ImageToolkitException;

final readonly class ImagePathResolver
{
    public function __construct(
        private string $workspacePath,
    ) {}

    public function imagesRoot(): string
    {
        return $this->workspacePath . '/images';
    }

    public function resolveDirectory(?string $saveDirectory, string $profile): string
    {
        if ($saveDirectory === null || trim($saveDirectory) === '') {
            return $this->imagesRoot() . '/' . FileNameHelper::sanitizeSegment($profile, 'default');
        }

        $trimmed = trim($saveDirectory);

        if ($trimmed[0] === '/') {
            $resolved = rtrim($trimmed, '/');
        } else {
            $resolved = rtrim($this->workspacePath . '/' . ltrim($trimmed, '/'), '/');
        }

        if (!is_dir($resolved)) {
            mkdir($resolved, 0755, true);
        }

        $realWorkspace = realpath($this->workspacePath);
        $realResolved = realpath($resolved);

        if ($realWorkspace === false || $realResolved === false || !str_starts_with($realResolved, $realWorkspace . '/')) {
            throw ImageToolkitException::saveOutsideWorkspace($saveDirectory);
        }

        return $realResolved;
    }

    public function buildTargetPath(string $directory, string $fileHint, ?string $timestamp = null): string
    {
        $timestamp ??= gmdate('Ymd-His');

        return sprintf(
            '%s/%s-%s.png',
            rtrim($directory, '/'),
            FileNameHelper::sanitizeSegment($fileHint, 'image'),
            $timestamp,
        );
    }

}