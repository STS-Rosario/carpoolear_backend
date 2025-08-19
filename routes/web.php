<?php
 
use Illuminate\Support\Facades\Route;
use STS\Http\Controllers\Api\v1\DataController;
use STS\Http\Controllers\HomeController;
use STS\Http\Controllers\PaymentController;
use STS\Http\Controllers\Api\v1\MercadoPagoWebhookController;
    
Route::get('/', [HomeController::class, 'home']);
Route::get('/home', [HomeController::class, 'home']);
Route::get('/generateHash', [HomeController::class, 'hashPassword']);
Route::get('/mesadeayuda', [HomeController::class, 'mesadeayuda']);
Route::get('/acerca-de-equipo', [HomeController::class, 'acercaDeEquipo']);
Route::get('/acerca-de-proyecto', [HomeController::class, 'acercaDeProyecto']);
Route::get('/plataforma-preguntas-frecuentes', [HomeController::class, 'plataformaPreguntasFrecuentes']);
Route::get('/plataforma-recomendaciones', [HomeController::class, 'plataformaRecomendaciones']);
Route::get('/plataforma-terminos-condiciones', [HomeController::class, 'plataformaTerminosYCondiciones']);
Route::get('/colabora-como-colaborar', [HomeController::class, 'colaboraComoColaborar']);
Route::get('/colabora-ideame-2014', [HomeController::class, 'colaboraIdeame2014']);
Route::get('/difusion', [HomeController::class, 'difusion']);
Route::get('/privacidad', [HomeController::class, 'privacidad']);
Route::get('/terminos', [HomeController::class, 'terminos']);
Route::get('/contacto', [HomeController::class, 'contacto']);
Route::get('/autorojo', [HomeController::class, 'autoRojo']);
Route::get('/descarga', [HomeController::class, 'descarga']);
Route::get('/app/{name?}', [HomeController::class, 'handleApp'])->where('name', '[\/\w\.-]*');
Route::get('/campaigns/{name?}', [HomeController::class, 'handleCampaigns'])->where('name', '[\/\w\.-]*');
Route::get('/dev/{name?}', [HomeController::class, 'handleDev'])->where('name', '[\/\w\.-]*');
Route::get('/desuscribirme', [HomeController::class, 'desuscribirme']);
Route::get('/test', [HomeController::class, 'test']);
Route::get('/encuentrocarpoolero', [HomeController::class, 'encuentrocarpoolero']);
Route::get('/data-web', [DataController::class, 'data']);
Route::get('/donar', [HomeController::class, 'donar']);
Route::get('/donar-compartir', [HomeController::class, 'donarcompartir']);
Route::get('/datos', [HomeController::class, 'datos']);
Route::get('/freelance', [HomeController::class, 'freelance']);
Route::get('/derrumbe', [HomeController::class, 'derrumbe']);
Route::get('/lucro', [HomeController::class, 'lucro']);
Route::get('/covid', [HomeController::class, 'covid']);
Route::get('/colabora-programando', [HomeController::class, 'programar']);

Route::get('/transbank', [PaymentController::class, 'transbank']);
Route::any('/transbank-respuesta', [PaymentController::class, 'transbankResponse']);
Route::any('/transbank-final', [PaymentController::class, 'transbankFinal']);

// MercadoPago webhook
Route::any('/webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
    ->withoutMiddleware(['web']);

Route::get('/config.xml', function () {
    return response()->file(public_path('app/config.xml'));
});
