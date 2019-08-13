<?php


namespace App\Model;

use App\Http\Controllers\AbstractController;
use App\Services\RedisService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Event extends Model {
  const REDIS_KEY = 'events';
  const REDIS_PREFIX = 'event_';
  const IN_SALES_KEY = 'events_in_sales';
  const SORTED_BY_DATA= 'events_sort_by_data';

  public static function getSortedByData() {
    return RedisService::getFromRedis(self::SORTED_BY_DATA);
  }

  public static function getInSalesKeys() {
    return RedisService::getFromRedis(self::IN_SALES_KEY);
  }

  public static function getByKeyFromRedis() {
    return RedisService::getFromRedis(self::REDIS_KEY);
  }

  public static function getEventFromRedis($event_id) {
    return RedisService::getFromRedis(self::REDIS_PREFIX . $event_id);
  }

  public static function getEventsByRoute($city, $genre) {
    $result = [];
    $events = self::getByKeyFromRedis();
    foreach ($events as $event) {
      if (empty($genre)) {
        if ($event['date_start'] >= Carbon::now() && $event['status'] == 'in_sales' && AbstractController::translit($event['city']['name']) == $city) {
          $result[$event['id']] = $event;
        }
      } else {
        if ($event['date_start'] >= Carbon::now() && $event['status'] == 'in_sales' && AbstractController::translit($event['city']['name']) == $city && $event['type'] == $genre) {
          $result[$event['id']] = $event;
        }
      }
    }

    if (count($result) > 1) {
      usort($result, function ($a, $b) {
        return ($a['date_start'] <= $b['date_start']) ? -1 : 1;
      });
    }

    return $result;
  }


  public static function getAllEventsCities() {
    $result = [];
    $events = self::getByKeyFromRedis();
    foreach ($events as $event) {
      if (!in_array($event['city']['name'], $result)) {
        $result[] = AbstractController::translit($event['city']['name']);
      }
    }
    return $result;
  }

}