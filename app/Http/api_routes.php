<?php

$v1_path = 'STS\Http\Controllers\Api\v1\\';

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function ($api) use ($v1_path) {
    $api->post('login', $v1_path . 'AuthController@login');
    $api->post('retoken', $v1_path . 'AuthController@retoken');
    $api->post('logout', $v1_path .'AuthController@logout');
});