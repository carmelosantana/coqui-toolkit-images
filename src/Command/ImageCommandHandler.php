<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Command;

use CarmeloSantana\CoquiToolkitImages\Support\ImageCommandParser;
use CarmeloSantana\CoquiToolkitImages\Support\OllamaModelPullHelper;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CoquiBot\Coqui\Contract\ToolkitCommandHandler;
use CoquiBot\Coqui\Contract\ToolkitCommandExample;
use CoquiBot\Coqui\Contract\ToolkitCommandHelp;
use CoquiBot\Coqui\Contract\ToolkitCommandHelpEntry;
use CoquiBot\Coqui\Contract\ToolkitCommandHelpProvider;
use CoquiBot\Coqui\Contract\ToolkitReplContext;
use CoquiBot\Coqui\Contract\ToolkitTabCompletionProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Self-registering REPL command handler for the /image command.
 *
 * Provides image generation, library management, and configuration
 * inspection through the Coqui REPL. Discovered automatically by
 * the SlashCommandRouter when the toolkit is enabled.
 */
final class ImageCommandHandler implements ToolkitCommandHandler, ToolkitCommandHelpProvider, ToolkitTabCompletionProvider
{
    private const string GENERATE_OUTPUT_FORMAT = 'json';

    private readonly ImageCommandParser $parser;

    /**
     * @param list<ToolInterface> $tools Built tool instances from the parent toolkit
     */
    public function __construct(
        private readonly array $tools,
    ) {
        $this->parser = new ImageCommandParser();
    }

    public function commandName(): string
    {
        return 'image';
    }

    public function subcommands(): array
    {
        return ['generate', 'preview', 'list', 'search', 'get', 'tag', 'delete', 'config'];
    }

    public function usage(): string
    {
        return '/image [action]';
    }

    public function description(): string
    {
        return 'Generate, preview, and manage workspace images through the image toolkit.';
    }

    public function help(): ToolkitCommandHelp
    {
        return new ToolkitCommandHelp(
            title: 'Image Generation & Management',
            summary: 'Generate images, inspect previews, and manage the workspace image library from the Coqui REPL.',
            subcommands: [
                new ToolkitCommandHelpEntry(
                    'generate',
                    '/image generate <prompt> [--model=vendor/model] [--vendor=openai|ollama] [--tags=a,b] [--category=name]',
                    'Generate an image and save it into the workspace image library.',
                ),
                new ToolkitCommandHelpEntry(
                    'preview',
                    '/image preview <path> [--width=60]',
                    'Render an existing workspace image as a low-fidelity terminal preview.',
                ),
                new ToolkitCommandHelpEntry(
                    'list',
                    '/image list [--profile=name] [--vendor=openai|ollama]',
                    'List saved image records, optionally filtered by profile or vendor.',
                ),
                new ToolkitCommandHelpEntry(
                    'search',
                    '/image search <query> [--category=name]',
                    'Search saved image records by prompt, tags, model, vendor, or path.',
                ),
                new ToolkitCommandHelpEntry(
                    'get',
                    '/image get <record-id>',
                    'Show the full metadata for one saved image record.',
                ),
                new ToolkitCommandHelpEntry(
                    'tag',
                    '/image tag <record-id> <tag1,tag2> [--category=name]',
                    'Update tags and an optional category for a saved image record.',
                ),
                new ToolkitCommandHelpEntry(
                    'delete',
                    '/image delete <record-id>',
                    'Delete a saved image record from the workspace image library.',
                ),
                new ToolkitCommandHelpEntry(
                    'config',
                    '/image config',
                    'Show the resolved image-generation defaults, vendor settings, and workspace paths.',
                ),
            ],
            examples: [
                new ToolkitCommandExample(
                    '/image generate Studio portrait of a red fox in cinematic light --vendor=openai --tags=portrait,fox',
                    'Generate and tag an image in one step.',
                ),
                new ToolkitCommandExample(
                    '/image search fox',
                    'Find earlier images by prompt fragments or metadata.',
                ),
                new ToolkitCommandExample(
                    '/image preview images/caelum/example.png --width=72',
                    'Re-open an existing image as a terminal preview.',
                ),
            ],
            notes: [
                'When the resolved model is an Ollama image model that is not installed locally, Coqui asks for confirmation before running `ollama pull`.',
                'The default save root is `workspace/images/{profile}/...`, and PNG metadata is embedded when supported.',
            ],
        );
    }

    public function handle(ToolkitReplContext $context, string $arg): void
    {
        $arg = trim($arg);

        if ($arg === '' || $arg === 'help') {
            $context->io->text('Use /image or /image help from the Coqui REPL to view the image toolkit command reference.');
            return;
        }

        [$command, $rest] = array_pad(explode(' ', $arg, 2), 2, '');
        $tools = $this->resolveTools($context);

        if ($tools === null) {
            $context->io->warning([
                'The image toolkit is not currently available.',
                'Install or enable `carmelosantana/coqui-toolkit-images`, then restart Coqui.',
            ]);
            return;
        }

        $result = match ($command) {
            'generate' => $this->generate($context, $rest, $tools),
            'preview' => $this->dispatchParsed($this->parser->parsePreviewInput($rest), $tools['image_preview']),
            'list' => $this->dispatchParsed($this->parser->parseListInput($rest), $tools['image_library']),
            'search' => $this->dispatchParsed($this->parser->parseSearchInput($rest), $tools['image_library']),
            'get' => $this->dispatchParsed($this->parser->parseGetInput($rest), $tools['image_library']),
            'tag' => $this->dispatchParsed($this->parser->parseTagInput($rest), $tools['image_library']),
            'delete' => $this->dispatchParsed($this->parser->parseDeleteInput($rest), $tools['image_library']),
            'config' => $tools['image_config']->execute([]),
            default => ToolResult::error('Unknown /image subcommand: ' . $command),
        };

        $this->renderResult($context->io, $result, $command);
    }

    public function completeArguments(string $commandName, array $parts): array
    {
        // Only provide static subcommand completion for now
        // Future: dynamic record IDs for get/tag/delete
        return $this->subcommands();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function dispatchParsed(array $input, ToolInterface $tool): ToolResult
    {
        if (isset($input['__error'])) {
            return ToolResult::error((string) $input['__error']);
        }

        return $tool->execute($input);
    }

    /**
     * @return array{image_preflight?: ToolInterface|null, image_generate: ToolInterface, image_preview: ToolInterface, image_library: ToolInterface, image_config: ToolInterface}|null
     */
    private function resolveTools(ToolkitReplContext $context): ?array
    {
        $resolved = [];
        foreach ($this->tools as $tool) {
            $resolved[$tool->name()] = $tool;
        }

        if (isset($resolved['image_generate'], $resolved['image_preview'], $resolved['image_library'], $resolved['image_config'])) {
            return [
                'image_preflight' => $resolved['image_preflight'] ?? null,
                'image_generate' => $resolved['image_generate'],
                'image_preview' => $resolved['image_preview'],
                'image_library' => $resolved['image_library'],
                'image_config' => $resolved['image_config'],
            ];
        }

        return null;
    }

    /**
    * @param array{image_preflight?: ToolInterface|null, image_generate: ToolInterface, image_preview: ToolInterface, image_library: ToolInterface, image_config: ToolInterface} $tools
     */
    private function generate(ToolkitReplContext $context, string $arg, array $tools): ToolResult
    {
        $input = $this->parser->parseGenerateInput($arg);
        if (isset($input['__error'])) {
            return ToolResult::error((string) $input['__error']);
        }

        $preflightTool = $tools['image_preflight'] ?? null;
        $resolvedModel = null;

        if ($preflightTool instanceof ToolInterface) {
            $preflight = $this->runGeneratePreflight($context, $preflightTool, $input);
            if ($preflight instanceof ToolResult) {
                return $preflight;
            }

            if (isset($preflight['vendor'], $preflight['model']) && is_string($preflight['vendor']) && is_string($preflight['model'])) {
                $resolvedModel = $preflight['vendor'] . '/' . $preflight['model'];
            }
        }

        $input['output_format'] = self::GENERATE_OUTPUT_FORMAT;
        $spinner = $context->createSpinner($this->defaultGenerateContext($resolvedModel));
        $isInteractive = $this->isInteractiveTerminal($context->io);

        if ($isInteractive) {
            $spinner->start($this->defaultGenerateContext($resolvedModel));
            $input['__progress_callback'] = function (string $status) use ($spinner): void {
                $spinner->setContext($this->formatGenerateProgressContext($status));
                $spinner->tick();
            };
        } elseif ($resolvedModel !== null) {
            $context->io->text(sprintf('<fg=gray>Generating image with %s. This may take a while.</>', $resolvedModel));
        } else {
            $context->io->text('<fg=gray>Generating image. This may take a while.</>');
        }

        try {
            return $tools['image_generate']->execute($input);
        } finally {
            if ($isInteractive) {
                $spinner->stop();
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|ToolResult
     */
    private function runGeneratePreflight(ToolkitReplContext $context, ToolInterface $tool, array $input): array|ToolResult
    {
        $preflightResult = $tool->execute($input);
        if ($preflightResult->status->value === 'error') {
            return $preflightResult;
        }

        $payload = json_decode($preflightResult->content, true);
        if (!is_array($payload)) {
            return ToolResult::error('Image preflight returned an invalid response.');
        }

        $downloadRequired = ($payload['download_required'] ?? false) === true;
        $canGenerate = ($payload['can_generate'] ?? false) === true;

        if (!$downloadRequired) {
            if (!$canGenerate) {
                return ToolResult::error((string) ($payload['reason'] ?? 'Image generation prerequisites are not satisfied.'));
            }

            return $payload;
        }

        $model = is_string($payload['model'] ?? null) ? $payload['model'] : null;
        if ($model === null || $model === '') {
            return ToolResult::error('Image preflight did not report which Ollama model needs to be downloaded.');
        }

        if (!$this->isInteractiveTerminal($context->io)) {
            return ToolResult::error(sprintf(
                'Ollama image model "%s" is not available locally. Pull it first with `%s`, then retry `/image generate`.',
                $model,
                $payload['download_command'] ?? ('ollama pull ' . $model),
            ));
        }

        $confirm = $context->prompt->confirm(
            sprintf('Ollama image model "%s" is not installed locally. Download it now?', $model),
            false,
        );

        if (!$confirm) {
            return ToolResult::success('Image generation cancelled before downloading the required Ollama model.');
        }

        $pullHelper = new OllamaModelPullHelper();
        $pullResult = $pullHelper->pull($context->io, $model);
        if ($pullResult->status->value === 'error') {
            return $pullResult;
        }

        return $payload;
    }

    private function isInteractiveTerminal(SymfonyStyle $io): bool
    {
        $input = $this->readStyleProperty($io, 'input');
        if (!$input instanceof InputInterface) {
            return false;
        }

        if (!$input->isInteractive()) {
            return false;
        }

        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDIN);
        }

        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDIN);
        }

        return false;
    }

    private function renderResult(SymfonyStyle $io, ToolResult $result, string $command): void
    {
        if ($result->status->value === 'error') {
            $io->error($result->content);
            if (str_starts_with($result->content, 'Usage: /image')) {
                $io->text('<fg=gray>Use /image or /image help for the image command reference.</>');
            }
            return;
        }

        if ($command === 'generate') {
            $this->renderGenerateResult($io, $result);
            return;
        }

        if ($command === 'preview') {
            $this->renderPreviewResult($io, $result);
            return;
        }

        if (in_array($command, ['list', 'search', 'get', 'tag', 'delete', 'config'], true)) {
            $io->write($result->content);
            $io->newLine();
            return;
        }

        $this->renderMultiline($io, $result->content);
    }

    private function renderGenerateResult(SymfonyStyle $io, ToolResult $result): void
    {
        $payload = json_decode($result->content, true);
        if (!is_array($payload)) {
            $this->renderMultiline($io, $result->content);

            return;
        }

        $message = is_string($payload['message'] ?? null)
            ? $payload['message']
            : 'Image generated successfully.';
        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $path = is_string($payload['saved_path'] ?? null)
            ? $payload['saved_path']
            : (is_string($record['path'] ?? null) ? $record['path'] : null);
        $preview = is_string($payload['preview'] ?? null) ? $payload['preview'] : null;
        $previewFormat = is_string($payload['preview_format'] ?? null) ? $payload['preview_format'] : null;
        $previewReason = is_string($payload['preview_unavailable_reason'] ?? null) ? $payload['preview_unavailable_reason'] : null;
        $metadataReason = is_string($payload['metadata_unavailable_reason'] ?? null) ? $payload['metadata_unavailable_reason'] : null;

        $io->success($message);

        $lines = [];
        if ($path !== null) {
            $lines[] = '<fg=gray>Saved path:</> ' . $path;
            $lines[] = '<fg=gray>Open:</> file://' . $path;
        }

        if (is_string($record['id'] ?? null)) {
            $lines[] = '<fg=gray>Record ID:</> ' . $record['id'];
        }

        if (is_string($record['vendor'] ?? null) && is_string($record['model'] ?? null)) {
            $lines[] = '<fg=gray>Model:</> ' . $record['vendor'] . '/' . $record['model'];
        }

        if (is_string($record['format'] ?? null) && $record['format'] !== '') {
            $lines[] = '<fg=gray>Format:</> ' . strtoupper((string) $record['format']);
        }

        if ($previewFormat !== null) {
            $lines[] = '<fg=gray>Preview format:</> ' . $previewFormat;
        }

        $lines[] = '<fg=gray>Metadata embedded:</> ' . ((($record['metadata_embedded'] ?? false) === true) ? 'yes' : 'no');

        if ($metadataReason !== null) {
            $lines[] = '<fg=gray>Metadata note:</> ' . $metadataReason;
        }

        $io->text($lines);

        if ($preview !== null && trim($preview) !== '') {
            $io->newLine();
            $io->section('Preview');
            $this->renderMultiline($io, $preview);

            return;
        }

        if ($previewReason !== null) {
            $io->newLine();
            $io->text('<fg=gray>Preview unavailable:</> ' . $previewReason);
        }
    }

    private function renderPreviewResult(SymfonyStyle $io, ToolResult $result): void
    {
        $payload = json_decode($result->content, true);
        if (!is_array($payload)) {
            $this->renderMultiline($io, $result->content);

            return;
        }

        $message = is_string($payload['message'] ?? null)
            ? $payload['message']
            : 'Image preview generated successfully.';
        $path = is_string($payload['path'] ?? null) ? $payload['path'] : null;
        $preview = is_string($payload['preview'] ?? null) ? $payload['preview'] : null;
        $previewFormat = is_string($payload['preview_format'] ?? null) ? $payload['preview_format'] : null;

        $io->success($message);

        $lines = [];
        if ($path !== null) {
            $lines[] = '<fg=gray>Image path:</> ' . $path;
            $lines[] = '<fg=gray>Open:</> file://' . $path;
        }

        if ($previewFormat !== null) {
            $lines[] = '<fg=gray>Preview format:</> ' . $previewFormat;
        }

        if ($lines !== []) {
            $io->text($lines);
        }

        if ($preview !== null && trim($preview) !== '') {
            $io->newLine();
            $io->section('Preview');
            $this->renderMultiline($io, $preview);
        }
    }

    private function renderMultiline(SymfonyStyle $io, string $content): void
    {
        $io->writeln(preg_split("/\r\n|\n|\r/", $content) ?: [$content]);
    }

    private function defaultGenerateContext(?string $resolvedModel): string
    {
        if ($resolvedModel === null) {
            return 'image generation';
        }

        return 'image generation with ' . $resolvedModel;
    }

    private function formatGenerateProgressContext(string $status): string
    {
        $normalized = trim(strtolower($status));

        return match ($normalized) {
            'contacting-openai' => 'contacting OpenAI',
            'writing-image' => 'writing generated image',
            'finalizing-image' => 'finalizing generated image',
            default => preg_replace('/\s+/', ' ', str_replace('-', ' ', $normalized)) ?? 'image generation',
        };
    }

    private function readStyleProperty(SymfonyStyle $io, string $property): mixed
    {
        $reflection = new \ReflectionObject($io);

        while ($reflection !== false) {
            if ($reflection->hasProperty($property)) {
                $resolvedProperty = $reflection->getProperty($property);
                $resolvedProperty->setAccessible(true);

                return $resolvedProperty->getValue($io);
            }

            $reflection = $reflection->getParentClass();
        }

        return null;
    }
}
