<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\Client\OpenAIImageClient;
use CarmeloSantana\CoquiToolkitImages\Contract\ImageToolkitContext;
use CarmeloSantana\CoquiToolkitImages\ImageToolkitRuntime;
use CarmeloSantana\CoquiToolkitImages\Tool\ImageGenerateTool;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

it('returns structured json metadata when image_generate requests json output', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-generate-tool-' . bin2hex(random_bytes(4));
    mkdir($workspace, 0755, true);

    try {
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(1, 1);
            expect($image)->not->toBeFalse();

            $blue = imagecolorallocate($image, 20, 120, 240);
            expect($blue)->not->toBeFalse();
            imagefill($image, 0, 0, $blue);

            ob_start();
            imagepng($image);
            $pngBytes = ob_get_clean();
            imagedestroy($image);
        } else {
            $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4//8/AwAI/AL+KDv8AAAAAElFTkSuQmCC', true);
        }

        expect($pngBytes)->toBeString();

        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'data' => [[
                'b64_json' => base64_encode($pngBytes),
                'revised_prompt' => 'tiny blue square',
            ]],
        ], JSON_UNESCAPED_SLASHES) ?: '{}', ['http_code' => 200]));

        $context = ImageToolkitContext::fromArray([
            'workspacePath' => $workspace,
            'activeProfile' => 'caelum',
            'imageModel' => 'openai/gpt-image-1',
        ]);

        $runtime = new ImageToolkitRuntime(
            $context,
            openAIClient: new OpenAIImageClient('test-key', httpClient: $httpClient),
        );

        $result = (new ImageGenerateTool($runtime))->build()->execute([
            'prompt' => 'tiny blue square',
            'output_format' => 'json',
        ]);

        expect($result->status->value)->toBe('success');
        expect($result->mimeType)->toBe('application/json');
        expect($result->displayHint)->toBe('structured-json');

        $payload = json_decode($result->content, true);
        expect($payload)->toBeArray();
        expect($payload['message'])->toBe('Image generated successfully.');
        expect($payload['saved_path'])->toBeString();
        expect(file_exists($payload['saved_path']))->toBeTrue();
        expect($payload['record']['vendor'])->toBe('openai');
        expect($payload['record']['model'])->toBe('gpt-image-1');
    } finally {
        if (is_dir($workspace)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($workspace);
        }
    }
});