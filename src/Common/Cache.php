<?php

namespace App\Common;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class Cache
{
  private AdapterInterface $cache;

  private function __construct ()
  {
    if (isset($_GET['skip_cache']))
      $this->cache = new NullAdapter();
    else {
      $redisConnection = RedisAdapter::createConnection('redis://redis');
      $this->cache = new RedisAdapter($redisConnection, 'hotel_', 0);

      if (isset($_GET['clear_cache']))
        $this->cache->clear();
    }
  }

  private static ?Cache $instance = null;
  public static function get(): AdapterInterface
  {
    if (self::$instance === null)
      self::$instance = new Cache();
    return self::$instance->cache;
  }
}