<?php

namespace App\Common;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class Cache
{
  private AdapterInterface $cache;

  private function __construct ()
  {
    $redisConnection = RedisAdapter::createConnection('redis://redis');
    $this->cache = new RedisAdapter($redisConnection, 'hotel_', 0);
  }

  private static ?Cache $instance = null;
  public static function get(): AdapterInterface
  {
    if (self::$instance === null)
      self::$instance = new Cache();
    return self::$instance->cache;
  }
}