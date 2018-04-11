<?php

namespace Compolomus\Cache;

use DateInterval;
use DateTime;
use FilesystemIterator;
use LogicException;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileObject;

class FileCache implements CacheInterface
{

    private $cachePath;

    /**
     * FileCache constructor.
     * @param null|string $cachePath
     */
    public function __construct($cachePath = null)
    {
        $this->cachePath = null !== $cachePath ? $cachePath : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cache';
        is_dir($this->cachePath) ?: mkdir($this->cachePath, 0775, true);
    }

    /**
     * Clear cache directory
     *
     * @return bool
     */
    public function clear()
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->cachePath,
            FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        $iterator = iterator_count($iterator) ? $iterator : [];
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        return rmdir($this->cachePath);
    }

    /**
     * @param iterable $keys
     * @param mixed $default
     * @return array
     * @throws LogicException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getMultiple($keys, $default = null)
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key) ?: $default;
        }

        return $values;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     * @throws LogicException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        $keyFile = $this->getFilename($key);
        $file = file_exists($keyFile) && $this->has($key) ? realpath($keyFile) : null;

        return $file ? unserialize((new SplFileObject($file))->fread(filesize($file)),
            ['allowed_classes' => false]) : $default;
    }

    /**
     * @param string $key
     * @return string
     * @throws InvalidArgumentException
     */
    private function getFilename($key)
    {
        $this->validateKey($key);
        $sha1 = sha1($key);

        return $this->cachePath . DIRECTORY_SEPARATOR
            . substr($sha1, 0, 2) . DIRECTORY_SEPARATOR
            . substr($sha1, 2, 2) . DIRECTORY_SEPARATOR
            . $sha1 . '.cache';
    }

    /**
     * @param $key
     * @return bool
     * @throws InvalidArgumentException
     */
    private function validateKey($key)
    {
        if (preg_match('#[{}()/\\\@:]#', $key)) {
            throw new InvalidArgumentException('Can\'t validate the specified key');
        }

        return true;
    }

    /**
     * Isset and life item
     *
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function has($key)
    {
        $filename = $this->getFilename($key);
        if (!file_exists($filename)) {
            return false;
        }
        if ($this->isLife(filemtime($filename))) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * @param $ttl int
     * @return bool
     */
    private function isLife($ttl)
    {
        return $ttl < time();
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function delete($key)
    {
        return unlink($this->getFilename($key));
    }

    /**
     * @param array $values
     * @param int $ttl
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RuntimeException
     * @return bool
     */
    public function setMultiple($values, $ttl = null)
    {
        $status = [];
        foreach ($values as $key => $value) {
            $status[$key] = $this->set($key, $value, $ttl);
        }

        return \in_array(true, $status, true) ? false : true;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null|int|\DateInterval $ttl
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RuntimeException
     * @return bool
     */
    public function set($key, $value, $ttl = null)
    {
        $file = $this->getFilename($key);
        $dir = \dirname($file);
        is_dir($dir) ?: mkdir($dir, 0775, true);

        switch ($ttl) {
            case \is_int($ttl):
                $ttl += time();
                break;
            case ($ttl instanceof DateInterval):
                $ttl = (new DateTime())->add($ttl)->getTimestamp();
                break;
            case (null === $ttl):
            default:
                $ttl = time() + 15;
        }

        $data = new SplFileObject($file, 'wb');
        $data->fwrite(serialize($value));
        return touch($file, $ttl);
    }

    /**
     * @param array $keys
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteMultiple($keys)
    {
        $status = [];
        foreach ($keys as $key) {
            $status[] = $this->delete($key);
        }

        return \in_array(true, $status, true) ? false : true;
    }
}
