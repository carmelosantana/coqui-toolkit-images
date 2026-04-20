<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Support;

final class PngMetadataWriter
{
    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1A\n";

    public function supports(string $path): bool
    {
        $bytes = @file_get_contents($path, false, null, 0, 8);

        return is_string($bytes) && str_starts_with($bytes, self::PNG_SIGNATURE);
    }

    /**
     * @param array<string, scalar|null> $metadata
     */
    public function write(string $path, array $metadata): bool
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false || !str_starts_with($bytes, self::PNG_SIGNATURE)) {
            return false;
        }

        $chunks = '';
        foreach ($metadata as $key => $value) {
            if ($value === null) {
                continue;
            }

            $keyword = substr($this->sanitizeKeyword($key), 0, 79);
            $text = (string) $value;

            if ($keyword === '' || $text === '') {
                continue;
            }

            $chunks .= $this->buildTextChunk($keyword, $text);
        }

        if ($chunks === '') {
            return false;
        }

        $iendOffset = strrpos($bytes, 'IEND');
        if ($iendOffset === false || $iendOffset < 4) {
            return false;
        }

        $chunkStart = $iendOffset - 4;
        $updated = substr($bytes, 0, $chunkStart) . $chunks . substr($bytes, $chunkStart);

        return file_put_contents($path, $updated) !== false;
    }

    /**
     * @return array<string, string>
     */
    public function readTextEntries(string $path): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false || !str_starts_with($bytes, self::PNG_SIGNATURE)) {
            return [];
        }

        $entries = [];
        $offset = 8;
        $length = strlen($bytes);

        while ($offset + 8 <= $length) {
            $chunkLength = unpack('N', substr($bytes, $offset, 4))[1] ?? 0;
            $type = substr($bytes, $offset + 4, 4);
            $data = substr($bytes, $offset + 8, $chunkLength);

            if ($type === 'tEXt' && str_contains($data, "\0")) {
                [$keyword, $text] = explode("\0", $data, 2);
                $entries[$keyword] = $text;
            }

            $offset += 12 + $chunkLength;
        }

        return $entries;
    }

    private function buildTextChunk(string $keyword, string $text): string
    {
        $data = $keyword . "\0" . $text;
        $type = 'tEXt';
        $crc = hash('crc32b', $type . $data, true);

        return pack('N', strlen($data)) . $type . $data . $crc;
    }

    private function sanitizeKeyword(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9 _.-]+/', '', $key) ?? '';
    }
}