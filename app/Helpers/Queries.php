<?php

use Illuminate\Pagination\Paginator;

function make_pagination($query, $pageNumber = null, $pageSize = 20)
{
    if (! $pageNumber) {
        $pageNumber = 1;
    }
    if ($pageSize == null) {
        return $query->get();
    } else {
      Paginator::currentPageResolver(function () use ($pageNumber) {
          return $pageNumber;
      });
      return $query->paginate($pageSize);
    }
}

function console_log($obj)
{
   if (App::environment('testing')) {
       print_r(json_encode($obj, JSON_PRETTY_PRINT) .  PHP_EOL);
   } else {
       info(json_encode($obj));
   }
}

function transform($obj)
{
    return json_decode(json_encode($obj));
}

function start_log_query()
{
    DB::enableQueryLog();
}

function stop_log_query()
{
    DB::disableQueryLog();
}

function get_query($index = null)
{
    $laQuery = DB::getQueryLog();
    if (! $index) {
        $index = count($laQuery) - 1;
    }

    $query = $laQuery[$index]['query'];
    $bindings = $laQuery[$index]['bindings'];

    return $query.' '.json_encode($bindings);
}
