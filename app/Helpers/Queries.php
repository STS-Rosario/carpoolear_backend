<?php

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;

function match_array($data)
{
    return is_array($data) ? $data : [$data];
}

function make_pagination($query, $pageNumber = null, $pageSize = null)
{
    if (! $pageNumber) {
        $pageNumber = 1;
    }

    if (func_num_args() < 3) {
        $pageSize = 20;
    }

    if ($pageSize === null) {
        return $query->get();
    }

    Paginator::currentPageResolver(function () use ($pageNumber) {
        return $pageNumber;
    });

    return $query->paginate($pageSize);
}

function console_log($obj)
{
    $payload = json_encode($obj, JSON_PRETTY_PRINT).PHP_EOL;

    if (App::environment('testing')) {
        print_r($payload);
    } else {
        Log::info($payload);
    }
}

// function transform($obj)
// {
//     return json_decode(json_encode($obj));
// }

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
    if ($index === null) {
        $index = count($laQuery) - 1;
    }

    $query = $laQuery[$index]['query'];
    $bindings = $laQuery[$index]['bindings'];

    return $query.' '.json_encode($bindings);
}
