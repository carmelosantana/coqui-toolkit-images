<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\Support\PngMetadataWriter;

it('writes and reads PNG text metadata', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-png-meta-' . bin2hex(random_bytes(4));
    mkdir($workspace, 0755, true);
    $path = $workspace . '/test.png';

    file_put_contents(
        $path,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6r0X8AAAAASUVORK5CYII=', true),
    );

    $writer = new PngMetadataWriter();
    $written = $writer->write($path, [
        'CoquiPrompt' => 'test prompt',
        'CoquiProfile' => 'default',
    ]);

    expect($written)->toBeTrue();
    expect($writer->readTextEntries($path))->toMatchArray([
        'CoquiPrompt' => 'test prompt',
        'CoquiProfile' => 'default',
    ]);
});