<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

// Route::get('/app/{name?}', function () {
//     return File::get(public_path().'/app/index.html');
// })->where('name', '[\/\w\.-]*');

Route::get('/', 'HomeController@home');
Route::get('/acerca-de-equipo', 'HomeController@acercaDeEquipo');
Route::get('/acerca-de-proyecto', 'HomeController@acercaDeProyecto');
Route::get('/app/{name?}', 'HomeController@handleApp')->where('name', '[\/\w\.-]*');

// Route::group(['middleware' => 'cors', 'prefix' => 'api'], function () {
    //Route::post("/login", 'Api\AuthController@login');
    //Route::post("/registrar", 'Api\AuthController@registrar');
    //Route::post("/retoken", 'Api\AuthController@retoken');
    //Route::post("/logoff", 'Api\AuthController@logoff');
    /*
    Route::group(['prefix' => 'social'], function () {
        Route::post("/login/{provider?}", 'Api\SocialController@login');
        Route::post("/friends/{provider?}", 'Api\SocialController@friends');
        Route::post("/update/{provider?}", 'Api\SocialController@update');
    });

    Route::group(['prefix' => 'profile'], function () {
        Route::get("/show/{id?}", 'Api\ProfileController@show');
        Route::post("/update", 'Api\ProfileController@update');
        Route::post("/update/photo", 'Api\ProfileController@updatePhoto');
    });

    Route::group(['prefix' => 'friends'], function () {
        Route::post("/accept/{id?}", 'Api\FriendsController@accept');
        Route::post("/request/{id?}", 'Api\FriendsController@request');
        Route::post("/delete/{id?}", 'Api\FriendsController@delete');
        Route::post("/reject/{id?}", 'Api\FriendsController@reject');
    });
    */
// });
