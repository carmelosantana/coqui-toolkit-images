<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages;

use CarmeloSantana\CoquiToolkitImages\Command\ImageCommandHandler;
use CarmeloSantana\CoquiToolkitImages\Contract\ImageToolkitContext;
use CarmeloSantana\CoquiToolkitImages\Tool\ImageConfigTool;
use CarmeloSantana\CoquiToolkitImages\Tool\ImageGenerateTool;
use CarmeloSantana\CoquiToolkitImages\Tool\ImageLibraryTool;
use CarmeloSantana\CoquiToolkitImages\Tool\ImagePreflightTool;
use CarmeloSantana\CoquiToolkitImages\Tool\ImagePreviewTool;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CoquiBot\Coqui\Contract\ReplCommandProvider;
use CoquiBot\Coqui\Contract\ToolkitCommandHandler;

final class ImagesToolkit implements ToolkitInterface, ReplCommandProvider
{
    private readonly ImageToolkitRuntime $runtime;

    public function __construct(
        ?ImageToolkitRuntime $runtime = null,
        string $workspacePath = '',
    ) {
        $this->runtime = $runtime ?? ImageToolkitRuntime::fromContext(
            ImageToolkitContext::fromArray([
                'workspacePath' => $workspacePath,
            ]),
        );
    }

    public static function fromEnv(): self
    {
        return new self(ImageToolkitRuntime::fromContext(ImageToolkitContext::fromArray()));
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function fromCoquiContext(array $context): self
    {
        return new self(ImageToolkitRuntime::fromContext(ImageToolkitContext::fromArray($context)));
    }

    /**
     * @return list<ToolInterface>
     */
    public function tools(): array
    {
        return [
            (new ImagePreflightTool($this->runtime))->build(),
            (new ImageGenerateTool($this->runtime))->build(),
            (new ImagePreviewTool($this->runtime))->build(),
            (new ImageLibraryTool($this->runtime))->build(),
            (new ImageConfigTool($this->runtime))->build(),
        ];
    }

    public function guidelines(): string
    {
        return <<<'GUIDELINES'
        <IMAGES-TOOLKIT-GUIDELINES>
        ## Images Toolkit

        Use this toolkit to generate, organize, and search AI-generated images.

        ### Tool Overview
        - `image_generate` — create an image from a natural-language prompt and save it into the workspace image library
        - `image_preflight` — resolve the effective image model and check whether Ollama pulls or credentials are still required
        - `image_preview` — render an existing workspace image as a low-fidelity colored block preview for REPL display
        - `image_library` — inspect, search, tag, categorize, and annotate saved images
        - `image_config` — inspect the resolved image generation defaults and vendor settings

        ### Preferred Workflow
        1. Check prerequisites with `image_preflight` when the selected backend may need a local Ollama pull or credentials
        2. Generate with `image_generate` using a clear prompt and optional file hint
        3. Review the returned low-fidelity preview and saved path
        4. Add or update tags and category with `image_library(action: "tag", ...)`
        5. Use `image_preview` to show an existing workspace image again without reopening an external viewer
        6. Reuse `image_library(action: "search", ...)` to find related images later

        ### Important Notes
        - Image generation is independent from the active chat model
        - The default save root is `workspace/images/{profile}/...`
        - Prompt, model, vendor, profile, owner, tags, and category are persisted in the library index and embedded into PNG metadata when possible
        - Low-fidelity previews use `ext-gd` and ANSI colored block characters in terminal-friendly text output
        - Ollama image generation is experimental and currently uses the local `ollama` CLI
        - Ollama models are never pulled implicitly by the toolkit; callers should confirm downloads before running `ollama pull`
        - Use the saved path when handing images off to `vision_analyze` or loop workflows
        </IMAGES-TOOLKIT-GUIDELINES>
        GUIDELINES;
    }

    /**
     * @return list<ToolkitCommandHandler>
     */
    public function commandHandlers(): array
    {
        return [new ImageCommandHandler($this->tools())];
    }
}