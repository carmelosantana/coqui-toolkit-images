<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\Support\ImageCommandParser;

it('parses generate input with simple prompt', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseGenerateInput('sunset over mountains');

    expect($input)->toHaveKey('prompt', 'sunset over mountains');
    expect($input)->not->toHaveKey('__error');
});

it('parses generate input with options', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseGenerateInput('"a cute fox" --vendor=openai --model=gpt-image-1 --tags=fox,cute --category=animals');

    expect($input['prompt'])->toBe('a cute fox');
    expect($input['vendor'])->toBe('openai');
    expect($input['model'])->toBe('gpt-image-1');
    expect($input['category'])->toBe('animals');
    expect($input['tags_json'])->toBe('["fox","cute"]');
});

it('returns error for empty generate prompt', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseGenerateInput('');

    expect($input)->toHaveKey('__error');
});

it('parses numeric options for seed and steps', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseGenerateInput('a robot --seed=42 --steps=20');

    expect($input['seed'])->toBe(42);
    expect($input['steps'])->toBe(20);
});

it('parses list input with filters', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseListInput('--profile=default --vendor=openai');

    expect($input['action'])->toBe('list');
    expect($input['profile'])->toBe('default');
    expect($input['vendor'])->toBe('openai');
});

it('parses search input with query', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseSearchInput('sunset --category=landscape');

    expect($input['action'])->toBe('search');
    expect($input['query'])->toBe('sunset');
    expect($input['category'])->toBe('landscape');
});

it('returns error for empty search query', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseSearchInput('');

    expect($input)->toHaveKey('__error');
});

it('parses get input with id', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseGetInput('img_abc123');

    expect($input['action'])->toBe('get');
    expect($input['id'])->toBe('img_abc123');
});

it('returns error for empty get id', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseGetInput('');

    expect($input)->toHaveKey('__error');
});

it('parses tag input with id and tags', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseTagInput('img_abc123 --tags=hero,banner --category=marketing');

    expect($input['action'])->toBe('tag');
    expect($input['id'])->toBe('img_abc123');
    expect($input['tags_json'])->toBe('["hero","banner"]');
    expect($input['category'])->toBe('marketing');
});

it('parses tag input with positional tags', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseTagInput('img_abc123 hero,banner');

    expect($input['action'])->toBe('tag');
    expect($input['id'])->toBe('img_abc123');
    expect($input['tags_json'])->toBe('["hero","banner"]');
});

it('returns error for incomplete tag input', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseTagInput('');

    expect($input)->toHaveKey('__error');
});

it('parses delete input with id', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseDeleteInput('img_abc123');

    expect($input['action'])->toBe('delete');
    expect($input['id'])->toBe('img_abc123');
});

it('returns error for empty delete id', function (): void {
    $parser = new ImageCommandParser();
    $input = $parser->parseDeleteInput('');

    expect($input)->toHaveKey('__error');
});

it('handles quoted strings in token parsing', function (): void {
    $parser = new ImageCommandParser();
    [$tokens, $options] = $parser->parseTokensAndOptions('"multi word prompt" --vendor=openai');

    expect($tokens)->toBe(['multi word prompt']);
    expect($options)->toBe(['vendor' => 'openai']);
});

it('normalizes option keys to lowercase', function (): void {
    $parser = new ImageCommandParser();
    [, $options] = $parser->parseTokensAndOptions('--Model=dall-e-3 --VENDOR=openai');

    expect($options)->toHaveKey('model', 'dall-e-3');
    expect($options)->toHaveKey('vendor', 'openai');
});
