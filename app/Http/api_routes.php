<?php

$v1_path = 'STS\Http\Controllers\Api\v1\\';

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['middleware'=>'cors'], function ($api) use ($v1_path) {
    $api->post('login', $v1_path.'AuthController@login');
    $api->post('retoken', $v1_path.'AuthController@retoken');
    $api->post('logout', $v1_path.'AuthController@logout');
    $api->post('active/{activation_token?}', $v1_path.'AuthController@active');
    $api->post('reset-password', $v1_path.'AuthController@reset');
    $api->post('change-password/{token?}', $v1_path.'AuthController@changePasswod');
    
    $api->group(['prefix' => 'users'], function ($api) use ($v1_path) {
        $api->post('/', $v1_path.'UserController@create');
        $api->get('/{id?}', $v1_path.'UserController@show');
        $api->put('/', $v1_path.'UserController@update');
        $api->put('/photo', $v1_path.'UserController@updatePhoto'); 
    });  

    $api->group(['prefix' => 'notifications'], function ($api) use ($v1_path) {
        $api->get('/', $v1_path.'NotificationController@index');
        $api->delete('/{id?}', $v1_path.'NotificationController@delete');
        $api->get('/count', $v1_path.'NotificationController@count');
    });  
    
    $api->group(['prefix' => 'friends'], function ($api) use ($v1_path) {
        $api->post('/accept/{id?}', $v1_path.'FriendsController@accept');
        $api->post('/request/{id?}', $v1_path.'FriendsController@request');
        $api->post('/delete/{id?}', $v1_path.'FriendsController@delete');
        $api->post('/reject/{id?}', $v1_path.'FriendsController@reject');
        $api->get('/', $v1_path.'FriendsController@index');
        $api->get('/pedings', $v1_path.'FriendsController@pedings');
    });
    
    $api->group(['prefix' => 'social'], function ($api) use ($v1_path) {
        $api->post('/login/{provider?}', $v1_path.'SocialController@login');
        $api->post('/friends/{provider?}', $v1_path.'SocialController@friends');
        $api->put('/update/{provider?}', $v1_path.'SocialController@update');
    });
    
    $api->group(['prefix' => 'trips'], function ($api) use ($v1_path) {
        $api->get('/', $v1_path.'TripController@index');
        $api->post('/', $v1_path.'TripController@create');
        $api->put('/{id?}', $v1_path.'TripController@update');
        $api->delete('/{id?}', $v1_path.'TripController@delete');
        $api->get('/{id?}', $v1_path.'TripController@show');
        
        $api->get('/{tripId}/passengers', $v1_path.'PassengerController@passengers');
        $api->get('/{tripId}/requests', $v1_path.'PassengerController@requests');
        $api->post('/{tripId}/requests', $v1_path.'PassengerController@newRequest');
        $api->delete('/{tripId}/requests', $v1_path.'PassengerController@cancelRequest');
        $api->post('/{tripId}/requests/{userId}/accept', $v1_path.'PassengerController@acceptRequest');
        $api->post('/{tripId}/requests/{userId}/reject', $v1_path.'PassengerController@rejectRequest');
    });
     
    $api->group(['prefix' => 'conversations'], function ($api) use ($v1_path) {
        $api->get("/",  $v1_path . 'ConversationController@index');
        $api->post("/", $v1_path . 'ConversationController@create');
        $api->get("/{id?}",  $v1_path . 'ConversationController@getConversation');
        $api->get("/{id?}/users",  $v1_path . 'ConversationController@users');
        $api->post("/{id?}/users",  $v1_path . 'ConversationController@addUser');
        $api->delete("/{id?}/users/{userId?}", $v1_path . 'ConversationController@deleteUser');
    });
     
    $api->group(['prefix' => 'cars'], function ($api) use ($v1_path) {
        $api->get('/', $v1_path.'CarController@index');
        $api->post('/', $v1_path.'CarController@create');
        $api->put('/{id?}', $v1_path.'CarController@update');
        $api->delete('/{id?}', $v1_path.'CarController@delete');
        $api->get('/{id?}', $v1_path.'CarController@show');
    });
});