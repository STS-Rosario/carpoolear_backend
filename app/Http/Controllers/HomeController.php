<?php

namespace STS\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use STS\Entities\Rating as RatingModel;
use STS\Contracts\Logic\User as UserLogic;

use Transbank\Webpay\Configuration;
use Transbank\Webpay\Webpay;
use Illuminate\Support\Facades\Redirect;

class HomeController extends Controller
{
    public function home()
    {
        return view('home');
    }

    public function privacidad()
    {
        return view('privacidad');
    }

    public function terminos()
    {
        return view('terminos');
    }

    public function acercaDeEquipo()
    {
        return view('acerca-de-equipo');
    }

    public function acercaDeProyecto()
    {
        return view('acerca-de-proyecto');
    }

    public function descarga()
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'];

        $isIOS = preg_match('/iPad|iPhone|iPod/', $useragent);

        if ($isIOS) {
            header('Location: https://itunes.apple.com/ar/app/carpoolear/id1045211385?mt=8');
            die();
        } else {
            header('Location: https://play.google.com/store/apps/details?id=com.sts.carpoolear&hl=es_419');
            die();
        }
    }

    public function autoRojo()
    {
        return view('auto-rojo');
    }

    public function hashPassword(Request $request)
    {
        if ($request->has('p')) {
            echo bcrypt($request->get('p'));
            die;
        }
    }

    public function plataformaPreguntasFrecuentes()
    {
        return view('plataforma-preguntas-frecuentes');
    }

    public function plataformaRecomendaciones()
    {
        return view('plataforma-recomendaciones');
    }

    public function plataformaTerminosYCondiciones()
    {
        return view('plataforma-terminos-condiciones');
    }

    public function colaboraComoColaborar()
    {
        return view('colabora-como-colaborar');
    }

    public function colaboraIdeame2014()
    {
        return view('colabora-ideame-2014');
    }

    public function difusion()
    {
        return view('difusion');
    }

    public function mesadeayuda()
    {
        return view('mesadeayuda');
    }

    public function contacto()
    {
        return view('contacto');
    }

    public function encuentrocarpoolero()
    {
        return view('encuentrocarpoolero');
    }

    public function donar()
    {
        return view('donar');
    }

    public function donarcompartir()
    {
        return view('donar-compartir');
    }

    public function datos()
    {
        return view('datos');
    }

    public function programar()
    {
        return view('programar');
    }

    public function transbank()
    {
        $transaction = (new Webpay(Configuration::forTestingWebpayPlusNormal()))->getNormalTransaction();
        $amount = 1000;
        // Identificador que será retornado en el callback de resultado:
        $sessionId = "mi-id-de-sesion";
        // Identificador único de orden de compra:
        $buyOrder = strval(rand(100000, 999999999));
        $returnUrl = "http://carpoolear.192.168.0.3.nip.io/transbank-respuesta";
        $finalUrl = "http://carpoolear.192.168.0.3.nip.io/transbank-respuesta";
        $initResult = $transaction->initTransaction($amount, $buyOrder, $sessionId, $returnUrl, $finalUrl);

        $formAction = $initResult->url;
        $tokenWs = $initResult->token;
        // var_dump($initResult);die;
        return view('transbank', [
            'formAction' => $formAction,
            'tokenWs' => $tokenWs
        ]);
    }

    public function transbankResponse (Request $request) {
        $transaction = (new Webpay(Configuration::forTestingWebpayPlusNormal()))->getNormalTransaction();
        $result = $transaction->getTransactionResult($request->input("token_ws"));
        /* object(Transbank\Webpay\transactionResultOutput)#419 (8) { ["accountingDate"]=> string(4) "0904" ["buyOrder"]=> string(9) "119027553" ["cardDetail"]=> object(Transbank\Webpay\cardDetail)#425 (2) { ["cardNumber"]=> string(4) "6623" ["cardExpirationDate"]=> NULL } ["detailOutput"]=> object(Transbank\Webpay\wsTransactionDetailOutput)#421 (7) { ["authorizationCode"]=> string(4) "1213" ["paymentTypeCode"]=> string(2) "VN" ["responseCode"]=> int(0) ["sharesNumber"]=> int(0) ["amount"]=> string(4) "1000" ["commerceCode"]=> string(12) "597020000540" ["buyOrder"]=> string(9) "119027553" } ["sessionId"]=> string(15) "mi-id-de-sesion" ["transactionDate"]=> string(29) "2019-09-04T12:04:35.719-04:00" ["urlRedirection"]=> string(57) "https://webpay3gint.transbank.cl/webpayserver/voucher.cgi" ["VCI"]=> string(3) "TSY" }  */
        $output = $result->detailOutput;
        if ($output->responseCode == 0) {
            // Transaccion exitosa, puedes procesar el resultado con el contenido de
            // las variables result y output.
            return view('transbank-respuesta', []);
        }
    }

    public function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }

    public function test()
    {
        /* $user = new \STS\User();
        $user->id = 11525;
        $ratingRepository = new \STS\Repository\RatingRepository();
        $data = array();
        $data['value'] = RatingModel::STATE_POSITIVO;
        $ratings = $ratingRepository->getRatingsCount($user, $data);
        var_dump($ratings); die; */

        /* $user = \STS\User::where('id', 23124)->first();
        $messageRepo = new \STS\Repository\MessageRepository();
        $timestamp = time();
        $messages = $messageRepo->getMessagesUnread($user, $timestamp);
        echo $messages->count(); die;*/
        /*$criterias = [
            ['key' => 'trip_date', 'value' => '2018-03-08 13:29:00', 'op' => '<'],
            ['key' => 'mail_send', 'value' => false],
            ['key' => 'is_passenger', 'value' => false],
        ];

        $withs = ['user', 'passenger'];

        $trips = \STS\Entities\Trip::orderBy('trip_date');


        $trips->where('mail_send', false);
        $trips->where('is_passenger', false);

        var_dump($trips->get());die;*/
        $first = new Carbon('first day of this month');
        $last = new Carbon('last day of this month');
        var_dump($first);
        var_dump($last);
        die;
    }

    public function handleApp($name)
    {
        if ($this->endsWith($name, '.js')) {
            $strings = explode('/', $name);
            $file = $strings[count($strings) - 1];

            return \File::get(public_path().'/app/'.$file);
        } else {
            return \File::get(public_path().'/app/index.html');
        }
    }

    public function handleDev($name)
    {
        if ($this->endsWith($name, '.js')) {
            $strings = explode('/', $name);
            $file = $strings[count($strings) - 1];

            return \File::get(public_path().'/dev/'.$file);
        } else {
            return \File::get(public_path().'/dev/index.html');
        }
    }

    public function desuscribirme(Request $request, UserLogic $userLogic)
    {
        $email = $request->get('email');
        if ($email) {
            $userLogic->mailUnsuscribe($email);
        }

        return view('unsuscribe');
    }
}
