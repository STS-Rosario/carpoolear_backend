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

Route::get('/', function () {
    return view('welcome');
});

Route::group([ /*'middleware' => 'cors', */ 'prefix' => 'api'], function () {
    Route::post("/login", 'Api\AuthController@login');
    Route::post("/registrar", 'Api\AuthController@registrar'); 
    Route::post("/retoken", 'Api\AuthController@retoken'); 
    Route::post("/logoff", 'Api\AuthController@logoff');   

    Route::group(['prefix' => 'profile'], function () {
        Route::post("/show/{id?}", 'Api\Profile@show');    
        Route::post("/update", 'Api\Profile@update');
    });

});