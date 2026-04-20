<?php

declare(strict_types=1);

namespace CoquiBot\Coqui\Contract {
    if (!interface_exists(ReplCommandProvider::class)) {
        interface ReplCommandProvider
        {
            public function commandHandlers(): array;
        }
    }

    if (!class_exists(ToolkitReplContext::class)) {
        final class ToolkitReplContext
        {
        }
    }

    if (!interface_exists(ToolkitCommandHandler::class)) {
        interface ToolkitCommandHandler
        {
            public function commandName(): string;

            public function subcommands(): array;

            public function usage(): string;

            public function description(): string;

            public function handle(ToolkitReplContext $context, string $arg): void;
        }
    }

    if (!interface_exists(ToolkitTabCompletionProvider::class)) {
        interface ToolkitTabCompletionProvider
        {
            public function completeArguments(string $commandName, array $parts): array;
        }
    }

    if (!class_exists(ToolkitCommandExample::class)) {
        final readonly class ToolkitCommandExample
        {
            public function __construct(
                public string $command,
                public string $description = '',
            ) {}
        }
    }

    if (!class_exists(ToolkitCommandHelpEntry::class)) {
        final readonly class ToolkitCommandHelpEntry
        {
            public function __construct(
                public string $name,
                public string $usage,
                public string $description,
            ) {}
        }
    }

    if (!class_exists(ToolkitCommandHelp::class)) {
        final readonly class ToolkitCommandHelp
        {
            public function __construct(
                public ?string $title = null,
                public ?string $summary = null,
                public array $subcommands = [],
                public array $examples = [],
                public array $notes = [],
            ) {}
        }
    }

    if (!interface_exists(ToolkitCommandHelpProvider::class)) {
        interface ToolkitCommandHelpProvider
        {
            public function help(): ToolkitCommandHelp;
        }
    }
}