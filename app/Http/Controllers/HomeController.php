<?php

namespace STS\Http\Controllers;



class HomeController extends Controller
{
    public function home()
    {
        return view('welcome');
    }

    public function handleApp()
    {
        return \File::get(public_path().'/app/index.html');
    }
}
