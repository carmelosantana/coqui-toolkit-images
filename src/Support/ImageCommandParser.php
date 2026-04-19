<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Support;

/**
 * Parses REPL /image subcommand arguments into tool-ready input arrays.
 *
 * Extracted from Coqui core so image-specific CLI parsing lives in the toolkit.
 */
final class ImageCommandParser
{
    /**
     * Parse a generate subcommand string into tool input.
     *
     * @return array<string, mixed>
     */
    public function parseGenerateInput(string $arg): array
    {
        [$tokens, $options] = $this->parseTokensAndOptions($arg);
        $prompt = trim(implode(' ', $tokens));

        if ($prompt === '') {
            return ['__error' => 'Usage: /image generate <prompt> [--model=vendor/model] [--vendor=openai|ollama] [--tags=a,b] [--category=name]'];
        }

        $input = ['prompt' => $prompt];

        $this->copyOption($options, $input, 'model');
        $this->copyOption($options, $input, 'vendor');
        $this->copyOption($options, $input, 'file-hint', 'file_hint');
        $this->copyOption($options, $input, 'save-dir', 'save_dir');
        $this->copyOption($options, $input, 'category');
        $this->copyOption($options, $input, 'size');
        $this->copyOption($options, $input, 'quality');
        $this->copyOption($options, $input, 'negative-prompt', 'negative_prompt');
        $this->copyOption($options, $input, 'owner', 'owner_name');
        $this->copyOption($options, $input, 'owner-name', 'owner_name');

        if (isset($options['seed']) && is_numeric($options['seed'])) {
            $input['seed'] = (int) $options['seed'];
        }

        if (isset($options['steps']) && is_numeric($options['steps'])) {
            $input['steps'] = (int) $options['steps'];
        }

        if (isset($options['tags'])) {
            $input['tags_json'] = $this->encodeTags($options['tags']);
        }

        return $input;
    }

    /**
     * Parse a list subcommand string into tool input.
     *
     * @return array<string, mixed>
     */
    public function parseListInput(string $arg): array
    {
        [, $options] = $this->parseTokensAndOptions($arg);

        $input = ['action' => 'list'];
        $this->copyOption($options, $input, 'profile');
        $this->copyOption($options, $input, 'vendor');

        return $input;
    }

    /**
     * Parse a search subcommand string into tool input.
     *
     * @return array<string, mixed>
     */
    public function parseSearchInput(string $arg): array
    {
        [$tokens, $options] = $this->parseTokensAndOptions($arg);
        $query = trim(implode(' ', $tokens));

        if ($query === '') {
            return ['__error' => 'Usage: /image search <query> [--category=name]'];
        }

        $input = [
            'action' => 'search',
            'query' => $query,
        ];
        $this->copyOption($options, $input, 'category');

        return $input;
    }

    /**
     * Parse a get subcommand string into tool input.
     *
     * @return array<string, mixed>
     */
    public function parseGetInput(string $arg): array
    {
        [$tokens] = $this->parseTokensAndOptions($arg);
        $id = $tokens[0] ?? '';

        if ($id === '') {
            return ['__error' => 'Usage: /image get <record-id>'];
        }

        return [
            'action' => 'get',
            'id' => $id,
        ];
    }

    /**
     * Parse a tag subcommand string into tool input.
     *
     * @return array<string, mixed>
     */
    public function parseTagInput(string $arg): array
    {
        [$tokens, $options] = $this->parseTokensAndOptions($arg);
        $id = $tokens[0] ?? '';
        $tags = $options['tags'] ?? ($tokens[1] ?? '');

        if ($id === '' || trim($tags) === '') {
            return ['__error' => 'Usage: /image tag <record-id> <tag1,tag2> [--category=name]'];
        }

        $input = [
            'action' => 'tag',
            'id' => $id,
            'tags_json' => $this->encodeTags($tags),
        ];
        $this->copyOption($options, $input, 'category');

        return $input;
    }

    /**
     * Parse a delete subcommand string into tool input.
     *
     * @return array<string, mixed>
     */
    public function parseDeleteInput(string $arg): array
    {
        [$tokens] = $this->parseTokensAndOptions($arg);
        $id = $tokens[0] ?? '';

        if ($id === '') {
            return ['__error' => 'Usage: /image delete <record-id>'];
        }

        return [
            'action' => 'delete',
            'id' => $id,
        ];
    }

    /**
     * @return array{0: list<string>, 1: array<string, string>}
     */
    public function parseTokensAndOptions(string $arg): array
    {
        $tokens = str_getcsv($arg, ' ', '"', '\\');
        $positionals = [];
        $options = [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '--') && str_contains($token, '=')) {
                [$name, $value] = explode('=', substr($token, 2), 2);
                $name = strtolower(trim($name));
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if ($name !== '' && $value !== '') {
                    $options[$name] = $value;
                    continue;
                }
            }

            $positionals[] = $token;
        }

        return [$positionals, $options];
    }

    /**
     * @param array<string, string> $options
     * @param array<string, mixed> $input
     */
    private function copyOption(array $options, array &$input, string $optionName, ?string $targetName = null): void
    {
        if (!isset($options[$optionName])) {
            return;
        }

        $input[$targetName ?? $optionName] = $options[$optionName];
    }

    private function encodeTags(string $tags): string
    {
        $values = array_values(array_filter(array_map(
            static fn(string $tag): string => trim($tag),
            explode(',', $tags),
        ), static fn(string $tag): bool => $tag !== ''));

        return json_encode($values, JSON_UNESCAPED_SLASHES) ?: '[]';
    }
}
