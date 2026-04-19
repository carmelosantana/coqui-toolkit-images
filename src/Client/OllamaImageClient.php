<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Client;

use CarmeloSantana\CoquiToolkitImages\Contract\ImageClientInterface;
use CarmeloSantana\CoquiToolkitImages\Contract\ImageGenerationRequest;
use CarmeloSantana\CoquiToolkitImages\Contract\ImageGenerationResult;
use CarmeloSantana\CoquiToolkitImages\Exception\ImageToolkitException;

final readonly class OllamaImageClient implements ImageClientInterface
{
    public function __construct(
        private string $binary = 'ollama',
    ) {}

    public function generate(ImageGenerationRequest $request, string $targetPath): ImageGenerationResult
    {
        $this->assertCliAvailable();
        $this->assertModelAvailable($request->model);

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $isolatedDir = sys_get_temp_dir() . '/coqui-ollama-gen-' . bin2hex(random_bytes(8));
        mkdir($isolatedDir, 0755, true);

        try {
            $command = [$this->binary, 'run', $request->model, $request->prompt];
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($command, $descriptors, $pipes, $isolatedDir);
            if (!is_resource($process)) {
                throw ImageToolkitException::providerFailure('Failed to start the ollama CLI. Make sure Ollama is installed and available on PATH.');
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            $candidates = $this->listCandidateImages($isolatedDir);

            if ($candidates === []) {
                $message = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
                throw ImageToolkitException::providerFailure(
                    $message !== ''
                        ? 'Ollama did not produce an image file: ' . $message
                        : 'Ollama did not produce an image file.',
                );
            }

            $source = $this->resolveNewestPath($isolatedDir, $candidates);
            rename($source, $targetPath);

            if ($exitCode !== 0 && trim($stderr) !== '') {
                throw ImageToolkitException::providerFailure('Ollama generation failed: ' . trim($stderr));
            }

            return new ImageGenerationResult(
                vendor: 'ollama',
                model: $request->model,
                filePath: $targetPath,
                providerPayload: [
                    'stdout' => trim($stdout),
                    'stderr' => trim($stderr),
                    'exit_code' => $exitCode,
                ],
            );
        } finally {
            $this->cleanupDirectory($isolatedDir);
        }
    }

    public function isCliAvailable(): bool
    {
        [$exitCode] = $this->runCommand([$this->binary, '--version']);

        return $exitCode === 0;
    }

    public function isModelAvailable(string $model): bool
    {
        [$exitCode] = $this->runCommand([$this->binary, 'show', $model, '--modelfile']);

        return $exitCode === 0;
    }

    public function assertCliAvailable(): void
    {
        if ($this->isCliAvailable()) {
            return;
        }

        throw ImageToolkitException::providerFailure(
            'The `ollama` CLI is required for Ollama image generation. Install Ollama and make sure `ollama` is available on PATH.',
        );
    }

    public function assertModelAvailable(string $model): void
    {
        if ($this->isModelAvailable($model)) {
            return;
        }

        throw ImageToolkitException::providerFailure(sprintf(
            'Ollama image model "%s" is not available locally. Pull it first with `ollama pull %s`.',
            $model,
            $model,
        ));
    }

    /**
     * @return string[]
     */
    private function listCandidateImages(string $dir): array
    {
        $matches = glob($dir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE);

        return is_array($matches) ? $matches : [];
    }

    /**
     * @param string[] $paths
     */
    private function resolveNewestPath(string $dir, array $paths): string
    {
        usort($paths, static fn(string $left, string $right): int => filemtime($right) <=> filemtime($left));

        return $paths[0];
    }

    private function cleanupDirectory(string $dir): void
    {
        $files = glob($dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }

    /**
     * @param list<string> $command
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCommand(array $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return [127, '', ''];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }
}