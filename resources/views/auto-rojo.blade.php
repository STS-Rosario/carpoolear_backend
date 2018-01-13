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
    .social-share-buttons svg {
        fill: #CCC;
    }
    .social-share-buttons svg:hover {
        fill: #FFF;
    }

</style>
<section style="background: #D12624;">
    <div class="container">
        <div class="row">
            <div class="col-sm-10 col-sm-offset-1 mb-xs-24 text-center pt48">
                <h2 style="color: white; font-weight: bold;">La verdad sobre el auto rojo</h2>
                <div class="embed-video-container embed-responsive embed-responsive-16by9">
                    <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/5QkvOg--Kew"  allowfullscreen="allowfullscreen"></iframe>
                </div>
                <div class="embed-video-container embed-responsive embed-responsive-16by9">
                    <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/Icy9r_C7E9Y"  allowfullscreen="allowfullscreen"></iframe>
                </div>
            </div>

            <div class="col-md-12 col-sm-12 text-center">
                <h1 style="margin-top:1em;"><a class="accede-app" href="https://carpoolear.com.ar/app" style="color: white;">Compartí la campaña</a></h1>
                <div class="social-share-buttons">
                    <a href="http://www.facebook.com/dialog/feed?app_id=147151221990591&link=https%3A%2F%2Fcarpoolear.com.ar%2Fautorojo&message=&%C2%BFSab%C3%A9s%20la%20verdad%20sobre%20el%20tema%20Auto%20Rojo%20de%20Vilma%20Palma%3F%20Carpoolear%20te%20invita%20a%20concerla" title="Compartir en Facebook" target="_blank">
                        <i class="ti-facebook"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=https%3A%2F%2Fcarpoolear.com.ar%2Fautorojo&text=%C2%BFSab%C3%A9s%20la%20verdad%20sobre%20el%20tema%20Auto%20Rojo%20de%20Vilma%20Palma%3F&via=carpoolear&hashtags=autorojo" title="Compartir en Twitter" target="_blank">
                        <i class="ti-twitter-alt"></i>
                    </a>
                    <a href="https://plus.google.com/share?url=https%3A%2F%2Fcarpoolear.com.ar%2Fautorojo" title="Compartir en Google+" target="_blank">
                        <i class="ti-google"></i>
                    </a>
                    <a href="whatsapp://send?text=%C2%BFSab%C3%A9s%20la%20verdad%20sobre%20el%20tema%20Auto%20Rojo%20de%20Vilma%20Palma%3F%20Carpoolear%20te%20invita%20a%20concerla%20ac%C3%A1%3A%20https%3A%2F%2Fcarpoolear.com.ar%2Fautorojo" title="Compartir en Whats App" target="_blank" class="hidden-sm hidden-md hidden-lg" >
                        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                            width="32px" height="32px" viewBox="0 0 90 90" style="enable-background:new 0 0 90 90;" xml:space="preserve">
                            <g>
                                <path id="WhatsApp" d="M90,43.841c0,24.213-19.779,43.841-44.182,43.841c-7.747,0-15.025-1.98-21.357-5.455L0,90l7.975-23.522
                                    c-4.023-6.606-6.34-14.354-6.34-22.637C1.635,19.628,21.416,0,45.818,0C70.223,0,90,19.628,90,43.841z M45.818,6.982
                                    c-20.484,0-37.146,16.535-37.146,36.859c0,8.065,2.629,15.534,7.076,21.61L11.107,79.14l14.275-4.537
                                    c5.865,3.851,12.891,6.097,20.437,6.097c20.481,0,37.146-16.533,37.146-36.857S66.301,6.982,45.818,6.982z M68.129,53.938
                                    c-0.273-0.447-0.994-0.717-2.076-1.254c-1.084-0.537-6.41-3.138-7.4-3.495c-0.993-0.358-1.717-0.538-2.438,0.537
                                    c-0.721,1.076-2.797,3.495-3.43,4.212c-0.632,0.719-1.263,0.809-2.347,0.271c-1.082-0.537-4.571-1.673-8.708-5.333
                                    c-3.219-2.848-5.393-6.364-6.025-7.441c-0.631-1.075-0.066-1.656,0.475-2.191c0.488-0.482,1.084-1.255,1.625-1.882
                                    c0.543-0.628,0.723-1.075,1.082-1.793c0.363-0.717,0.182-1.344-0.09-1.883c-0.27-0.537-2.438-5.825-3.34-7.977
                                    c-0.902-2.15-1.803-1.792-2.436-1.792c-0.631,0-1.354-0.09-2.076-0.09c-0.722,0-1.896,0.269-2.889,1.344
                                    c-0.992,1.076-3.789,3.676-3.789,8.963c0,5.288,3.879,10.397,4.422,11.113c0.541,0.716,7.49,11.92,18.5,16.223
                                    C58.2,65.771,58.2,64.336,60.186,64.156c1.984-0.179,6.406-2.599,7.312-5.107C68.398,56.537,68.398,54.386,68.129,53.938z"/>
                            </g>
                        </svg>
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