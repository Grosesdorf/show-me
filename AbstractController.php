<?php

namespace App\Http\Controllers;

use App\Model\Event;
use App\Model\Log;
use App\Model\Wishlist;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class AbstractController
{
    protected $params = [];

    public function __construct()
    {
      AbstractController::timeLog('Abstract_construct_start ' . Session::getId());

      $this->param['lang'] = strtolower(App::getLocale());

      if (Auth::user() && Auth::user()->id) {

        $this->param['wish_events'] = Wishlist::ByUser(Auth::user()->id);

        if (isset($this->param['wish_events'])) {
          foreach ($this->param['wish_events'] as $wish) {
            $event_from_redis = Event::getEventFromRedis($wish->event_id);
            if (!empty($event_from_redis)) {
              $this->param['wishlist'][$wish->event_id] = $event_from_redis;
            }
          }
        }

        if (isset($this->param['wishlist']) && is_array($this->param['wishlist'])) {
          $this->param['count_wishlist'] = count($this->param['wishlist']);
        } else {
          $this->param['count_wishlist'] = 0;
        }
     // dd($this->param);

      }

        $cityId   = !empty($_COOKIE['city_of_client_id']) ? $_COOKIE['city_of_client_id'] : 0;

        $newCityId = Cookie::get('new_city_of_client_id');

        if (isset($cityId))
        {
            $this->params['cityId'] = $cityId;
        }

        if (isset($newCityId))
        {
            $this->params['cityId'] = $newCityId;
        }
      AbstractController::timeLog('Abstract_construct_end ' . Session::getId());

    }

    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }

  public function getEventsIsForceSales()
  {
    $result['events'] = [];

    $events = Event::getByKeyFromRedis();

    foreach ($events as $item)
    {
      if ($item['force_sales'])
      {
        $result['events'][] = $item;
      }
    }

    // Сортируем по дате
    usort($result['events'], function($a,$b){
      return ($a["date_start"] <= $b["date_start"]) ? -1 : 1;
    });

    return $result['events'];
  }


  public function shuffle_assoc($list) {
    if (!is_array($list)) {
      return $list;
    }
    $keys = array_keys($list);
    shuffle($keys);
    $random = [];
    foreach ($keys as $key) {
      $random[$key] = $list[$key];
    }
    return $random;
  }

  public static function translit($s)
  {
    $s = (string)$s; // преобразуем в строковое значение
    $s = strip_tags($s); // убираем HTML-теги
    $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
    $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
    $s = trim($s); // убираем пробелы в начале и конце строки
    $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда надо задать локаль)
    $s = strtr($s, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ie', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
    $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s); // очищаем строку от недопустимых символов
    $s = str_replace(" ", "-", $s); // заменяем пробелы знаком минус
    return $s; // возвращаем результат
  }

  public static function Log($action, $host, $request, $response) {
    $log = new Log();
    $log->user_ip = $_SERVER['REMOTE_ADDR'];
    $log->action = $action;
    $log->host = $host;
    $log->request = json_encode($request);
    $log->response = json_encode($response);
    $log->user_session = json_encode(Session::all());
    $log->user_session_id = json_encode(session()->getId());
    $log->save();
  }

  public static function timeLog($action) {
    if (env('WIDGET_MODE') == 'dev') {
      if (!Storage::disk('public')->exists('time.log')) {
        Storage::disk('public')->put('time.log', '---- Time log file ----');
      }
      Storage::disk('public')->append('time.log', $action . ' --- ' . microtime(true));
    }
  }

}