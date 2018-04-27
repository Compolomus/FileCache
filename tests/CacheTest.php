<?php

namespace Compolomus\Compomage\Tests;

use Compolomus\Cache\FileCache;
use PHPUnit\Framework\TestCase;
use DateInterval;
use InvalidArgumentException;

class CacheTest extends TestCase
{
    private $dir = __DIR__ . DIRECTORY_SEPARATOR . 'test';

    private $cache;

    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->cache = new FileCache($this->dir);
    }

    private function getCache(): FileCache
    {
        return $this->cache;
    }

    public function test__construct(): void
    {
        try {
            $cache = new FileCache();
            $this->assertInternalType('object', $cache);
            $this->assertInstanceOf(FileCache::class, $cache);
        } catch (\Exception $e) {
            $this->assertContains('Must be initialized ', $e->getMessage());
        }
        new FileCache($this->dir);
        $this->assertDirectoryExists($this->dir);
        $this->assertDirectoryIsReadable($this->dir);
        $this->assertDirectoryIsWritable($this->dir);
    }

    public function testSet(): void
    {
        $sha1_0 = sha1('Dummy');
        $sha1_1 = sha1('Dummy1');
        $sha1_2 = sha1('Dummy2');
        $sha1_3 = sha1('Dummy3');

        $this->getCache()->set('Dummy', 'Dummy', 120);
        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . substr($sha1_0, 0,
                2) . DIRECTORY_SEPARATOR . substr($sha1_0, 2, 2) . DIRECTORY_SEPARATOR . $sha1_0 . '.cache');
        $this->getCache()->set('Dummy1', 'Dummy1', new DateInterval('P' . abs((7 - date('N'))) . 'D'));
        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . substr($sha1_1, 0,
                2) . DIRECTORY_SEPARATOR . substr($sha1_1, 2, 2) . DIRECTORY_SEPARATOR . $sha1_1 . '.cache');
        $this->getCache()->set('Dummy2', 'Dummy2', -1);
        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . substr($sha1_2, 0,
                2) . DIRECTORY_SEPARATOR . substr($sha1_2, 2, 2) . DIRECTORY_SEPARATOR . $sha1_2 . '.cache');
        $this->getCache()->set('Dummy3', 'Dummy3', null);
        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . substr($sha1_3, 0,
                2) . DIRECTORY_SEPARATOR . substr($sha1_3, 2, 2) . DIRECTORY_SEPARATOR . $sha1_3 . '.cache');
    }

    public function testGet(): void
    {
        $this->assertEquals('Dummy', $this->getCache()->get('Dummy'));
    }

    public function testHas(): void
    {
        $this->assertTrue($this->getCache()->has('Dummy'));
        $this->assertNotTrue($this->getCache()->has('Test'));
        $this->getCache()->set('Test', 1);
        sleep(2);
        $this->assertNotTrue($this->getCache()->has('Test'));
    }

    public function testGetMultiple(): void
    {
        $this->assertArraySubset(
            $this->getCache()->getMultiple(['Dummy', 'Dummy1', 'NotSet'], 'Default'),
            ['Dummy' => 'Dummy', 'Dummy1' => 'Dummy1', 'NotSet' => 'Default']
        );
    }

    public function testSetMultiple(): void
    {
        $this->assertTrue($this->getCache()->setMultiple(['Dummy4' => 100500]));
        $this->expectException(InvalidArgumentException::class);
        $this->assertNotTrue($this->getCache()->setMultiple(['@@@' => new FileCache()]));
    }

    public function testDeleteMultiple(): void
    {
        $this->assertTrue($this->getCache()->deleteMultiple(['Dummy3']));
        $this->assertNotTrue($this->getCache()->deleteMultiple(['NotSet']));
    }

    public function testClear(): void
    {
        $this->assertTrue($this->getCache()->clear());
    }
}
