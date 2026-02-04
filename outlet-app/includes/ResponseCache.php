<?php

class ResponseCache {
    private $cacheDir;
    private $defaultTTL;

    public function __construct($cacheDir = null, $defaultTTL = 30) {
        $this->cacheDir = $cacheDir ?? __DIR__ . '/../cache/api';
        $this->defaultTTL = $defaultTTL;


        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);

            file_put_contents($this->cacheDir . '/.htaccess', "Deny from all\n");
        }
    }


    private function getCacheKey($key) {
        return md5($key);
    }


    private function getCacheFilePath($key) {
        $hash = $this->getCacheKey($key);
        return $this->cacheDir . '/' . $hash . '.cache';
    }


    public function get($key) {
        $filePath = $this->getCacheFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $data = @file_get_contents($filePath);
        if ($data === false) {
            return null;
        }

        $cached = @unserialize($data);
        if ($cached === false) {
            return null;
        }


        if (isset($cached['expires_at']) && time() > $cached['expires_at']) {
            @unlink($filePath);
            return null;
        }

        return $cached['data'] ?? null;
    }


    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTTL;
        $filePath = $this->getCacheFilePath($key);

        $cached = [
            'data' => $data,
            'created_at' => time(),
            'expires_at' => time() + $ttl
        ];

        $serialized = serialize($cached);
        return @file_put_contents($filePath, $serialized, LOCK_EX) !== false;
    }


    public function delete($key) {
        $filePath = $this->getCacheFilePath($key);
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return true;
    }


    public function cleanup($maxAge = 3600) {
        $files = glob($this->cacheDir . '/*.cache');
        $count = 0;

        foreach ($files as $file) {
            if (time() - filemtime($file) > $maxAge) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }


    public function clear() {
        $files = glob($this->cacheDir . '/*.cache');
        $count = 0;

        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        return $count;
    }


    public function remember($key, $callback, $ttl = null) {
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $callback();
        $this->set($key, $data, $ttl);

        return $data;
    }
}
