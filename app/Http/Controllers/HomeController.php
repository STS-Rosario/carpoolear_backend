<?php

namespace STS\Http\Controllers;


class HomeController extends Controller
{
    public function home()
    {
        return view('welcome');
    }

    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    public function handleApp($name)
    {
        if ($this->endsWith($name, 'cordova.js')) {
            return \File::get(public_path().'/app/cordova.js');
        } else {
            return \File::get(public_path().'/app/index.html');
        }
    }
}
