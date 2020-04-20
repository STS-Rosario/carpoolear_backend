@extends('layouts.master')

@section('title', 'Carpoolear')

@section('content')

{{-- <div class="container">
    <div class="row">
        <div class="col-sm-12 pt48">
            <br>
            <br>
            <br>
            <img src="img/suspendido.png" style="display:block;margin: 1em auto;" />
            <br>
            <p class="lead">
                <strong>Carpoolear</strong> apoya las medidas preventivas por el Coronavirus y da de baja la plataforma y grupos de viaje vinculados hasta el 24 de marzo inclusive.
            </p>
            <p class="lead">
                Lamentamos cualquier problema ocasionado y te pedimos no viajar si no es necesario.
            </p>
            <p class="lead">
                Para más información sobre las medidas del Gobierno Argentino podés entrar a <a href="https://www.argentina.gob.ar/salud/coronavirus-COVID-19">https://www.argentina.gob.ar/salud/coronavirus-COVID-19</a>
            </p>
            <br>
            <br>
        </div>
    </div>
</div> --}}
<section class="image-bg parallax pt240 pb104 pt-xs-80 pb-xs-80">
    <div class="background-image-holder">
        <img alt="image" class="background-image" src="img/carpoolear-home3.jpg" />
    </div>
    
    <div class="container">
        <div class="row">
            <div class="col-md-12 col-sm-12 text-center">
                <h1><a class="accede-app" href="https://carpoolear.com.ar/app">Compartí tu viaje</a></h1>
                <a class="btn btn-lg btn-filled boton-usa-plataforma" href="https://carpoolear.com.ar/app">Usá la plataforma</a>
                <p class="lead disponible-celulares mb-xs-32">
                    También disponible para celulares:
                </p>
                <!--<a href="https://carpoolear.com.ar/app">-->
                    <!--<img class="image-small" alt="Accedé a la aplicación" src="img/accede-aplicacion.png" />-->
                <!--</a>-->
                <div class="logos-store">
                    <a href="https://play.google.com/store/apps/details?id=com.sts.carpoolear&hl=es_419">
                        <img class="image-xs" alt="Disponible en Google Play" src="img/googleplay.png" />
                    </a>
                    <a href="https://itunes.apple.com/ar/app/carpoolear/id1045211385?mt=8">
                        <img class="image-xs" alt="App Store" src="img/appstore.png" />
                    </a>
                </div>
                <img class="firma" src="img/firma.png" alt="Un proyecto de la ONG STS Rosario. Un proyecto argentino, sin fines de lucro y colaborativo">
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-10 col-sm-offset-1 mb-xs-24 text-center">
                <h2>¿Qué es Carpoolear?</h2>
                <div class="embed-video-container embed-responsive embed-responsive-16by9">
                    <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/pB5r8ex8Abk"  allowfullscreen="allowfullscreen"></iframe>
                </div>
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>
<section class="section-calificaciones">
    <div class="container">
        <div class="row v-align-children">
            <div class="col-sm-6 mb-xs-24">
                <h2 class="mb64 mb-xs-32">Acordate de calificar</h2>
                <div class="mb40 mb-xs-24">
                    <p>
                        Contanos cómo te fue compartiendo viaje con ese conductor o pasajero.
                    </p>
                    <p>
                        Si recomendás carpoolear con él, dale pulgar arriba :D
                    </p>
                    
                </div>
            </div>
            <div class="col-sm-4 col-sm-6 col-sm-offset-1 text-center">
                <img alt="Screenshot" src="img/thumbsUpIcon.png" />
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>
<section>
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-md-push-3 text-center">
                <div class="image-slider slider-paging-controls controls-outside">
                    <ul class="slides">
                        <li class="mb32">
                            <img alt="App" src="img/dispositivos.jpg" />
                        </li>
                        <li class="mb32">
                            <img alt="App" src="img/thumbsUpIcon_big_bg.png" />
                        </li>
                        <li class="mb32">
                            <img alt="App" src="img/searchIcon.png" />
                        </li>
                        <li class="mb32">
                            <img alt="App" src="img/visibilidad.png" />
                        </li>
                        <li class="mb32">
                            <img alt="App" src="img/stopwatchIcon.png" />
                        </li>
                        <li class="mb32">
                            <img alt="App" src="img/collaborationIcon.png" />
                        </li>

                    </ul>
                </div>
            </div>

            <div class="col-md-3 col-md-pull-6">
                <div class="mt80 mt-xs-0 text-right text-left-xs">
                    <h5 class="uppercase bold mb16">Funciona en todos los dispositivos</h5>
                    <p class="fade-1-4">
                        Usá la plataforma en PC/tablet/smartphone en su <a href="https://carpoolear.com.ar/app">versión web</a>
                        o descargarte las apps para <a href="https://play.google.com/store/apps/details?id=com.sts.carpoolear&hl=es_419">Android</a> o <a href="https://itunes.apple.com/ar/app/carpoolear/id1045211385?mt=8">iPhone</a>.
                    </p>
                </div>
                
                <div class="mt80 mt-xs-80 text-right text-left-xs">
                    <h5 class="uppercase bold mb16">Sistema de calificaciones</h5>
                    <p class="fade-1-4">
                        Compartí viajes y contale al resto de la comunidad Carpoolear cómo te fue :)
                        Los conductores califican a los pasajeros y al revés.
                    </p>
                </div>

                <div class="mt80 mt-xs-0 text-right text-left-xs">
                    <h5 class="uppercase bold mb16">Buscador</h5>
                    <p class="fade-1-4">
                        Encontrá tu viaje eligiendo por origen, destino, fecha u hora... podés combinarlo como quieras! No hace falta cargar todos :D
                    </p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="mt80 mt-xs-0">
                    <h5 class="uppercase bold mb16">Privacidad</h5>
                    <p class="fade-1-4">
                        Compartí hasta dónde te sientas más cómodo, podés compartir con amigos, amigos de amigos o todos los usuarios del sistema.
                    </p>
                </div>

                <div class="mt80 mt-xs-0">
                    <h5 class="uppercase bold mb16">Registro fácil</h5>
                    <p class="fade-1-4">
                        Registrarte en un par de pasos con tú correo y completando un perfil básico o vinculando tu cuenta de Carpoolear con los datos públicos de alguna red social en la que ya participes.
                    </p>
                </div>

                <div class="mt80 mt-xs-0">
                    <h5 class="uppercase bold mb16">Colaboración</h5>
                    <p class="fade-1-4">
                        Podés colaborar en el equipo carpoolear sumandote o de forma externa con tareas de difusión y programación. También podés colaborar con $ o contactos.
                    </p>
                </div>
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>
<section class="image-bg overlay pt180 pb180 pt-xs-80 pb-xs-80 section-proyecto">
    <div class="background-image-holder">
        <img alt="image" class="background-image" src="img/equipo.jpg" />
    </div>
    
    <div class="container equipo">
        <div class="row">
            <div class="col-12">
                <div class="container">
                    <h2>Un proyecto colaborativo sin fines de lucro de código abierto</h2>
                    <p class="lead ruta-title mb48 mb-xs-32">
                        Esta es la ruta elegida por Carpoolear...
                    </p>
                </div>
                <div class="container proyecto-colaborativo">
                    <div class="row">
                        <div class="col-sm-12">
                            <h5 class="uppercase mb-xs-24">Colaborativo</h5>
                            <p>
                                Podés sumarte a participar en alguna de las áreas del proyecto (sistemas,comunicación o fundraising) o de forma externa colaborar con difusión y código del sistema.
                            </p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <h5 class="uppercase mb-xs-24">Sin fines de lucro</h5>
                            <p>
                                El dinero es importante pero no es un tema central dentro del proyecto. A veces tenemos plata… otras veces no… el proyecto siempre sigue adelante. Los fondos que ingresan se utilizan para mejoras y mantenimiento de la plataforma, comunicación.
                            </p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <h5 class="uppercase mb-xs-24">Código abierto</h5>
                            <p>
                                Desde el 2017 la programación de la plataforma es abierta, pudiendo revisarse cómo funciona completamente el sistema y se pueden proponer/generar mejoras a nivel de código.
                            </p>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>
<section>
    <div class="container">
        <div class="row v-align-children">
            
            <div class="col-md-6 ">
                <h2>¿Por qué Carpooleamos?</h2>
                <p>Podemos ahorrar $, pasarla mejor en el viaje, conocer gente y cuidar el medio ambiente<strong>*</strong></p>
                <p><em><strong>*</strong> cuando dos conductores viajan en sólo un auto, quedando el otro guardado, se genera un impacto positivo sobre el medio ambiente</em></p>

                <p>Menos autos circulando implica reducir...</p>
                <ul class="lead" data-bullet="ti-control-play">
                    <li>Emisión de gases de efecto invernadero</li>
                    <li>Consumo de combustibles fósiles</li>
                    <li>Embotellamientos</li>
                    <li>Contaminación</li>
                </ul>
            </div>
            <div class="col-sm-6 text-center mb-xs-24">
                <img class="cast-shadow" alt="Screenshot" src="img/mapa.jpg" />
            </div>
        </div>
        <!--end of row-->
    </div>
    <!--end of container-->
</section>
<section class="section-calificaciones pb0">
    <div class="container">
        <div class="row mb64 mb-xs-32">
            <div class="col-md-6 col-md-offset-3 col-sm-8 col-sm-offset-2 text-center">
                <h1 class="large">Cargá tu viaje</h1>
                <p class="lead mb48 mb-xs-32 fade-1-4"> 
                    ¿Qué esperás para empezar a compartir tus viajes en auto? ¡Conocé gente nueva, reducí gastos y ayudá al medioambiente!
                </p>
                <a class="btn btn-lg btn-filled boton-usa-plataforma" href="https://carpoolear.com.ar/app">Usá la plataforma</a>
            </div>
        </div><!--end of row-->
        
        <div class="row">
            <div class="col-sm-4 col-sm-offset-4 text-center">
                <img alt="App" src="img/carpoolear_top.png" />
            </div>	
        </div><!--end of row-->
    </div><!--end of container-->
</section>
@endsection