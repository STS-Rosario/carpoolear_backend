<?php

namespace STS\Http\Controllers;

use League\Fractal\Manager;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
class Controller extends \Illuminate\Routing\Controller {
    //

    public function collection($items, $transformer) {
        $fractal = new Manager();
        $resource = new Collection($items, $transformer);
        return response()->json($fractal->createData($resource)->toArray());
    }

    public function item($item, $transformer) {
        $fractal = new Manager();
        $resource = new Item($item, $transformer);
        return response()->json($fractal->createData($resource)->toArray());
    }

    public function paginator($items, $transformer) {
        $fractal = new Manager();
        $resource = new Collection($items, $transformer);
        $resource->setPaginator(new IlluminatePaginatorAdapter($items));
        return response()->json($fractal->createData($resource)->toArray());
    }
}
