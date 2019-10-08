<?php

namespace STS\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use STS\Entities\Rating as RatingModel;
use STS\Contracts\Logic\User as UserLogic;
use STS\Contracts\Logic\Routes as RoutesLogic;
use STS\Entities\NodeGeo;
use STS\Entities\Trip;
use STS\Entities\Route;
use STS\Repository\RoutesRepository;

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
        $repo = new RoutesRepository();
        $manager = new \STS\Services\Logic\RoutesManager($repo);
        $ros = NodeGeo::where('id', 911)->first();
        $bsAs = NodeGeo::where('id', 1)->first();
        $trip = Trip::where('id', 1)->first();
        $manager->createRoute($ros, $bsAs, $trip);
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
