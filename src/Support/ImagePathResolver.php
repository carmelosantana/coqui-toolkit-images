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

        $realWorkspace = $this->workspaceRealPath();
        $realResolved = realpath($resolved);

        if ($realResolved === false || !$this->isWithinWorkspace($realResolved, $realWorkspace)) {
            throw ImageToolkitException::saveOutsideWorkspace($saveDirectory);
        }

        return $realResolved;
    }

    public function resolveExistingPath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            throw ImageToolkitException::previewPathRequired();
        }

        $workspace = $this->workspaceRealPath();
        $candidate = $trimmed[0] === '/'
            ? $trimmed
            : $this->workspacePath . '/' . ltrim($trimmed, '/');

        $directory = realpath(dirname($candidate));
        if ($directory === false) {
            throw ImageToolkitException::imageFileNotFound($path);
        }

        if (!$this->isWithinWorkspace($directory, $workspace)) {
            throw ImageToolkitException::saveOutsideWorkspace($path);
        }

        $resolved = realpath($directory . '/' . basename($candidate));
        if ($resolved === false || !is_file($resolved)) {
            throw ImageToolkitException::imageFileNotFound($path);
        }

        if (!$this->isWithinWorkspace($resolved, $workspace)) {
            throw ImageToolkitException::saveOutsideWorkspace($path);
        }

        return $resolved;
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

    private function workspaceRealPath(): string
    {
        $resolved = realpath($this->workspacePath);
        if ($resolved === false) {
            throw ImageToolkitException::saveOutsideWorkspace($this->workspacePath);
        }

        return $resolved;
    }

    private function isWithinWorkspace(string $resolvedPath, string $resolvedWorkspace): bool
    {
        return $resolvedPath === $resolvedWorkspace || str_starts_with($resolvedPath, $resolvedWorkspace . '/');
    }

}