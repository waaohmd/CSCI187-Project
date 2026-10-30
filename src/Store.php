<?php
declare(strict_types=1);

namespace CampusMarketplace\Persistence;

use RuntimeException;

function readStore(string $dataFile): array
{
    $handle = fopen($dataFile, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open the data store.');
    }

    try {
        if (!flock($handle, LOCK_SH)) {
            throw new RuntimeException('Unable to lock the data store.');
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    $decoded = json_decode($contents ?: '{}', true);
    if (!is_array($decoded)) {
        throw new RuntimeException('The data store is invalid.');
    }

    return array_merge(['listings' => [], 'help_posts' => []], $decoded);
}

function writeStore(string $dataFile, array $store): void
{
    $directory = dirname($dataFile);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the data directory.');
    }

    $handle = fopen($dataFile, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to write the data store.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the data store.');
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}
