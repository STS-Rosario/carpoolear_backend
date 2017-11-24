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

              <h3>Versiones compatibles dispositivos móviles</h3>
              <p>Para que la aplicación móvil funcione con todas sus características, es necesario que el dispositivo móvil tenga instalada la siguiente versión del sistema operativo:
                  <ul>
                      <li><strong>Android</strong> 4.4 o posterior</li>
                      <li><strong>iOS</strong> 9 o posterior</li>
                  </ul>
              </p>
              <p>
                @php
                  function isMobile() {
                      return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
                  }

                  if ( isMobile() ) {
                    echo "Información de tu dispositivo: " . $_SERVER['HTTP_USER_AGENT'] . "\n\n";
                  }
                @endphp
              </p>

              <h3>No puedo iniciar sesión con Facebook - Versión Web</h3>
              <p>Si tenes problemas para iniciar sesión con Facebook, intenta lo siguiente:
	              <ul>
	                <li>1. Modo privado navegador: Para inciar el modo de navegación privada en Chrome presiona CTRL + SHIFT + N, en Firefox: CTRL + SHIFT + P, en Internet Explorer: presiona la tecla ALT para que aparezca la barra de menú. Haz clic en Herramientas y selecciona Exploración de InPrivate. <br>
	                Una vez iniciada la sesión modo privado, ingresa en carpoolear.com.ar/app/ e intenta iniciar sesión con tu cuenta.
	                </li>
	                <li>2. Deshabilitar Extensiones: Hay algunas Extensiones que suelen impedir el funcionamiento de ciertas funcionalidades de nuestro sitio, por lo que una opción para verificar si esta es la causa del problema de inicio de sesión, deshabilitando todas y en caso de poder ingresar a nuestro sitio, ir habilitando de a una para saber cual es el causante del inconveniente. Cualquier duda no dudes en consultarnos y te ayudaremos a agregar Carpoolear como excepción en la Extensión.</li>
	                <li>3. Borrar Cookies: Las Cookies son archivos que guarda el navegador con información sobre el sitio. Se pueden eliminar para que el sitio las vuelva a generlas. Te brindamos los enlaces oficiales de cada navegador que explican como hacerlo: <a href="https://support.google.com/chrome/answer/95647" target="_blank">Chrome</a>, <a href="https://support.mozilla.org/es/kb/Borrar%20cookies" target="_blank">Firefox</a>, <a href="http://help.opera.com/Windows/11.50/es-ES/cookies.html" target="_blank">Opera</a>, <a href="https://support.microsoft.com/es-es/help/17442/windows-internet-explorer-delete-manage-cookies" target="_blank">Internet Explorer</a>.</li>
	                <li>4. Si el problema persiste, iniciar la consola de desarrollo de Google Chrome y enviarnos captura de pantalla vía e-mail a <a href="mailto:carpoolear@stsrosario.org.ar" target="_top">carpoolear@stsrosario.org.ar</a> así podemos ver los errores y ayudarte. <a href="https://drive.google.com/file/d/0B7NuwPTmmEXBTGFfXzM1UFhoVnc/view" target="_blank">Ver explicación de como enviarnos consola de desarrollo.</a></li>
	              </ul>
              </p>

              <h3>No puedo iniciar sesión con Facebook - Versión App</h3>              
              <p>
	              <ul>
	                <li>1. Desintalar la aplicación y volver a instalar.</li>
	                <li>2. Si continúa sin funcionar, borrar datos y caché de la aplicación: <a href="https://drive.google.com/file/d/0B-c20gzHOTpmbFp2bDQwQmZXMlU/view" target="_blank">Ver video explicativo.</a></li>
	                <li>3. Si ninguna de estas opciones funcionó, enviarnos un e-mail a <a href="mailto:carpoolear@stsrosario.org.ar" target="_top">carpoolear@stsrosario.org.ar</a> con marca, modelo y sistema operativo de tu teléfono.</li>
	              </ul>              	
              </p>

              <h3>Conoce más</h3>
              <p><a href="https://carpoolear.com.ar/plataforma-preguntas-frecuentes" target="_blank">Preguntas Frecuentes</a>.</p>
              <p><a href="https://carpoolear.com.ar/plataforma-recomendaciones" target="_blank">Recomendaciones</a>.</p>

            </div>
        </div>
    </div>
    </section>
@endsection
