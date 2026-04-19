<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitImages\Support;

use CarmeloSantana\CoquiToolkitImages\Exception\ImageToolkitException;

final readonly class ImageRecordStore
{
    public function __construct(
        private string $workspacePath,
    ) {}

    public function indexPath(): string
    {
        return $this->workspacePath . '/images/index.json';
    }

    /**
     * @param array<string, mixed> $record
     */
    public function saveRecord(array $record): void
    {
        $index = $this->readIndex();
        $index['records'][$record['id']] = $record;
        $index['updated_at'] = gmdate(DATE_ATOM);
        $this->writeIndex($index);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $profile = null, ?string $vendor = null): array
    {
        $records = array_values($this->readIndex()['records']);

        $records = array_values(array_filter($records, static function (array $record) use ($profile, $vendor): bool {
            if ($profile !== null && ($record['profile'] ?? null) !== $profile) {
                return false;
            }

            if ($vendor !== null && ($record['vendor'] ?? null) !== $vendor) {
                return false;
            }

            return true;
        }));

        usort($records, static fn(array $left, array $right): int => strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? '')));

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query = '', ?string $category = null): array
    {
        $needle = strtolower(trim($query));

        return array_values(array_filter($this->list(), static function (array $record) use ($needle, $category): bool {
            if ($category !== null && ($record['category'] ?? null) !== $category) {
                return false;
            }

            if ($needle === '') {
                return true;
            }

            $haystacks = [
                (string) ($record['prompt'] ?? ''),
                (string) ($record['path'] ?? ''),
                (string) ($record['vendor'] ?? ''),
                (string) ($record['model'] ?? ''),
                (string) ($record['profile'] ?? ''),
                (string) ($record['owner_name'] ?? ''),
                (string) ($record['category'] ?? ''),
                implode(' ', array_map('strval', $record['tags'] ?? [])),
            ];

            foreach ($haystacks as $haystack) {
                if (str_contains(strtolower($haystack), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param string[] $tags
     * @return array<string, mixed>
     */
    public function updateTags(string $id, array $tags, ?string $category = null): array
    {
        $index = $this->readIndex();
        $record = $index['records'][$id] ?? null;

        if (!is_array($record)) {
            throw ImageToolkitException::recordNotFound($id);
        }

        $record['tags'] = array_values(array_unique(array_filter(array_map(static fn(string $tag): string => FileNameHelper::sanitizeSegment($tag, ''), $tags))));
        if ($category !== null) {
            $record['category'] = $category;
        }
        $record['updated_at'] = gmdate(DATE_ATOM);
        $index['records'][$id] = $record;
        $index['updated_at'] = gmdate(DATE_ATOM);
        $this->writeIndex($index);

        return $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        $record = $this->readIndex()['records'][$id] ?? null;

        return is_array($record) ? $record : null;
    }

    /**
     * Delete a record from the index and optionally remove the image file.
     *
     * @return array{deleted: bool, id: string, path?: string}
     */
    public function delete(string $id, bool $removeFile = true): array
    {
        $index = $this->readIndex();
        $record = $index['records'][$id] ?? null;

        if (!is_array($record)) {
            throw ImageToolkitException::recordNotFound($id);
        }

        $path = is_string($record['path'] ?? null) ? $record['path'] : null;

        unset($index['records'][$id]);
        $index['updated_at'] = gmdate(DATE_ATOM);
        $this->writeIndex($index);

        if ($removeFile && $path !== null && file_exists($path)) {
            @unlink($path);
        }

        $result = ['deleted' => true, 'id' => $id];
        if ($path !== null) {
            $result['path'] = $path;
        }

        return $result;
    }

    /**
     * @return array{version: int, updated_at: string, records: array<string, array<string, mixed>>}
     */
    private function readIndex(): array
    {
        $path = $this->indexPath();
        if (!file_exists($path)) {
            return $this->emptyIndex();
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return $this->emptyIndex();
        }

        $records = $decoded['records'] ?? null;

        return [
            'version' => 1,
            'updated_at' => is_string($decoded['updated_at'] ?? null) ? $decoded['updated_at'] : gmdate(DATE_ATOM),
            'records' => is_array($records) ? $records : [],
        ];
    }

    /**
     * @param array<string, mixed> $index
     */
    private function writeIndex(array $index): void
    {
        $path = $this->indexPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $encoded = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(4));

        try {
            file_put_contents($tmpPath, $encoded, LOCK_EX);
            rename($tmpPath, $path);
        } catch (\Throwable) {
            @unlink($tmpPath);
            file_put_contents($path, $encoded, LOCK_EX);
        }
    }

    /**
     * @return array{version: int, updated_at: string, records: array<string, array<string, mixed>>}
     */
    private function emptyIndex(): array
    {
        return [
            'version' => 1,
            'updated_at' => gmdate(DATE_ATOM),
            'records' => [],
        ];
    }
}