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

              <h3>¿Qué es Carpoolear?</h3>
              <p>Es una red de personas que comparten viajes en auto entre ciudades, haciendo una división equitativa de costos entre todos los viajeros o una contribución convenida. Carpoolear no es un sistema de transporte de pasajeros ni público ni privado.</p>
              <p>Es un proyecto de gestión colectiva sin fines de lucro y de <a href="http://www.github.com/sts-rosario">código libre</a> que surge de la asociación civil <a href="http://www.stsrosario.org.ar">STS Rosario</a>. </p>

              <h3>¿Qué tipos de viaje puedo hacer con Carpoolear?</h3>
              <p>En Carpoolear podés crear o encontrar viajes a media y larga distancia, desde comunas aledañas a destinos internacionales. </p>
              <p>Los viajes los crean los mismos usuarios según su destino y disponibilidad horario. En base a esa propuesta de viaje, otros usuarios pueden solicitarle al conductor acompañarlo en trayecto. </p>

              <h3>¿Tienen costo los viajes en Carpoolear? ¿Qué costo tiene un viaje de Carpoolear?</h3>
              <p>Carpoolear -al ser una red de personas que comparten viajes- sólo permite contribuciones monetarias para combustible utilizado y peajes o división equitativa de gastos entre todos los partícipes. No existe una tarifa fija ni un valor de pasaje. La contribución no puede superar el valor de una división equitativa de gastos de combustible y peajes ya que de ser así podría ser considerado como un transporte ilegal de pasajeros. <u>Carpoolear es una red colaborativa, no un servicio.</u></p>


              <h3>¿Cómo se calcula la contribución para un viaje?</h3>
              <p>La contribución monetaria máxima aceptada en Carpoolear es la de combustible utilizado + peajes dividido la cantidad de personas que viajan en el auto. La misma se debe definir antes del viaje, antes o durante la coordinaciòn previa. A menos que se decida hacer una división de gastos con los tickets de combustible y peaje en mano, al finalizar el mismo.En caso de que una persona pida un valor monetario que deje en evidencia que supera la máxima aceptada, será advertido por los administradores y suspendido de la plataforma hasta aceptar las reglas.En caso de reincidencia, suspendido por meses hasta llegar a una suspensiòn definitiva.</p>


              <h3>¿Por qué sólo se consideran el gasto de combustible utilizado y peajes?</h3>
              <p>Son los únicos gastos comprobables en un viaje a través de tickets (en caso de requerirse su verificación). El resto de los gastos que pudiesen existir (desgaste del auto o seguros) corren a cuenta del conductor ya que aporta su automóvil de forma desinteresada para el viaje y no se dedica profesionalmente al transporte de personas. </p>
              <p>En caso de un posible inconveniente en el viaje, si se constata que ha existido un lucro en favor del conductor, el mismo podría enfrentar problemas legales por ofrecer un servicio y no estar inscripto para realizar esa actividad y cobrarla. En cambio, en una división de combustible y peaje no hay actividad ilícita. </p>
              <p>Más allá del aporte de combustible y peaje, las personas que comparten viaje pueden contribuir monetariamente por el motivo que consideren necesario a otra persona con quien compartieron el viaje -por ejemplo por las galletitas que compartieron- La misma debe ser completamente voluntaria y el motivo no puede ser por haber compartido el viaje.</p>
              <p><a href="https://docs.google.com/document/d/1sz_p3LS5AbcADtxxRByYTYAK3DGB73arvMRryF_SvAs/edit">Más informaciòn sobre la regla de contribuciòn màxima</a></p>



              <h3>¿Es fundamental presentar tickets de combustible y peaje para pedir la contribución monetaria a los pasajeros?</h3>
              <p>No son obligatorios pero son una forma de dejar claras las cuentas entre el conductor y sus acompañantes. Algunas personas realizan el mismo viaje con frecuencia y ya conocen los montos, así que no acostumbran a dividir los gastos en cada oportunidad sino que usan el valor de referencia del viaje anterior. 
              </p>
              <p>De todas formas, cualquier persona durante la coordinación previa al viaje puede pedir anticipadamente definir la contribución presentando tickets de combustible y peaje en mano. Ningún conductor puede negarse a este pedido ni dejarlo sin responder, tampoco puede dar de baja a un pasajero por esta solicitud. La división de gastos con tickets de combustible y peajes, es la vía más directa para establecer la contribución máxima para un viaje y es la recomendada por Carpoolear.(*)</p>
              <p>(*) Para calcular el gasto de combustible y peajes con los tickets, Carpoolear recomienda llenar el tanque antes de salir y volverlo a llenar en el destino. Esta última carga (o la cantidad de cargas de combustible que se hagan una vez comenzado el viaje) será el gasto del combustible + los tickets de los peajes. </p>


              <h3>¿Se puede hacer contribuciones de otra forma, que no sea monetaria?</h3>
              <p>Será un arreglo entre conductor y pasajero. Algunos pueden solicitar u ofrecer otro tipo de contribución, como que alguien se encargue de pagar las comidas durante un viaje de larga duración o solicitarle al pasajero que acompañe despierto para mantener al conductor atento al camino, entre otros ejemplos. 
              </p>

              <h3>¿Cómo coordino un viaje? </h3>
              <p><strong>Conductores:</strong>
              </p>
              <p>Cuando cargues el viaje te recomendamos que detallar lo mejor posible la información sobre para evitar consultas repetitivas de parte de los interesados en tu viaje. Para ello utilizá el campo “Comentario para los pasajeros”. Ahí podrás informar cuál es la contribución por el viaje y el monto, el espacio disponible para equipaje, cuál es el máximo de pasajeros que llevarás en tu auto, dónde comienza y finaliza el mismo y cualquier otro dato adicional que consideres relevante. <br >Es importante que te comuniques con tus pasajeros vía mensaje para terminar de coordinar detalles y las condiciones del viaje. Te sugerimos que en esta instancia les pidas un contacto telefónico para una comunicación más precisa llegada la fecha del viaje. <br >Recordá que ambos deben estar de acuerdo y al tanto de las condiciones del viaje para evitar malentendidos con respecto a horarios, puntos de encuentro y llegada, equipaje, contribución para combustible y peaje y demás información fundamental.<br >Te recomendamos coordinar y subir a tus pasajeros utilizando la plataforma de Carpoolear para luego acceder a la posibilidad de calificarlos al terminar el viaje. 
                </p>
                <p><strong>Conductores: Buscador de pasajeros:</strong>
                </p>
                <p>Además de publicar tu viaje como Conductor y esperar que otros te contacten, también podés buscar pasajeros usando Carpoolear. Desde la PC, haciendo click en el botón “Busco pasajeros”, completás los campos de origen y destino y presionás “Buscar”. 
                  Desde el celular: en la pantalla principal apretando sobre el símbolo de la lupa y seleccionando “busco pasajero” como criterio de búsqueda. 
                  </p>
              
                  <p><strong>Pasajeros: </strong>
                  </p>
                  <p>Una vez que encontraste un viaje que te sirve, enviá un mensaje vía la plataforma al conductor para aclarar los términos del mismo y coordinar los detalles. En ese mensaje podrás hacer consultas si ves que la descripción del viaje no tiene toda la información que precisás, o podés dejarle un contacto telefónico al conductor para que tenga otra alternativa de comunicación con vos. Una vez satisfechas las consultas y que ambos están de acuerdo con las condiciones del viaje, le solicitás el asiento al conductor y él acepta tu solicitud. 
                    <br />Es importante que ambos estén de acuerdo en las condiciones y detalles del viaje a la hora de confirmarlo. Asegurate de que no hay dudas sobre el punto de encuentro, el horario, la disponibilidad de espacio para equipaje, la cantidad total de pasajeros y la contribución por los gastos de combustible y peaje. También te sugerimos pedir el número de teléfono del conductor para una comunicación más precisa llegada la fecha del viaje. 
                    <br />Te recomendamos siempre subirte al viaje utilizando la plataforma de Carpoolear para luego acceder a la posibilidad de calificarlo al terminar el viaje. Recordá que para eso, debés solicitar asiento con el botón correspondiente y que la otra persona te acepte la solicitud. Cuando te acepten, te llegará una notificación que podés ver verificar dentro de “Mis viajes".
                    <br />Una vez que está todo coordinado y el conductor te confirma para compartir el viaje ¡ya sólo hay que esperar hasta el día del viaje!
                    </p>

              <h3>¿Quiénes ven los viajes que publicó?: Visibilidad personalizada de viajes.</h3>
              <p>Al momento de crear un viaje podés definir la privacidad del mismo y elegir quiénes serán los usuarios de Carpoolear que podrán contactarse con vos. <br />
                Hay tres tipos de visibilidad: <br />
                “Viaje público” <br />
                “Viaje visible para amigos de amigos” <br />
                “Viaje visible para amigos” <br />
                <br />Podés crear tu lista de amigos enviando solicitudes de amistad dentro de la plataforma o vinculando tu cuenta con la de Facebook, para que la plataforma tenga en cuenta también a tus amigos de esa red social que están en Carpoolear. 
              </p>
              <p>Siguiendo esto los tres tipos de viaje comprenden: </p>
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

              <h3>Tuve problemas con mi viaje, ¿qué hago? </h3>
              <p>Si tuviste una mala experiencia con tu viaje (conductores irresponsables, falta de compromiso del pasajero, entre otros problemas) o las condiciones del mismo no se dieron tal como fueron pautadas, contá sobre tu experiencia cuando puedas calificar a la otra persona. Es por eso que es importante que mantengas la coordinación de los viajes dentro de la plataforma de Carpoolear.</p>
              <p>Si la mala experiencia implica violencia por mensajes, verbal o física. También violencia vial (conducción peligrosa, uso de teléfono celular, etc). No respetar la contribución máxima o alguna otra ilegalidad. Más allá de dejar una calificación a la persona, también escribinos a carpoolear@stsrosario.org.ar o vía Mensaje Privado en nuestra página de Facebook, Instagram o Tweeter y contanos qué pasó. 
                Si consideramos que lo amerita y que hay pruebas del incidente, podemos advertir, suspender a la persona por algún tiempo o de forma indeterminada.
                </p>

              <h3>¿Cómo funciona el sistema de calificaciones? </h3>
              <p>Podés calificar a tu conductor o pasajeros a través de nuestro sistema de calificaciones pasadas las 24hs desde el horario de inicio del viaje. <br />
                Para poder hacerlo -ya sea en el rol de conductor o pasajero- ingresá en la plataforma dentro de la sección ‘Mis Viajes’. Aparecerá un cuadro de diálogo en el cual podrás comentar tu experiencia indicando si recomendás o no compartir viaje con la persona (pulgar arriba/abajo según corresponda tu experiencia) y sumar un texto que justifique esa calificación. <br />El comentario sobre tu experiencia es muy importante para que el resto de la comunidad entienda porque recomendás o no compartir viajes con esa persona. Contá todo lo que te parezca importante que sepan los demás. Recordá que una vez enviada la calificación, la misma no puede modificarse. <br />
                Las calificaciones se publican una vez que la otra persona también haya dejado su calificación sobre vos, apareciendo ambas referencias al mismo tiempo en sus perfiles en la plataforma. En caso de que pasen 14 días sin que alguien califique, el sistema publicará la calificación que haya sido realizada y la persona que no haya dejado referencia en ese tiempo ya no podrá hacerlo.<br />Para que el sistema habilite la calificación, es necesario que se registre que ambos usuarios compartieron un viaje. Para eso es necesario que el pasajero haya enviado una solicitud de viaje al conductor y este último la haya aceptado. Si tan solamente te comunicaste vía mensaje pero no te aceptaron la solicitud de asiento (como pasajero) o no aceptaste la solicitud (como conductor), no se podrán calificar. 
                
                </p>

              <h3>Si me subí a un viaje como pasajero y luego tuve que bajarme o siendo conductor tuve que cancelar el viaje ¿se califica igual? </h3>
              <p>El sistema registra la calificación pendiente una vez que ambos (conductor y pasajeros) confirman que compartirán un viaje. Si un pasajero se diera de baja o conductor cancelara por algún motivo, igualmente ambos podrán calificarse. En ese caso te recomendamos aclarar que el viaje no se concretó y que cuentes cómo fue tu experiencia al interactuar con la otra persona.</p>


              <h3>¿Puedo carpoolear sin tener auto?</h3>
              <p>Claro, generando un viaje como pasajero que busca conductor. Podés crear un viaje como tal y la app le avisará a los conductores cuando carguen un viaje similar al que vos generaste como pasajero. Si no creás tu viaje, también podés buscar entre los ya publicados de los conductores y solicitar un asiento.  </p>


              <h3>¿Puedo usar Carpoolear para empalmar con otro transporte y continuar mi viaje? (Ejemplo: para ir al Aeropuerto Internacional de Ezeiza)   </h3>
              <p>Claro que sí, pero tené en cuenta que los viajes pueden estar sujetos a modificaciones por parte del conductor del auto. Para garantizar tu llegada a un aeropuerto o estación de ómnibus, te recomendamos usar transportes públicos. </p>



              <h3>¿Puedo buscar un viaje en particular?</h3>
              <p>Sí, usando el buscador de viajes. En la pantalla principal de la plataforma vas a poder ingresar “Origen”, “Destino” y/o “Fecha”. Todas funcionan como opcionales ya que no es necesario completar las tres.  <br>Ejemplo: Si querés irte un fin de semana largo de viaje pero no tenés destino definido, podés ingresar tu lugar de origen y la fecha en la que deseás salir. El buscador se encargará de mostrarte todos los viajes que salgan de ese lugar en esa fecha. Además, te mostrará viajes relacionados con fechas alternativas por si tenés planes flexibles. <br>Te invitamos a navegar la app y ver las infinitas opciones de viajes, como sumarte a alguno con asientos libres o encontrar compañeros viajeros en la lista de pasajeros que buscan auto. 
                </p>

                <h3>¿Cómo funciona la alerta de viaje?</h3>
                <p>Luego de realizar una búsqueda de viaje, al final del listado de resultados encontrarás un botón para crear una alerta con los datos de viajes que cargaste en la búsqueda.
                  La alerta te llega cuando alguien crea un nuevo viaje que coincide con la búsqueda desde la cual se originó la alerta. 
                  Próximamente podrás configurar las condiciones de la alerta pudiendo tener un rango de tiempo y distancia. Podés colaborar con Carpoolear para que podamos incorporar estas funciones pronto <a href="https://carpoolear.com.ar/donar"> ---> ¡Donar!</a></p>

                  <h3>¿Cómo funciona el ‘matcheador’ de viajes?</h3>
                  <p>Para ver los matchs (o coincidencias) de búsqueda con viajes publicados, tenés que ir a “mis viajes” entrar al que creaste y vas a ver las coincidencias. El “match” tiene un rango de días. Próximamente podrás configurar las condiciones de “match” pudiendo tener un rango de tiempo y distancia. Podés colaborar con Carpoolear para que podamos incorporar estas funciones pront <a href="https://carpoolear.com.ar/donar"> ---> ¡Donar!</a></p>

              <h3>¿Puedo invitar a mis amigos a Carpoolear? :D</h3>
              <p>¡Claro que sí! Contales del proyecto e invitalos a sumarse a la aplicación mediante el botón “Invitar amigos” en el menú principal. También podés incentivarlos compartir en tu muro de Facebook los viajes que cargues a la plataforma. </p>


              <h3>¿Puedo crear viajes frecuentes u ocasionales para carpoolear dentro de mi ciudad?</h3>
              <p>Lamentablemente el sistema aún no está desarrollado para gestionar este tipo de viajes pero estamos trabajando en el desarrollo de una versión que también sea útil para compartir viajes dentro de la misma ciudad.</p>

              <h3>¿Cómo comparto viajes por las redes sociales?</h3>
              <p>Para compartir tu viaje o alguno que hayas visto en la plataforma a través de las redes sociales, tenés que usar los botones con el símbolo de la red social. Los podés encontrar en el listado de viajes o dentro del detalle de cada uno. Esta función sólo está disponible para la versión descargable para celulares.  </p>


              <h3>¿Cómo nace Carpoolear? ¿Quiénes lo hacen? </h3>
              <p>Carpoolear es una red de personas que comparten viajes en auto. Es un proyecto de gestión colectiva sin fines de lucro y de código libre que surge de la asociación civil STS Rosario en 2013 con el fin de aportar a un uso más racional del auto. 
                La idea es que cuando tengamos que viajar en auto porque no hay colectivo o no nos conviene por algún motivo, siempre busquemos de llenar el auto para hacer un mejor aprovechamiento de los recursos fósiles y contaminación asociada al viaje. Más allá de la motivación ambiental, también Carpoolear nos parece muy importante porque ayuda a generar vínculos entre las personas, quienes muchas veces no se hubieran encontrado de otra forma. </p>

                <h3>¿Cómo puedo contribuir con Carpoolear?  </h3>
                <p>¡Genial que quieras colaborar! Se puede aportar a carpoolear con tareas de voluntariado y también difusión/contactos. <a href="https://carpoolear.com.ar/colabora-como-colaborar">Acá podés encontrar toda la información para ayudar a que Carpoolear llegue más lejos</a>.  </p>

              <p>También podés donar por única vez o mensualmente acá <a href="https://carpoolear.com.ar/donar"> ---> ¡Donar!</a></p>

              <h3>Ante cualquier comentario, consulta o propuesta de mejora, escribinos a nuestras redes Facebook, Instagram, Twitter o <a href="mailto:carpoolear@stsrosario.org.ar" target="_top">carpoolear@stsrosario.org.ar</a>.</p>

                <p>¡Buen viaje!</p>
            </div>
        </div>
    </div>
</section>
@endsection
