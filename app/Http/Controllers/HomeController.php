<?php

namespace STS\Http\Controllers;

use Illuminate\Http\Request;
use STS\Contracts\Logic\User as UserLogic;
use STS\Entities\Rating as RatingModel;

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
        $useragent = $_SERVER ['HTTP_USER_AGENT'];
        
        $isIOS = preg_match ('/iPad|iPhone|iPod/', $useragent);
        
        if($isIOS) {
            header("Location: https://itunes.apple.com/ar/app/carpoolear/id1045211385?mt=8");
            die();
        } else {
            header("Location: https://play.google.com/store/apps/details?id=com.sts.carpoolear&hl=es_419");
            die();
        }
    }

    public function autoRojo()
    {
        return view('auto-rojo');
    }


	public function hashPassword(Request $request) {
        if ($request->has("p")) {
			echo bcrypt($request->get("p"));die;
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

    public function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }

    public function test () {
        /* $user = new \STS\User();
        $user->id = 11525;
        $ratingRepository = new \STS\Repository\RatingRepository();
        $data = array();
        $data['value'] = RatingModel::STATE_POSITIVO;
        $ratings = $ratingRepository->getRatingsCount($user, $data);
        var_dump($ratings); die; */


        $user = \STS\User::where('id', 23124)->first();
        $messageRepo = new \STS\Repository\MessageRepository();
        $timestamp = time();
        $messages = $messageRepo->getMessagesUnread($user, $timestamp);
        echo $messages->count(); die;
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


    public function desuscribirme(Request $request, UserLogic $userLogic)
    {
        $email = $request->get("email");
        if ($email) {
            $userLogic->mailUnsuscribe($email);
        }
        return view('unsuscribe');
    }

}
