<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\AbstractController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Desktop\DesktopIndexController;
use App\Model\Choice;
use App\Model\Event;
use App\Services\DateTranslate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Elasticsearch\Client;
use Illuminate\Support\Facades\App;

class SearchController extends Controller
{
    const TOP_EVENTS_LIMIT = 6;

    private $client;
    private $array_id = [];
    private $array_result = [];

    public function __construct(Request $request, Client $client)
    {
        $this->client = $client;
        parent::__construct($request);
        $this->param['server'] = env('TMS_URL') . '/';
    }


    public function top()
    {
        $this->param['lang'] = strtolower(App::getLocale());
        $this->param['cityId'] = $cityId = $_COOKIE['city_of_client_id'];
        $this->param['server'] = env('TMS_URL') . '/';

        if ((array_key_exists('city', $this->param) && !empty($this->param['city']) || (array_key_exists('genre', $this->param) && !empty($this->param['genre'])))) {
            $this->param['top'] = Event::getEventsByRoute($this->param['city'], $this->param['genre']);
        } else {
            $this->param['top'] = DesktopIndexController::getTop($cityId);
        }

        if ($this->param['version'] == 'mobile') {
            return view('mobile.pages.search', $this->param);
        } else {
            return view('desktop.pages.search', $this->param);
        }
    }

    public function index($string, $simple)
    {
        $result = [];

        $result_search = $this->response_of_search($string, $simple);

        foreach ($result_search as $key => $value) {
            if (in_array('concert', $value))
                $result['concert'] = $this->array_result[$key];
            if (in_array('place', $value))
                $result['place'] = $this->array_result[$key];
            if (in_array('artist', $value))
                $result['artist'] = $this->array_result[$key];
            if (in_array('news', $value))
                $result['news'] = $this->array_result[$key];
        }

        if ($this->param['version'] == 'mobile') {
            return empty($result_search) ?
                view('mobile.pages.search_empty', ['string' => $string])
                :
                view('mobile.pages.search', ['string' => $string, 'result_search' => $result]);
        }

        $this->param['cityId'] = $cityId = $_COOKIE['city_of_client_id'];
        $choiceIds = Choice::getChoicesIdsByRegion($cityId);
        //dd($choiceIds);
        $this->param['choices']['mp_city_top_bl1'] = Choice::fillChoiceByPlace('mp_city_top_bl1', $choiceIds);
        $this->param['choices']['mp_city_top_bl2'] = Choice::fillChoiceByPlace('mp_city_top_bl2', $choiceIds);
        $this->param['choices']['mp_city_top_bl3'] = Choice::fillChoiceByPlace('mp_city_top_bl3', $choiceIds);
        $this->param['events'] = DesktopIndexController::getTop($cityId, self::TOP_EVENTS_LIMIT);


        return empty($result_search) && empty($result) ?
            view('desktop.pages.search_empty', ['string' => $string])->with($this->param)
            :
            view('desktop.pages.search', ['string' => $string, 'result_search' => $result]);
    }

    public function searchFilters(Request $request)
    {
        $arrResult = [];

        $string = $request->get('search_string');

        $search = $request->all();

        if (key_exists('text', $search)) {
            $arrResult = [
                "header" => trans('search.filter'), //"ФІЛЬТР",
                "clear" => trans('search.clear_all_filters'), //"Очистити усі фільтри",
                "placeholder" => trans('search.by_atist_or_title'), //"Пошук за артистом чи назвою",
                "go" => trans('search.search'), //"Пошук"
                "location_empty" => trans('location.location_empty'),//"Ваше мiсто",
            ];

            return response()->json($arrResult)->header('Access-Control-Allow-Origin', '*');
        }

        if (key_exists('filters', $search)) {

            $arrResult = [
                "header" => trans('search.filter'), //"ФІЛЬТР",
                "result" => "100",
                "text_clear" => trans('search.clear_all_filters'), //"Очистити усі фільтри",
                "text_submit" => trans('search.apply'), //"Застосувати",
                "submit_error_header" => trans('search.error'), //"Помилка!",
                "submit_error_text" => trans('search.connection_error'), //"Не вдалося достукатися до сервера",
                "events" => [
                    "opened" => true,
                    "header" => trans('search.events'), //"Події",
                    "items" => [
                        [
                            "id" => "type[]=concert",
                            "label" => trans('search.concerts'), //"Концерти",
                            "selected" => false
                        ], [
                            "id" => "type[]=sport",
                            "label" => trans('search.sport'), //"Спорт",
                            "selected" => false
                        ], [
                            "id" => "type[]=children",
                            "label" => trans('search.for_children'), //"Дітям",
                            "selected" => false
                        ], [
                            "id" => "type[]=theater",
                            "label" => trans('search.theater'), //"Театр",
                            "selected" => false
                        ], [
                            "id" => "type[]=workshop",
                            "label" => "Workshop",
                            "selected" => false
                        ], [
                            "id" => "type[]=circus",
                            "label" => trans('search.circus'), //"Цирк",
                            "selected" => false
                        ],
                    ]
                ],
                "time" => [
                    "opened" => true,
                    "header" => trans('search.date'), //"Дата",
                    "items" => [
                        [
                            "id" => "date[]=tomorrow",
                            "label" => trans('search.tomorrow'), //"На завтра",
                            "selected" => false
                        ], [
                            "id" => "date[]=weekend",
                            "label" => trans('search.weekends'), //"На вихідні",
                            "selected" => false
                        ], [
                            "id" => "date[]=week",
                            "label" => trans('search.this_week'), //"На цей тиждень",
                            "selected" => false
                        ], [
                            "id" => "date[]=month",
                            "label" => trans('search.this_month'), //"На цей місяць",
                            "selected" => false
                        ]
                    ],
                    "range" => [
                        "id" => "range",
                        "header" => trans('search.calendar'), //"Календар",
//                        "label" => Carbon::now()->format('d.m.Y'),
                        "placeholder" => trans('search.use_calendar'), //"Cкористатися календарем",
                        "submit" => trans('search.propose'), //"Запропонувати",
                        "confirm" => trans('search.save'), //"Зберегти"
                    ]
                ],
                "genre" => [
                    "opened" => false,
                    "header" => trans('search.genre'), //"Жанр",
                ],
                "place" => [
                    "opened" => false,
                    "header" => '', //trans('search.venue'), //"Місце проведення",
                    "items" => [
                    ]
                ]
            ];

            $arrResult['genre']['items'] = [
                [
                    'id' => 'genre[]=classical',
                    'label' => 'Classical',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=authors',
                    'label' => 'Authors',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=electronic',
                    'label' => 'Electronic',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=jazz',
                    'label' => 'Jazz',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=pop',
                    'label' => 'Pop',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=blues',
                    'label' => 'Blues',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=rock',
                    'label' => 'Rock',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=other',
                    'label' => 'Other',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=indie',
                    'label' => 'Indie',
                    'selected' => false,
                ],
                [
                    'id' => 'genre[]=hiphop',
                    'label' => 'Hip-hop',
                    'selected' => false,
                ],
            ];

            return response()->json($arrResult)->header('Access-Control-Allow-Origin', '*');
        }

        $result_search = $this->response_of_search($request->all(), false);

        $result = [];

        foreach ($result_search as $key => $value) {
            if (in_array('concert', $value))
                $result['concert'] = $this->array_result[$key];
            if (in_array('place', $value))
                $result['place'] = $this->array_result[$key];
            if (in_array('artist', $value))
                $result['artist'] = $this->array_result[$key];
            if (in_array('news', $value))
                $result['news'] = $this->array_result[$key];
        }

        if ($this->param['version'] == 'mobile') {
            return empty($result_search) ?
                view('mobile.pages.search_empty', ['string' => $string])
                :
                view('mobile.pages.search', ['string' => $string, 'result_search' => $result]);
        }

        $this->param['cityId'] = $cityId = $_COOKIE['city_of_client_id'];
        $choiceIds = Choice::getChoicesIdsByRegion($cityId);
        $this->param['choices']['mp_city_top_bl1'] = Choice::fillChoiceByPlace('mp_city_top_bl1', $choiceIds);
        $this->param['choices']['mp_city_top_bl2'] = Choice::fillChoiceByPlace('mp_city_top_bl2', $choiceIds);
        $this->param['choices']['mp_city_top_bl3'] = Choice::fillChoiceByPlace('mp_city_top_bl3', $choiceIds);

        $this->param['events'] = DesktopIndexController::getTop($cityId, self::TOP_EVENTS_LIMIT);


        return empty($result) ?
            view('desktop.pages.search_empty', ['string' => $string])->with($this->param)
            :
            view('desktop.pages.search', ['string' => $string, 'result_search' => $result]);
    }

    public function response_of_search($string, $simple)
    {
        $minimumShouldMatch = 0;

        $params_events = [
            'index' => 'events',
            'type' => 'event',
        ];

        $json_search = [
            'from' => 0,
            'size' => 60,
            'query' => []
        ];

        if ($string && $simple) {
            $json_search['size'] = 12;

            $json_search['query']['multi_match'] = ['query' => $string,
                'fields' => ['event_name', 'city_name']
            ];

            /*$json_search['sort'] = [
              'event_date_sec' => ['order'=> 'asc', 'mode' => 'min'],
              ];*/
            /*   $json_search['sort'] = [
                  '_score' => ['order'=> 'desc'],
                 'event_date_sec' => ['order'=> 'asc'],
               ];*/
            //  $json_search['query']['bool']['must'][]['bool']['should'] = ['match' => ['event_name' => $string]];

        } else {
//dd($string);

            if (isset($string['city']) && !empty($string['city'])) {
                $json_search['query']['bool']['filter']['term'] = ['city_id' => $string['city'][0]];
            }

            if (isset($string['type']) && !empty($string['type'])) {
                foreach ($string['type'] as $item) {
                    $json_search['query']['bool']['should'][] =
                        ['match' => ['event_type' => $item]];
                }
            }

            if (isset($string['genre']) && !empty($string['genre'])) {
                foreach ($string['genre'] as $item) {
                    $json_search['query']['bool']['should'][] = ['match' => ['event_genre' => $item]];
                }
            }

            if ((isset($string['genre']) && isset($string['type'])) && (!empty($string['genre']) && !empty($string['type']))) {
                $json_search = [
                    'from' => 0,
                    'size' => 60,
                    'query' => [],
                ];

                $types = [];
                foreach ($string['type'] as $item) {
                    $types[] = [
                        'match' => [
                            'event_type' => [
                                'query' => $item,
                            ]
                        ]
                    ];
                }

                $json_search['query']['bool']['must'][]['bool']['should'] = $types;

                $genres = [];
                foreach ($string['genre'] as $item) {
                    $genres[] = [
                        'match' => [
                            'event_genre' => [
                                'query' => $item,
                            ]
                        ]
                    ];
                }

                $json_search['query']['bool']['must'][]['bool']['should'] = $genres;
            }

            if (isset($string['range']) && !empty($string['range'])) {
                $range = $string['range'];
                $range = explode(' - ', $range);

                $json_search['query']['bool']['must'][] = [
                    'range' => [
                        'event_date_sec' => [
                            'gt' => Carbon::parse($range[0])->startOfDay()->timestamp,
                            'lt' =>
                                count($range) === 1 ?
                                    Carbon::parse($range[0])->endOfDay()->timestamp :
                                    Carbon::parse($range[1])->endOfDay()->timestamp,
                        ]
                    ]
                ];
            }

            if (isset($string['date']) && !empty($string['date'])) {
                if (in_array('tomorrow', $string['date'])) {
                    //tomorrow
                    $json_search['query']['bool']['must'][] = ['range' => [
                        'event_date_sec' => [
                            'gt' => Carbon::now()->addDay(1)->startOfDay()->timestamp,
                            'lt' => Carbon::now()->addDay(1)->endOfDay()->timestamp,
                        ]
                    ]];
                }

                //weekend
                if (in_array('weekend', $string['date'])) {
                    $json_search['query']['bool']['must'][] = ['range' => [
                        'event_date_sec' => [
                            'gt' => Carbon::now()->nextWeekendDay()->startOfDay()->timestamp,
                            'lt' => Carbon::now()->nextWeekendDay()->addDay(1)->endOfDay()->timestamp,
                        ]
                    ]];
                }

                //this week
                if (in_array('week', $string['date'])) {
                    $json_search['query']['bool']['must'][] = ['range' => [
                        'event_date_sec' => [
                            'gt' => Carbon::now()->startOfDay()->timestamp,
                            'lt' => Carbon::now()->endOfWeek()->endOfDay()->timestamp,
                        ]
                    ]];
                }

                //this month
                if (in_array('month', $string['date'])) {
                    $json_search['query']['bool']['must'][] = ['range' => [
                        'event_date_sec' => [
                            'gt' => Carbon::now()->startOfDay()->timestamp,
                            'lt' => Carbon::now()->endOfMonth()->endOfDay()->timestamp,
                        ]
                    ]];
                }

            }

            if (isset($string['search_string']) && !empty($string['search_string'])) {
                $json_search['query']['bool']['must'][] = ['match' => ['event_name' => $string['search_string']]];
            }
        }

        $client = new \GuzzleHttp\Client([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type ' => 'application/json',
            ]]);
//dd($json_search);
        $response = $client->request(
        //'POST',
            'GET',
            // 'http://localhost:9200/events/event/_search',
            'http://localhost:9200/_all/event/_search',
            ['body' => json_encode($json_search)]);


        $result['events'] = json_decode($response->getBody()->getContents(), true);
//dd($result, $json_search);
        AbstractController::Log('search', 'http://localhost:9200/events/event/_search', $json_search, $result['events']);


        if (!empty($result['events']['hits']['hits'])) {
            $items = [];
            $ids = [];

            foreach ($result['events']['hits']['hits'] as $item) {
                if (!in_array($item['_id'], $ids)) {
                    $ids[] = $item['_id'];
                    $event = Event::getEventFromRedis($item['_id']);
                    if (!empty($event)) {
                        $this->array_id['event'][] = $event;
                    }
                }
            }

            if (!empty($this->array_id['event'])) {
                foreach ($this->array_id['event'] as $key => $item) {
                    $items[$key] = [
                        'url' => env('APP_URL') . '/' . App::getLocale() . '/' . $item['url_alias'],
                        'date' => DateTranslate::translate($item['date_start']),
                        'time' => substr($item['date_entry'], 11, 5),
                        'header' => $item['name'],
                        'place' => $item['city']['name'],
                        'event_min_price' => !empty($item['min_price']) ? $item['min_price'] : '',
                        'event_max_price' => !empty($item['max_price']) ? $item['max_price'] : '',
                    ];

                    $img_files = $item['files'];

                    if ($this->param['version'] == 'mobile') {
                        $items[$key]['image'] = isset($img_files['mobile'][0]) ?
                            $img_files['mobile'][0] :
                            env('APP_URL') . '/static/500x500_zaglushka.png';
                    }
                    if ($this->param['version'] == 'desktop') {
                        $items[$key]['image'] = isset($img_files['desktop'][0]) ?
                            $img_files['desktop'][0] :
                            env('APP_URL') . '/static/800x500_zaglushka.png';
                    }
                }


                $this->array_result[] = [
                    'type' => 'concert',
                    'header' => trans('search.events'), //'Події',
                    'items' => $items];
            }
        }
        
        return $this->array_result;
    }
}
