<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Support;

use CarmeloSantana\PHPAgents\Tool\ToolResult;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Interactive Ollama model download with progress feedback.
 *
 * Extracted from Coqui core so all Ollama-specific image UX lives in the toolkit.
 */
final class OllamaModelPullHelper
{
    public function __construct(
        private readonly string $binary = 'ollama',
    ) {}

    public function pull(SymfonyStyle $io, string $model): ToolResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open([$this->binary, 'pull', $model], $descriptors, $pipes);
        if (!is_resource($process)) {
            return ToolResult::error('Failed to start `ollama pull`. Make sure the `ollama` CLI is installed and available on PATH.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $spinner = ['|', '/', '-', '\\'];
        $spinnerIndex = 0;
        $statusText = 'Preparing download...';
        $statusBuffer = '';

        $io->newLine();
        $io->writeln(sprintf('<fg=gray>Downloading Ollama image model %s. This may take several minutes.</>', $model));

        while (true) {
            $status = proc_get_status($process);
            $statusBuffer .= stream_get_contents($pipes[1]) ?: '';
            $statusBuffer .= stream_get_contents($pipes[2]) ?: '';

            $parsedStatus = $this->extractPullStatus($statusBuffer);
            if ($parsedStatus !== null) {
                $statusText = $parsedStatus;
            }

            $io->write(sprintf("\r%s Downloading %s %s", $spinner[$spinnerIndex % count($spinner)], $model, $statusText));
            $spinnerIndex++;

            if (!$status['running']) {
                break;
            }

            usleep(100000);
        }

        $statusBuffer .= stream_get_contents($pipes[1]) ?: '';
        $statusBuffer .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $io->write("\r" . str_repeat(' ', 160) . "\r");
        $io->newLine();

        if ($exitCode !== 0) {
            $message = $this->extractPullStatus($statusBuffer) ?? 'Ollama reported a pull failure.';

            return ToolResult::error(sprintf('Failed to download Ollama image model "%s": %s', $model, $message));
        }

        $io->success(sprintf('Downloaded Ollama image model %s.', $model));

        return ToolResult::success('Ollama model download completed.');
    }

    private function extractPullStatus(string $buffer): ?string
    {
        $clean = preg_replace('/\e\[[\d;]*[A-Za-z]/', '', $buffer);
        if (!is_string($clean) || trim($clean) === '') {
            return null;
        }

        $lines = preg_split('/[\r\n]+/', $clean) ?: [];
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim(preg_replace('/\s+/', ' ', $lines[$index]) ?? '');
            if ($line === '') {
                continue;
            }

            if (str_contains($line, 'pulling') || str_contains($line, 'verifying') || str_contains($line, 'writing') || str_contains($line, 'success')) {
                return $line;
            }
        }

        return trim(preg_replace('/\s+/', ' ', end($lines) ?: '') ?: '') ?: null;
    }
}
