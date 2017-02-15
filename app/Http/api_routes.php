<?php

$v1_path = 'STS\Http\Controllers\Api\v1\\';

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function ($api) use ($v1_path) {
    $api->post('login',     $v1_path . 'AuthController@login');
    $api->post('retoken',   $v1_path . 'AuthController@retoken');
    $api->post('logout',    $v1_path . 'AuthController@logout');

    $api->group(['prefix' => 'users'], function ($api) use ($v1_path) {
        $api->post("/",                 $v1_path . 'ProfileController@create');    
        $api->get("/{id?}",             $v1_path . 'ProfileController@show');    
        $api->put("/",                  $v1_path . 'ProfileController@update');
        $api->put("/photo",             $v1_path . 'ProfileController@updatePhoto');
    });

    $api->group(['prefix' => 'friends'], function ($api) use ($v1_path) {
        $api->post("/accept/{id?}",     $v1_path . 'FriendsController@accept');    
        $api->post("/request/{id?}",    $v1_path . 'FriendsController@request');
        $api->post("/delete/{id?}",     $v1_path . 'FriendsController@delete');
        $api->post("/reject/{id?}",     $v1_path . 'FriendsController@reject'); 
        $api->get("/",                  $v1_path . 'FriendsController@index');
        $api->get("/pedings",           $v1_path . 'FriendsController@pedings');
    });

    $api->group(['prefix' => 'social'], function ($api) use ($v1_path) {
        $api->post("/login/{provider?}",    $v1_path . 'SocialController@login');    
        $api->post("/friends/{provider?}",  $v1_path . 'SocialController@friends');
        $api->put("/update/{provider?}",    $v1_path . 'SocialController@update');
    });

});

