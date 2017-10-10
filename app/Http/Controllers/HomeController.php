<?php

namespace STS\Http\Controllers;

class HomeController extends Controller
{
    public function home()
    {
        return view('home');
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
}
