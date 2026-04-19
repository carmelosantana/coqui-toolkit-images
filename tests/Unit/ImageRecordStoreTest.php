<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitImages\Exception\ImageToolkitException;
use CarmeloSantana\CoquiToolkitImages\Support\ImageRecordStore;

it('stores, lists, searches, and retags image records', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-image-store-' . bin2hex(random_bytes(4));
    $store = new ImageRecordStore($workspace);

    $store->saveRecord([
        'id' => 'img_test',
        'path' => $workspace . '/images/default/test.png',
        'vendor' => 'openai',
        'model' => 'gpt-image-1.5',
        'prompt' => 'sunset over mountains',
        'profile' => 'default',
        'owner_name' => 'default',
        'tags' => ['sunset', 'mountains'],
        'category' => 'landscape',
        'created_at' => gmdate(DATE_ATOM),
        'updated_at' => gmdate(DATE_ATOM),
    ]);

    expect($store->list())->toHaveCount(1);
    expect($store->search('sunset'))->toHaveCount(1);

    $updated = $store->updateTags('img_test', ['hero', 'banner'], 'marketing');

    expect($updated['tags'])->toBe(['hero', 'banner']);
    expect($updated['category'])->toBe('marketing');
});

it('deletes a record from the index', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-image-store-del-' . bin2hex(random_bytes(4));
    $store = new ImageRecordStore($workspace);

    $store->saveRecord([
        'id' => 'img_del',
        'path' => $workspace . '/images/default/del.png',
        'vendor' => 'openai',
        'model' => 'gpt-image-1',
        'prompt' => 'delete me',
        'profile' => 'default',
        'owner_name' => 'default',
        'tags' => [],
        'category' => 'test',
        'created_at' => gmdate(DATE_ATOM),
        'updated_at' => gmdate(DATE_ATOM),
    ]);

    expect($store->list())->toHaveCount(1);

    $result = $store->delete('img_del');

    expect($result['deleted'])->toBeTrue();
    expect($result['id'])->toBe('img_del');
    expect($store->list())->toHaveCount(0);
    expect($store->get('img_del'))->toBeNull();
});

it('throws when deleting a non-existent record', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-image-store-delmiss-' . bin2hex(random_bytes(4));
    $store = new ImageRecordStore($workspace);

    $store->delete('img_nonexistent');
})->throws(ImageToolkitException::class);

it('filters list by profile and vendor', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-image-store-filter-' . bin2hex(random_bytes(4));
    $store = new ImageRecordStore($workspace);

    $store->saveRecord([
        'id' => 'img_a',
        'path' => $workspace . '/images/a.png',
        'vendor' => 'openai',
        'model' => 'gpt-image-1',
        'prompt' => 'test a',
        'profile' => 'alice',
        'owner_name' => 'alice',
        'tags' => [],
        'category' => 'test',
        'created_at' => gmdate(DATE_ATOM),
        'updated_at' => gmdate(DATE_ATOM),
    ]);

    $store->saveRecord([
        'id' => 'img_b',
        'path' => $workspace . '/images/b.png',
        'vendor' => 'ollama',
        'model' => 'gemma3',
        'prompt' => 'test b',
        'profile' => 'bob',
        'owner_name' => 'bob',
        'tags' => [],
        'category' => 'test',
        'created_at' => gmdate(DATE_ATOM),
        'updated_at' => gmdate(DATE_ATOM),
    ]);

    expect($store->list('alice'))->toHaveCount(1);
    expect($store->list(null, 'ollama'))->toHaveCount(1);
    expect($store->list('alice', 'ollama'))->toHaveCount(0);
});

it('writes index atomically via temp file', function (): void {
    $workspace = sys_get_temp_dir() . '/coqui-image-store-atomic-' . bin2hex(random_bytes(4));
    $store = new ImageRecordStore($workspace);

    $store->saveRecord([
        'id' => 'img_atomic',
        'path' => $workspace . '/images/atomic.png',
        'vendor' => 'openai',
        'model' => 'gpt-image-1',
        'prompt' => 'atomic write test',
        'profile' => 'default',
        'owner_name' => 'default',
        'tags' => [],
        'category' => 'test',
        'created_at' => gmdate(DATE_ATOM),
        'updated_at' => gmdate(DATE_ATOM),
    ]);

    $indexPath = $store->indexPath();
    expect(file_exists($indexPath))->toBeTrue();

    $decoded = json_decode((string) file_get_contents($indexPath), true);
    expect($decoded['records'])->toHaveKey('img_atomic');

    // No .tmp files should remain
    $tmpFiles = glob(dirname($indexPath) . '/*.tmp.*');
    expect($tmpFiles)->toBe([]);
});