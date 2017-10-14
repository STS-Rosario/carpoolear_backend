@extends('layouts.master')

@section('title', 'Carpoolear')

@section('content')
<style>
    .social-share-buttons a {
        font-size: 32px;
        color: #CCC;
        margin: 0 .4em;
        display: inline-block;
    }
    .social-share-buttons a:hover {
        color: #FFF;
    }
</style>
<section style="background: #D12624;">
    <div class="container">
        <div class="row">
            <div class="col-sm-10 col-sm-offset-1 mb-xs-24 text-center pt48">
                <h2 style="color: white; font-weight: bold;">La verdad sobre el auto rojo</h2>
                <div class="embed-video-container embed-responsive embed-responsive-16by9">
                    <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/95gwmHUx5MQ"  allowfullscreen="allowfullscreen"></iframe>
                </div>
            </div>

            <div class="col-md-12 col-sm-12 text-center">
                <h1 style="margin-top:1em;"><a class="accede-app" href="https://carpoolear.com.ar/app" style="color: white;">Compartí la campaña</a></h1>
                <div class="social-share-buttons">
                    <a href="https://www.facebook.com/sharer.php?u=https%3A%2F%2Fcarpoolear.com.ar%2Fautorojo" title="Compartir en Facebook" target="_blank">
                        <i class="ti-facebook"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=https%3A%2F%2Fcarpoolear.com.ar%2Fautorojo&text=%C2%BFSab%C3%A9s%20la%20verdad%20sobre%20el%20tema%20Auto%20Rojo%20de%20Vilma%20Palma%3F&via=carpoolear&hashtags=autorojo" title="Compartir en Twitter" target="_blank">
                        <i class="ti-twitter-alt"></i>
                    </a>
                    <a href="https://plus.google.com/share?url=https%3A%2F%2Fcarpoolear.com.ar%2Fautorojo" title="Compartir en Google+" target="_blank">
                        <i class="ti-google"></i>
                    </a>
                </div>
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>
<section>
    
    <div class="container">
        <div class="row">
            <div class="col-md-12 col-sm-12 text-center">
                <!--<h1><a class="accede-app" href="https://carpoolear.com.ar/app">Compartí tu viaje</a></h1>-->
                <div class="col-md-12 col-sm-12 text-center pt24">
                    <a class="btn btn-lg btn-filled boton-usa-plataforma" href="https://carpoolear.com.ar/app">Usá la plataforma web</a>
                </div>
                <p class="lead disponible-celulares mb-xs-32">
                    Disponible para celulares:
                </p>
                <div class="logos-store">
                    <a href="https://play.google.com/store/apps/details?id=com.sts.carpoolear&hl=es_419">
                        <img class="image-xs" alt="Disponible en Google Play" src="img/googleplay.png" />
                    </a>
                    <a href="https://itunes.apple.com/ar/app/carpoolear/id1045211385?mt=8">
                        <img class="image-xs" alt="App Store" src="img/appstore.png" />
                    </a>
                </div>
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>
<section>
    <div class="container">
        <div class="row">
            <div class="col-xs-6 text-center">
                <img src="img/logo_sts_nuevo_color.png" alt="STS Rosario">
            </div>
            <div class="col-xs-6 text-center">
                <img  src="/static/img/espacio_santafesino.jpg" alt="Realizado con el apoyo de Espacio Santafesino, Ministerio de Innovación y Cultura de Santa Fe. Convocatoria 2016." class="img-ES">
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>

@endsection