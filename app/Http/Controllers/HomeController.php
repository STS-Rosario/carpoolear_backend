<?php

namespace STS\Http\Controllers;

use Illuminate\Http\Request;
use STS\Services\Logic\UsersManager;

class HomeController extends Controller
{
    public function home()
    {
        $url = config('carpoolear.home_redirection', '');
        if (! empty($url)) {
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

    public function descarga(Request $request)
    {
        $useragent = (string) $request->userAgent();

        if (preg_match('/iPad|iPhone|iPod/', $useragent) === 1) {
            return redirect()->away('https://itunes.apple.com/ar/app/carpoolear/id1045211385?mt=8');
        }

        return redirect()->away('https://play.google.com/store/apps/details?id=com.sts.carpoolear&hl=es_419');
    }

    public function autoRojo()
    {
        return view('auto-rojo');
    }

    public function hashPassword(Request $request)
    {
        if ($request->has('p')) {
            return response((string) bcrypt($request->get('p')), 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response()->noContent();
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
        $needleStr = (string) $needle;
        if ($needleStr === '') {
            return true;
        }

        $haystackStr = (string) $haystack;
        $length = strlen($needleStr);

        return substr($haystackStr, -$length) === $needleStr;
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
        return $this->servePublicAsset('app', $name);
    }

    public function handleCampaigns($name)
    {
        return $this->servePublicAsset('campaigns', $name);
    }

    public function handleDev($name)
    {
        return $this->servePublicAsset('dev', $name);
    }

    private function servePublicAsset(string $directory, $name)
    {
        if ($this->endsWith($name, '.js')) {
            $strings = explode('/', $name);
            $file = $strings[count($strings) - 1];
            $path = public_path().'/'.$directory.'/'.$file;
        } else {
            $path = public_path().'/'.$directory.'/index.html';
        }

        if (! \File::exists($path)) {
            return response('', 404);
        }

        return \File::get($path);
    }

    public function desuscribirme(Request $request, UsersManager $userLogic)
    {
        $email = $request->get('email');
        if ($email) {
            $userLogic->mailUnsuscribe($email);
        }

        return view('unsuscribe');
    }
}
