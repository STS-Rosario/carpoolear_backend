<?php

namespace STS\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use STS\Entities\Trip as TripModel;
use STS\Contracts\Logic\User as UserLogic;
use STS\Contracts\Logic\Routes as RoutesLogic;
use STS\Entities\NodeGeo;
use STS\Entities\Trip;
use STS\Entities\Route;
use STS\Repository\RoutesRepository;

use Illuminate\Support\Facades\Redirect;

class HomeController extends Controller
{
    public function home()
    {
        $url = config('carpoolear.home_redirection', '');
        if (!empty($url)) {
            return redirect()->away($url);
        } else {
            return view('home');
        }
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

    public function covid()
    {
        return view('covid');
    }

    public function freelance()
    {
        return view('freelance');
    }
    public function derrumbe()
    {
        return view('derrumbe');
    }

    public function lucro()
    {
        return view('lucro');
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
        /* $repo = new RoutesRepository();
        $manager = new \STS\Services\Logic\RoutesManager($repo);
        $bsAs = NodeGeo::where('id', 39428)->first();
        $laplata = NodeGeo::where('id', 29198)->first();
        $trip = Trip::where('id', 1)->first();
        $route = (object)[
            'origin' => $bsAs,
            'destiny' => $laplata
        ];
        $manager->createRoute($route); */
        /* $trip = Trip::where('id', 182307)->with([
            'user', 
            'user.accounts', 
            'points', 
            'passenger',
            'passengerAccepted', 
            'car', 
            'ratings',
            'routes'
        ])->first();
        echo '<pre>';
        var_dump(json_encode($trip));die; */
        /* \Mail::raw('Text to e-mail', function ($message) {
            $message->to('pabloluisbotta@gmail.com', 'test')->subject('email test text');
        });*/
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
