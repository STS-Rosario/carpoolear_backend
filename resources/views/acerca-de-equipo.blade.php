@extends('layouts.master')

@section('title', 'Acerca del Equipo - Carpoolear')
@section('body-class', 'body-acerca acerca-de-equipo')

@section('content')
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48">
                <h2>Nosotros</h2>

                <p>CarpooleAR empezó como un sueño en el año 2010 y fue creciendo paso a paso...</p> 
                <p>El equipo voluntario que lo lleva adelantes está formado por diseñadores, comunicadores, ingenieros de diversas ramas y autodidactas varios. La primer versión de la plataforma la lanzamos en septiembre del 2013. Se siguió desarrollando ese mismo sistema hasta el año 2017, en el cual se hace un re-lanzamiento, con un sistema 100% nuevo. Actualmente el equipo se divide en tres áreas de trabajo: Sistemas, Comunicación y Búsqueda de Recursos. Las tres son fundamentales para que el proyecto avance.</p>
                
                <p>Te presentamos al equipo de CarpooleAR:</p>
                
                <div class="row">
                    <div class="col-sm-10 col-sm-8-offset-1">
                    <ul class="fotos-nosotros">
                        <li><img src="img/foto_pablo.jpeg" width="100" height="100" alt="Foto de Pablo Botta">
                        <p><strong>Pablo Botta:</strong> Ingeniero en Sistemas oriundo de la ciudad de Rosario. En el 2014 conocí STS por Rosario en Bici ya que comparto la pasión por las biciletas. Entonces empecé a colaborar en los proyectos digitales de la organización. Finalmente encontré mi lugarcito en CarpooleAR.</p>
                        </li>
                        <li><img src="img/foto_andrea.jpg" width="100" height="100" alt="Foto de Andrea C">
                        <p><strong>Andrea Camorino:</strong> Soy periodista, organizadora de eventos. Voluntaria en varias organizaciones. Ecologista, amo los animales y el cine. En STS colaboro actualmente en Carpoolear, en la parte de prensa y difusión.</p>
                        </li>
                        <li><img src="img/foto_santiago.jpg" width="100" height="100" alt="Foto de Santiago Cantarutti">
                        <p><strong>Santiago Cantarutti:</strong> Soy de Buenos Aires, aunque de corazón rosarino, incorporado a STS y al proyecto de Carpoolear como diseñador gráfico para realizar difusión por redes sociales, comunicación institucional, diseño de interfaz y/o de lo que surja. Fotografía, música, cine y aire libre como hobbies clichés, sumando a la cocina que al combinarla con alguno de los anteriores genera mis lindos momentos. Carpoolear es mi forma de aportar a una causa más grande que mis propios intereses, cuestiones comerciales o a mi mismo, apostando y deseando a mejorar nuestro día a día, sociedad y entorno.</p>
                        </li>
                        <li><img src="img/foto_marina.jpg" width="100" height="100" alt="Foto de Marina Cebollada">
                        <p><strong>Marina Cebollada:</strong> Soy Marina, licenciada en Comunicación Social, Diseñadora Gáfica y diplomada en Responsabilidad Social y Desarrollo Sostenible. Y bailarina (sobre todo, bailarina). Conocí a STS en el Foro Latinoamericano de Desarrollo Sostenible, y finalmente en 2015 me incorporo al equipo de Carpoolear para dar una mano con difusión y diseño, y con todo lo que se necesite.</p>
                        </li>
                        <li><img src="img/foto_anita.jpg" width="100" height="100" alt="Foto de Anita">
                        <p><strong>Ana Laura Garrote:</strong> Aquí Anita. Ana Laura, si nos ponemos serios. Estudié Publicidad. Desde los comienzos de la carrera siempre me dediqué a la creatividad y el diseño, mayormente digital. Trabajando rodeada de programadores, finalmente me convencieron de que me aventurara en el mundo del código, así que hace unos 5 años que también maqueto sitios web, aplicaciones y software. Me sumé a Carpoolear hacia fines del 2014. No sé si vamos a salvar al mundo (aunque quién sabe), pero me gusta ser parte de una fuerza que empuja para mejorarlo un poquito todos los días.</p></li>
                        <li><img src="img/foto_marshan.jpg" width="100" height="100" alt="Foto de Anita">
                        <p><strong>Marina Giannone:</strong>Médica, aunque no parezca. Y otras cosas más de las que no tengo título habilitante. 
                        Rosarina, muy. Creo que la música y la bicicleta son ingredientes fundamentales de la felicidad.
                        Entré a STS para tratar de salvar al mundo y STS me salvó a mí.</p></li>
                        <li><img src="img/foto_emilio.jpg" width="100" height="100" alt="Foto de Emilio Gentile">
                        <p><strong>Emilio Gentile:</strong> Soy Rosarino y vivo en Rosario. Participo en STS Rosario desde el 2010, siempre me interesé por cuestiones vinculadas a la energía, sin embargo Carpoolear nos reunió a varios en el 2012 y desde ahí que colaboro en el proyecto. En mi cabeza hay una mezcla de ingeniería electrónica, funkadelic, beatles, black sabbath, ramones, manal, clarke, asimov, p.dick, bioy, comunicaciones y viajes. Me gustan mucho los colores.</p></li>
                        <li><img src="img/foto_seba.jpg" width="100" height="100" alt="Foto de Sebastián La Spina">
                        <p><strong>Sebastián La Spina:</strong> Soy Rosarino, nómade interestelar y IT ninja por naturaleza. Llegué a STS por un tejado verde y terminé tunneando el motor del proyecto Carpoolear. Me gusta estar con la naturaleza, viajar por los horizontes, compartir proyectos sustentables y realizar actividades superadoras.</p></li>
                        <li><img src="img/foto_matias.jpg" width="100" height="100" alt="Foto de Matías Ocampo">
                        <p><strong>Matías Ocampo:</strong> Estudiante de Licenciatura en Administración de Empresas, formo parte de Carpoolear desde Junio de 2014.</p>
                        </li>
                        <li><img src="img/foto_pelo.jpg" width="100" height="100" alt="Foto de Cristian Rojas">
                        <p><strong>Cristian Rojas</strong>: Soy de Concordia, Entre Ríos y me encuentro estudiando la carrera de Ingeniería Industrial en la UNR. Entusiasta de viajar y conocer nuevos lugares y personas, fiel creyente de un medio ambiente sostenible, me sume a Carpoolear en el 2014 para colaborar con el equipo e incentivar a la sociedad a darle un mejor uso al automóvil.</p>
                        </li>
                        <li><img src="img/foto_mariasol.jpg" width="100" height="100" alt="Foto de María Sol Tadeo">
                        <p><strong>María Sol Tadeo:</strong> Soy de Arroyo Dulce, un pueblito de Buenos Aires, pero me enamoré de Rosario cuando vine a estudiar y me quedé. Soy Ingeniera Civil. Estoy en Carpoolear porque quiero ayudar a un uso más racional de los autos.</p></li>          
                        <li><img src="img/foto_gabriel.jpg" width="100" height="100" alt="Foto de Gabriel Weitz">
                        <p><strong>Gabriel Weitz:</strong> Rosarino exiliado en Buenos Aires, carpoolero desde la primera hora, empleado de Google, fundador de este hermoso delirio que es STS. Uso demasiado Twitter.</p>
                        </li>
                        <li><img src="img/foto_martin_acosta.jpg" width="100" height="100" alt="Foto de Martín Acosta">
                        <p><strong>Martín Acosta:</strong> Soy de Pergamino, Buenos Aires. Me encanta todo lo que sea colaborativo y que ayude a la gente. En Carpoolear encontré ambas por lo que me sumé para aportar mi granito de arena en programación y comunicación.</p>
                        </li>
                        <li><img src="img/foto_camila.jpg" width="100" height="100" alt="Foto de Camila Grimi">
                        <p><strong>Camila Grimi:</strong> Diseñadora gráfica de Rosario, amante del rock, el cine y el Tetris. Me incorporé en el área de comunicación de Carpoolear en 2017 para promover y ser parte de este gran proyecto sustentable.</p>
                        </li>
                        <li><img src="img/foto_misslemon.jpg" width="100" height="100" alt="Foto de María Sánchez Villalba">
                        <p><strong>María Sánchez Villalba:</strong> Tucumana. Un gusto musical variado, matemáticas, psicología, hornear galletas y contarle a viajeros sobre mi ciudad, hacen mis días. Andar en bici para transportarme y conocer nuevos lugares, me fascina. Combino hobbies y principios varios con Carpoolear, colaboro desde 2016.</p>
                        </li>
                        </li>
                        <li><img src="img/foto_sole.jpg" width="100" height="100" alt="Foto de Soledad Larroucau">
                        <p><strong>Soledad Larroucau:</strong> Rosarina por adopción. Estudié comunicación social, sin peros, trabajo en Innovación Pública del Gobierno de Santa Fe. Juego a las Ciencias Sociales en el Centro de Investigaciones en Mediatizaciones y contagio ideas desde TEDxRosario. Fan con vincha de STS Rosario.</p>
                        </li>
                        <!--li><img src="img/foto_seba.jpg" width="100" height="100" alt="Foto de Sebastián La Spina">
                        <p><strong>Sebastián La Spina:</strong> Descripción</p>
                        </li-->
                    </ul>
                    
                    <p><strong>Colaboradores</strong>: Gonzalo González Mora, Carmen García, Barbara Cravero, Andrés Culasso, Gerardo Tjor, Germán Gentile, Luciana Bernard, Tristana Retamoso.</p>
                    
                    <p>Han formado parte del equipo:</p>
                    <p>Rocío Anselmo, Samuel Burgueño, Martín Bruno, Bárbara Cravero, Pilar Frechou, Mariel Figueroa, Ezequiel Grosso, Gisel Levit, Cintia Pereyra, Ramiro Picó, Galo Pugliese, Mariano Soto, Martin Cesanelli, Sofia Visintini, Moksha, Ohnos Diseño.</p>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</section>
@endsection
