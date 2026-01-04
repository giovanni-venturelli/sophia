<?php

namespace Sophia\Cache;

class FileCache
{
    private string $cacheDir;

    public function __construct(string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/sophia_cache';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return null;
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        $data = @unserialize($contents);
        if ($data === false) {
            return null;
        }

        // Controlla scadenza
        if (!isset($data['expires']) || $data['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        $file = $this->getCacheFile($key);

        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        $serialized = serialize($data);
        $result = @file_put_contents($file, $serialized, LOCK_EX);

        return $result !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function clear(): int
    {
        $deleted = 0;
        $files = @glob($this->cacheDir . '/*.cache');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Pulisce i file scaduti
     */
    public function cleanExpired(): int
    {
        $deleted = 0;
        $files = @glob($this->cacheDir . '/*.cache');

        if ($files === false) {
            return 0;
        }

        $now = time();
        foreach ($files as $file) {
            if (!is_file($file)) continue;

            $contents = @file_get_contents($file);
            if ($contents === false) continue;

            $data = @unserialize($contents);
            if ($data === false) continue;

            if (isset($data['expires']) && $data['expires'] < $now) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function getCacheFile(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    /**
     * Ritorna statistiche sulla cache
     */
    public function getStats(): array
    {
        $files = @glob($this->cacheDir . '/*.cache');

        if ($files === false) {
            return [
                'files' => 0,
                'size' => 0,
                'expired' => 0
            ];
        }

        $totalSize = 0;
        $expired = 0;
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);

                $contents = @file_get_contents($file);
                if ($contents !== false) {
                    $data = @unserialize($contents);
                    if (isset($data['expires']) && $data['expires'] < $now) {
                        $expired++;
                    }
                }
            }
        }

        return [
            'files' => count($files),
            'size' => $totalSize,
            'size_human' => $this->formatBytes($totalSize),
            'expired' => $expired
        ];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}