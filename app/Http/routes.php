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
/*
Route::get('/app/{name?}', function () {
	return File::get(public_path().'/app/index.html');
})->where('name', '[\/\w\.-]*');
*/

Route::get('/', 'HomeController@home');
Route::get('/home', 'HomeController@home');
Route::get('/generateHash', 'HomeController@hashPassword');
Route::get('/mesadeayuda', 'HomeController@mesadeayuda');
Route::get('/acerca-de-equipo', 'HomeController@acercaDeEquipo');
Route::get('/acerca-de-proyecto', 'HomeController@acercaDeProyecto');
Route::get('/plataforma-preguntas-frecuentes', 'HomeController@plataformaPreguntasFrecuentes');
Route::get('/plataforma-recomendaciones', 'HomeController@plataformaRecomendaciones');
Route::get('/plataforma-terminos-condiciones', 'HomeController@plataformaTerminosYCondiciones');
Route::get('/colabora-como-colaborar', 'HomeController@colaboraComoColaborar');
Route::get('/colabora-ideame-2014', 'HomeController@colaboraIdeame2014');
Route::get('/difusion', 'HomeController@difusion');
Route::get('/privacidad', 'HomeController@privacidad');
Route::get('/terminos', 'HomeController@terminos');
Route::get('/contacto', 'HomeController@contacto');
Route::get('/autorojo', 'HomeController@autoRojo');
Route::get('/descarga', 'HomeController@descarga');
Route::get('/app/{name?}', 'HomeController@handleApp')->where('name', '[\/\w\.-]*');
Route::get('/dev/{name?}', 'HomeController@handleDev')->where('name', '[\/\w\.-]*');
Route::get('/desuscribirme', 'HomeController@desuscribirme');
Route::get('/test', 'HomeController@test');
Route::get('/encuentrocarpoolero', 'HomeController@encuentrocarpoolero');
Route::get('/data', 'DataController@data');
Route::get('/donar', 'HomeController@donar');
Route::get('/datos', 'HomeController@datos');
