@extends('layouts.master')

@section('title', 'Donar - Carpoolear')
@section('body-class', 'body-difusion')

@section('content')
<style>
    .body-donar {
        min-height: 80vh;
    }
    .donation {
        margin-top: 4em;
        margin-bottom: 1em;
    }
    .radio {
        margin-bottom: 1.5em;
    }
    .btn-donar {
        min-height: 5em;
        vertical-align: middle;
        border: 0;
        padding: 1em 2em;
        min-width: 250px;
        border-radius: 10px;
        margin-right: 10px;
    }
    .btn-donar:hover,
    .btn-donar:active,
    .btn-donar:focus {
        opacity: 0.90;
    }
    .btn-unica-vez {    
        color: #fff;
        background-color: #5cb85c;
        border-color: #4cae4c;
    }
    .btn-mensualmente {    
        color: #fff;
        background-color: #5bc0de;
        border-color: #46b8da;
    }
</style>
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48 body-donar">
                <img src="/img/economia-colaborativa.jpg" style="float: right; width: 100%; max-width: 450px;" />
                <p>¡Hola Carpooler@s!</p>

                <p>En el 2013 pusimos en marcha a Carpoolear y desde ese momento estamos en ruta llevando adelante la plataforma bajo USO GRATUITO tanto en su versión web como app. Somos un proyecto SIN FINES DE LUCRO, COLABORATIVO y de CÓDIGO LIBRE. Queremos seguir siéndolo y para eso NECESITAMOS tu APOYO.</p>

                <p>Quienes nos acompañan desde el comienzo saben que avanzamos un montón con muy pocos recursos esporádicos, pero esto es cada vez más difícil. Hoy en día la plataforma tiene más de 100 mil personas registradas, requiere mucho trabajo y coordinación, que son llevados adelante mediante esfuerzo de un EQUIPO VOLUNTARIO pero también tenemos gastos de servidores, legales y administrativos como cualquier proyecto.</p>

                <p>Por eso necesitamos tu DONACIÓN para poder avanzar con el desarrollo de la plataforma más rápido manteniendo nuestra filosofía de trabajo. Si Carpoolear es útil para vos, te gusta lo que hacemos, querés cuidar el medio ambiente, tomate 1 MINUTO y COLABORÁ :D
                Podés donar $50, 100$ y más allá también. O sea, que nos podés invitar un café con leche, una pinta o por qué no, salir a comer.</p>

                <p>Carpoolear es un proyecto de STS Rosario, una ONG sin fines de lucro, constituida como asociación civil desde el 2014. A través de proyectos concretos, divulga las problemáticas socioambientales actuales y genera herramientas, para provocar un cambio cultural hacia una sociedad sustentable, resiliente y equitativa. Del total de la donación realizada a nosotros, un 10% será destinada al sostenimiento de nuestra organización, para que pueda haber más proyectos como Carpoolear. Podés enterarte más acerca de STS en www.stsrosario.org.ar</p>

                <div class="donation">
                    <div class="radio">
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation50" value="50" v-model="donateValue"><span>$ 50</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation100" value="100" v-model="donateValue"><span>$ 100</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation200" value="200" v-model="donateValue"><span>$ 200</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation500" value="500" v-model="donateValue"><span>$ 500</span>
                        </label>
                    </div>
                    <div>
                        <button class="btn-unica-vez btn-donar" id="btn-unica">ÚNICA VEZ</button>
                        <button class="btn-mensualmente btn-donar" id="btn-mensual">MENSUALMENTE <br />(cancelá cuando quieras)</button>
                    </div>
                </div>
                <p>Cualquier duda, escribinos a nuestras redes sociales o a <a href="mailto:carpoolear@stsrosario.org.ar">carpoolear@stsrosario.org.ar</a></p>
            </div>
        </div>
    </div>
</section>
<script>
    var linksUnicaVez = {
        50: "http://mpago.la/jgap",
        100: "http://mpago.la/CaSZ",
        200: "http://mpago.la/xntw",
        500: "http://mpago.la/QEiN"
    };
    var linksMensual = {
        50: "http://mpago.la/1w3aci",
        100: "http://mpago.la/BfZ",
        200: "http://mpago.la/P02H",
        500: "http://mpago.la/k8Xp"
    };
    var btns = document.querySelectorAll(".btn-donar");
    btns.forEach(function (btn) {
        btn.addEventListener("click", function (event) {
            console.log(event.target.id);
            var value = document.querySelector('input[name="donationValor"]:checked').value;
            if (event.target.id === "btn-unica") {
                window.open(linksUnicaVez[value], '_blank');
            } else {
                window.open(linksMensual[value], '_blank');
            }
        });
    });
</script>
@endsection
