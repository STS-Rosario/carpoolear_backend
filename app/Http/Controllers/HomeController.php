<?php

namespace STS\Http\Controllers;

use STS\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;

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
