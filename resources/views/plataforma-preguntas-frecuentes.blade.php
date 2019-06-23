@extends('layouts.master')

@section('title', 'Preguntas Frecuentes - Plataforma - Carpoolear')
@section('body-class', 'body-plataforma plataforma-preguntas-frecuentes')

@section('content')
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48">

              <h2>Preguntas Frecuentes</h2>

              <h3>Uso de Carpoolear</h3>
              <p>Tenés dudas de cómo usar Carpoolear? Escribinos a nuestro <a href="https://facebook.com/carpoolear" target="_blank">Facebook</a> o <a href="mailto:carpoolear@stsrosario.org.ar" target="_top">carpoolear@stsrosario.org.ar</a>.</p>

              <h3>¿Cómo funciona la visibilidad de los viajes?</h3>
              <p>Para que que te sientas seguro, al momento de crear un viaje podés definir cuáles serán los usuario de Carpoolear que podrán verlo. Hay tres tipos de visibilidad:  “Viaje público”  “Viaje visible para amigos de amigos”   “Viaje visible para amigos” . Podés definir quienes son tus amigos, mandando solicitudes de amistad dentro de la plataforma y, si vinculaste tu cuenta de facebook, la plataforma tendrá en cuenta también a tus amigos que estén en Carpoolear.</p>
              <ul>
                <li>“Viaje público” → TODOS los usuarios de Carpoolear podrán ver y solicitar este viaje.</li>
                <li>“Viaje visible para amigos de amigos” →  tus amigos y sus amigos de Carpoolear y Facebook (si te loggeaste con esta red social) podrán ver y solicitar este viaje.</li>
                <li>“Viaje visible para amigos” → sólo tus amigos de Carpoolear y Facebook (si te loggeaste con esta red social)  que utilicen Carpoolear podrán ver y solicitar este viaje.</li>
              </ul>

              <table class="table-visibilidad">
                <thead>
                  <tr>
                      <th></th>
                      <th>Si sos amigo
                      </th><th>Si tenés algún amigo en común</th>
                      <th>Si no sos amigo</th>
                  </tr>
                </thead>
                  <tbody><tr>
                      <th>Alguien crea un viaje "Público"</th>
                      <td>lo ves ✔</td>
                      <td>lo ves ✔</td>
                      <td>lo ves ✔</td>
                  </tr>
                  <tr>
                      <th>Alguien crea un viaje para "Amigos de amigos"</th>
                      <td>lo ves ✔</td>
                      <td>lo ves ✔</td>
                      <td>no lo ves ✘</td>
                  </tr>
                  <tr>
                      <th>Alguien crea un viaje para "Amigos"</th>
                      <td>lo ves ✔</td>
                      <td>no lo ves ✘</td>
                      <td>no lo ves ✘</td>
                  </tr>
              </tbody></table>

              <h3>¿Puedo invitar a mis amigos a Carpoolear? :D</h3>
              <p>Si!!! Contales del proyecto e invitalos a sumarse a la aplicación mediante el botón “invitar amigos” en el menú principal. También podés incentivarlos compartir en tu muro de Facebook los viajes que cargues a la plataforma.</p>

              <h3>¿Puedo crear viajes frecuentes u ocasionales para carpoolear dentro de mi ciudad?</h3>
              <p>Lamentablemente, aún, no porque el sistema no está desarrollado para gestionar este tipo de viajes. Pero estamos trabajando en el desarrollo de una versión para compartir viajes de esta forma.</p>

              <h3>¿Puedo carpoolear sin tener auto?</h3>
              <p>¡Claro que sí!. En ese caso, al momento de crear un viaje, simplemente tenés que indicar que sos pasajero o podés buscar entre los viajes de conductores</p>

              <h3>¿Puedo buscar un viaje en particular?</h3>
              <p>¡Efectivamente! Usando el buscador de viajes. En la pantalla principal de la plataforma vas a poder ingresar “Origen”, “Destino” y/o “Fecha” (todos los campos son opcionales) del viaje que estas buscando. Por ejemplo si queres irte un fin de semana largo a cualquier lugar de Argentina, ingresas tu lugar de origen y la fecha en la que deseas salir. El buscador se encargará de mostrarte todos los viajes que salgan de ese lugar en esa fecha. Además, te enseñará viajes relacionados con fechas alternativas. También podés buscar viajes que cargaron conductores con asientos libres o personas que buscan que los lleven.</p>

              <h3>Quiero calificar a mi compañero de viaje, ¿cómo puedo hacerlo?</h3>
              <p>Podés hacerlo a través de nuestro sistema de calificaciones después de que pasen 24hs desde el horario de inicio del viaje. Para poder calificar, ya sea en el rol de conductor o pasajero, ingresá en la plataforma dentro de la sección Mis Viajes. Aparecerá un cuadro de diálogo en el cual podrás comentar tu experiencia indicando si recomendás o no compartir viaje con la persona, a través de un texto y un pulgar arriba/abajo según corresponda. El comentario acerca de tu experiencia es muy importante para que el resto de la comunidad entienda porque recomendás o no compartir viajes con esa persona, contá todo lo que te parezca importante. Cuando aprietes el botón "calificar" , toda la información llegará a Carpoolear y no podrás cambiarla. Tu referencia recién será publicada en el perfil de la otra persona cuando ella también te haya dejado su calificación, apareciendo ambas referencias al mismo tiempo en sus perfiles en la plataforma. Eso es así para evitar que alguien te califique en venganza porque le hayas dejado una mala referencia. En caso de que pasen 14 días sin que alguien califique, el sistema publicará la calificación que haya sido realizada y la persona que no haya dejado referencia en ese tiempo ya no podrá hacerlo. Recordá que si sos conductor podes calificar a todos los pasajeros que llevaste, y si sos pasajero podes calificar al conductor.</p>
              <p>¡Algo muy importante! No alcanza con coordinar por mensaje para que la plataforma se entere que viajan juntos y luego habilite el sistema de calificación. El sistema sólo podrá saber que comparten viaje cuando se haya generado una solicitud de asiento con el botón correspondiente en el detalle del viaje y luego la persona que recibió esa solicitud de asiento la acepte con el botón correspondiente. Recién ahí el sistema registra que van a compartir un viaje y luego habilitará la posibilidad de que se dejen referencias :D</p>

              <h3>¿La pasaste mal en un viaje compartido?</h3>
              <p>Si tuviste problemas con alguno de los pasajeros o el conductor del viaje, escribinos un mail dejando en claro lo que pasó. Si consideramos que lo amerita y hay pruebas de lo ocurrido, podemos suspenderlo por algún tiempo o de forma indeterminada.</p>

              <h3>¿Cómo comparto viajes por las redes sociales?</h3>
              <p>Para compartir tu viaje o alguno que hayas visto en la plataforma a través de las redes sociales, tenés que usar los botones con el símbolo de la red social, los podés encontrar en el listado de viajes o dentro del detalle de cada uno. </p>

              <h3>Ante cualquier comentario, consulta o propuesta de mejora…</h3>
              <p>Escribinos a nuestro <a href="https://facebook.com/carpoolear" target="_blank">Facebook</a> o <a href="mailto:carpoolear@stsrosario.org.ar" target="_top">carpoolear@stsrosario.org.ar</a>.</p>

            </div>
        </div>
    </div>
</section>
@endsection
