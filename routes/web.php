<?php
 
use Illuminate\Support\Facades\Route;
    
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
Route::get('/data-web', 'DataController@data');
Route::get('/donar', 'HomeController@donar');
Route::get('/donar-compartir', 'HomeController@donarcompartir');
Route::get('/datos', 'HomeController@datos');
Route::get('/freelance', 'HomeController@freelance');
Route::get('/derrumbe', 'HomeController@derrumbe');
Route::get('/lucro', 'HomeController@lucro');
Route::get('/covid', 'HomeController@covid');
Route::get('/colabora-programando', 'HomeController@programar');

Route::get('/transbank', 'PaymentController@transbank');
Route::any('/transbank-respuesta', 'PaymentController@transbankResponse');
Route::any('/transbank-final', 'PaymentController@transbankFinal');