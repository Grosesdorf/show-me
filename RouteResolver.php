<?php


namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;

class RouteResolver
{
    public static function getController($lang, $slug)
    {
        $uri = '/'.$lang.'/'.$slug;
        $hash = md5($uri);
        $data = json_decode(Redis::get('all_routing_data'), true);
        $version = session()->get('mobile') ? 'mobile' : "desktop";

        if ($data) {
            if (array_key_exists($hash, $data))
            {
                $class = 'App\Http\Controllers\\' . ucfirst($data[$hash]['controller']) . 'Controller';
                $controller = new $class;
                $result = $data[$hash];
                $result['version'] = $version;
                $controller->setParams($result);

                return $controller;
            }
            else
            {
                return false;
            }
        }
    }
}