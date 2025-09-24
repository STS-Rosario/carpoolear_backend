@extends('layouts.master')

@section('title', 'Mesa de ayuda - Carpoolear')
@section('body-class', 'body-mesadeayuda')

@section('content')
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48">

              <h2>Mesa de ayuda</h2>

              <p><strong>Ante cualquier consulta, puedes contactarte con la mesa de ayuda vía <a href="https://facebook.com/carpoolear" target="_blank">Facebook</a> o e-mail <a href="mailto:carpoolear@stsrosario.org.ar" target="_top">carpoolear@stsrosario.org.ar</a>.</strong></p>

              <h3>Instrucciones para instalar la web app (PWA) en Android y iOS</h3>
              
              <div class="row">
                <div class="col-md-6">
                  <div class="embed-responsive embed-responsive-16by9">
                    <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/g34Re3WkqT0" allowfullscreen></iframe>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="embed-responsive embed-responsive-16by9">
                    <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/p2_GjTpgT6Y" allowfullscreen></iframe>
                  </div>
                </div>
              </div>

              {{-- 
              @php
                function isMobile() {
                    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
                }

                if ( isMobile() ) {
                  echo "Información de tu dispositivo: " . $_SERVER['HTTP_USER_AGENT'] . "\n\n";
                }
              @endphp
              --}}
              <br>
              <h3>No puedo iniciar sesión con Facebook/Apple</h3>
              <p>El ingreso/registro via Apple ya no funciona más. Escribinos a la mesa de ayuda de Carpoolear para poder recuperar tu cuenta y migrarla a una vinculada a mail. La mesa de ayuda de Carpoolear funciona desde <a href="mailto:carpoolear@stsrosario.org.ar" target="_top">carpoolear@stsrosario.org.ar</a>, mensaje privado de <a href="https://instagram.com/carpoolear" target="_blank">Instagram</a> y <a href="https://facebook.com/carpoolear" target="_blank">Facebook</a>.
              </p>

              <h3>Conoce más</h3>
              <p><a href="https://carpoolear.com.ar/plataforma-preguntas-frecuentes" target="_blank">Preguntas Frecuentes</a>.</p>
              <p><a href="https://carpoolear.com.ar/plataforma-recomendaciones" target="_blank">Recomendaciones</a>.</p>

            </div>
        </div>
    </div>
    </section>
@endsection
