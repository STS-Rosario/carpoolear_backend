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
    .donation-top {
        margin-top: 0;
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
                <img src="/img/economia-colaborativa.jpg" style="float: right; width: 100%; max-width: 450px;" class="hidden-xs" />
                <div class="donation donation-top">
                    <h3>Donar</h3>
                    <div class="radio">
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation50" value="1000" v-model="donateValue"><span>$ 1000</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation100" value="2000" v-model="donateValue"><span>$ 2000</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation200" value="5000" v-model="donateValue"><span>$ 5000</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation500" value="50" v-model="donateValue"><span>Elegí tu propia aventura (solo mensual)</span>
                        </label>
                    </div>
                    <div>
                        <button class="btn-unica-vez btn-donar btn-unica" id="btn-unica">ÚNICA VEZ</button>
                        <button class="btn-mensualmente btn-donar" id="btn-mensual">MENSUALMENTE <br />(cancelá cuando quieras)</button>
                    </div>
                </div>
                <p>¡Hola Carpooler@s!</p>

                <p>En el 2013 pusimos en marcha a Carpoolear y desde ese momento estamos en ruta llevando adelante la plataforma bajo USO GRATUITO tanto en su versión web como app. Somos un proyecto SIN FINES DE LUCRO, COLABORATIVO y de CÓDIGO LIBRE. Queremos seguir siéndolo y para eso NECESITAMOS tu APOYO.</p>

                <p>Quienes nos acompañan desde el comienzo saben que avanzamos un montón con muy pocos recursos esporádicos, pero esto es cada vez más difícil. Hoy en día la plataforma tiene más de 100 mil personas registradas, requiere mucho trabajo y coordinación, que son llevados adelante mediante esfuerzo de un EQUIPO VOLUNTARIO pero también tenemos gastos de servidores, legales y administrativos como cualquier proyecto.</p>

                <p>Por eso necesitamos tu DONACIÓN para poder avanzar con el desarrollo de la plataforma más rápido manteniendo nuestra filosofía de trabajo. Si Carpoolear es útil para vos, te gusta lo que hacemos, querés cuidar el medio ambiente, tomate 1 MINUTO y COLABORÁ :D
                Podés donar $1000, $2000 y más allá también. O sea, que nos podés invitar un café con leche, una pinta o por qué no, salir a comer.</p>

                <p>Carpoolear es un proyecto de STS Rosario, una ONG sin fines de lucro, constituida como asociación civil desde el 2014. A través de proyectos concretos, divulga las problemáticas socioambientales actuales y genera herramientas, para provocar un cambio cultural hacia una sociedad sustentable, resiliente y equitativa. Del total de la donación realizada a nosotros, un 10% será destinada al sostenimiento de nuestra organización, para que pueda haber más proyectos como Carpoolear. Podés enterarte más acerca de <a href="www.stsrosario.org.ar" target="_blank">STS en www.stsrosario.org.ar</a></p>

                <div class="donation hidden-sm hidden-md hidden-lg">
                    <div class="radio">
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation50" value="50" v-model="donateValue"><span>$ 50</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation100" value="1000" v-model="donateValue"><span>$ 1000</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation200" value="2000" v-model="donateValue"><span>$ 2000</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation500" value="5000" v-model="donateValue"><span>$ 5000</span>
                        </label>
                    </div>
                    <div>
                        <button class="btn-unica-vez btn-donar btn-unica" id="btn-unica">ÚNICA VEZ</button>
                        <button class="btn-mensualmente btn-donar" id="btn-mensual">MENSUALMENTE <br />(cancelá cuando quieras)</button>
                    </div>
                </div>
                <p>Cualquier duda, escribinos a nuestras redes sociales o a <a href="mailto:carpoolear@stsrosario.org.ar">carpoolear@stsrosario.org.ar</a></p>
                <img src="/img/economia-colaborativa.jpg" style="float: right; width: 100%; max-width: 450px;" class="hidden-sm hidden-md hidden-lg" />
            </div>
        </div>
    </div>
</section>
<script>
    function post (user, ammount) {
        var http = new XMLHttpRequest();
        var url = '/api/users/donation';
        var params = 'has_donated=1&ammount=' + ammount + '&user=' + user;
        http.open('POST', url, true);

        //Send the proper header information along with the request
        http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

        http.onreadystatechange = function() {//Call a function when the state changes.
            if(http.readyState == 4 && http.status == 200) {
                console.log('success');
            }
        }
        http.send(params);
    }
    function getParameterByName(name, url) {
        if (!url) url = window.location.href;
        name = name.replace(/[\[\]]/g, '\\$&');
        var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, ' '));
    }
    var linksUnicaVez = {
        1000: "https://www.mercadopago.com.ar/checkout/v1/redirect?preference-id=201279444-c693bd88-7fd4-49d8-9f22-2b80151d184e",
        2000: "https://mpago.la/1WhaoLf",
        5000: "https://mpago.la/1SB6on8"
    };
    var linksMensual = {
        50: "https://www.mercadopago.com.ar/subscriptions/checkout?preapproval_plan_id=2c938084749ef7f70174ad5d6f151110",
        1000: "https://www.mercadopago.com.ar/subscriptions/checkout?preapproval_plan_id=2c93808474a9e8340174ad3f98e7019b",
        2000: "https://www.mercadopago.com.ar/subscriptions/checkout?preapproval_plan_id=2c9380848a2fd5c9018a33702cc50181",
        5000: "https://www.mercadopago.com.ar/subscriptions/checkout?preapproval_plan_id=2c9380848cee0ea5018d0e9ea71016d7"
    };
    var btns = document.querySelectorAll(".btn-donar");
    btns.forEach(function (btn) {
        btn.addEventListener("click", function (event) {
            var rdb = document.querySelector('input[name="donationValor"]:checked');
            if (rdb) {
                var value = rdb.value;
                if (event.target.className.indexOf("btn-unica") >= 0) {
                    window.open(linksUnicaVez[value], '_blank');
                } else {
                    window.open(linksMensual[value], '_blank');
                }
                var user_id = getParameterByName('u');
                post(user_id, value);
            } else {
                alert("Debes seleccionar un monto de donación. Gracias!");
            }
        });
    });
</script>
@endsection
