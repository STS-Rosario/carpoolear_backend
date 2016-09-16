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

Route::get('/', 'WelcomeController@index');

Route::get('home', 'HomeController@index');

Route::controllers([
	'auth' => 'Auth\AuthController',
	'password' => 'Auth\PasswordController',
]);

Route::group([ /*'middleware' => 'cors', */ 'prefix' => 'api'], function () {
    Route::post("/login", 'Api\AuthController@login');
    Route::post("/registrar", 'Api\AuthController@registrar');
    Route::post("/login/facebook", 'Api\AuthController@facebookLogin');
    Route::post("/retoken", 'Api\AuthController@retoken');
    
    //Route::post("/retoken", 'UserController@retoken');
    //Route::post("/usuario", 'UserController@usuario');
    
     
});