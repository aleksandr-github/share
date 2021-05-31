<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

/**
 * TODO add cache invalidation
 *
 * PoC - add cache invalidation
 */
class LocalContentCacheService
{
    protected $localContent;

    protected $fileSystem;

    protected $cacheDir = APP_ROOT . DIRECTORY_SEPARATOR . "cache";

    public function __construct()
    {
        $this->fileSystem = new Filesystem();

        $this->init();
    }

    protected function init()
    {
        if (!is_dir($this->cacheDir)) {
            $this->fileSystem->mkdir($this->cacheDir);
        }
    }

    /**
     * @param string $url
     * @param string $content
     * @return string
     * @throws \Exception
     */
    public function cache(string $url, string $content)
    {
        $this->add($url, $content);

        return $this->fetch($url);
    }

    public function add(string $url, string $content)
    {
        if (!$this->cacheExists($url)) {
            $this->createCache($url, $content);
        }
    }

    public function fetch(string $url): string
    {
        if (!$this->cacheExists($url)) {
            throw new \Exception("Cache does not exist");
        }

        $md5key = md5($url);
        return file_get_contents($this->cacheDir . DIRECTORY_SEPARATOR . $md5key);
    }

    protected function createCache(string $url, string $content)
    {
        $md5key = md5($url);
        $this->fileSystem->dumpFile($this->cacheDir . DIRECTORY_SEPARATOR . $md5key, $content);
    }

    public function cacheExists(string $url): bool
    {
        $md5key = md5($url);
        if ($this->fileSystem->exists($this->cacheDir . DIRECTORY_SEPARATOR . $md5key)) {
            return true;
        }

        return false;
    }

    public function isCacheValid(string $url): bool
    {
        // todo

        return true;
    }
}