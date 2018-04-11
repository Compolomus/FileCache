<?php declare(strict_types=1);

namespace Test;

use Compolomus\Cache\FileCache as Cache;

require_once __DIR__ . '/../vendor/autoload.php';

$cache = new Cache('./storage');

#$cache->set('test', 'value', 10);

echo $cache->get('test', 'empty');
$cache->clear();
