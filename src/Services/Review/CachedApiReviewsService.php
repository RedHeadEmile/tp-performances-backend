<?php

namespace App\Services\Review;

use App\Common\Cache;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\ItemInterface;

class CachedApiReviewsService extends APIReviewsService
{
  public function get(int $hotelId): array
  {
    $item = Cache::get()->getItem('review_' . $hotelId);
    if ($item->get() === null) {
      $item->set(parent::get($hotelId));
      Cache::get()->save($item);
    }
    return $item->get();
  }
}