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
                <div class="donation donation-top">
                    <h3>#ViajeSeguro</h3>
                    <p>¡Hola Carpooler@s!</p>

                    <p>Si vas a viajar tenés que tener en cuenta que actualmente cada provincia define los requisitos de ingreso en su territorio, por lo que debe recurrirse a los organismos estatales provinciales para averiguar cuáles son. Algo importante a considerar es que las disposiciones van cambiando con el transcurso de los días.</p>

                    <p>
                        Ministerio de Transporte
                        <br />
                        <a href="https://www.argentina.gob.ar/transporte/covid-19/requisitos-ingreso-por-provincia">https://www.argentina.gob.ar/transporte/covid-19/requisitos-ingreso-por-provincia</a>
                    </p>

                    
                    <p>
                        Certificado Nacional Circular
                        <br />
                        <a href="https://www.argentina.gob.ar/circular">https://www.argentina.gob.ar/circular</a>
                    </p>

                    <p>
                        Certificado de Viajes Turísticos Verano
                        <br />
                        <a href="https://www.argentina.gob.ar/verano">https://www.argentina.gob.ar/verano</a>
                    </p>

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
        200: "https://www.mercadopago.com.ar/checkout/v1/redirect?preference-id=201279444-f94a3145-7336-4d79-9eb9-76c5402894fa",
        400: "https://www.mercadopago.com.ar/checkout/v1/redirect?preference-id=201279444-42de1d74-f967-455f-80bf-a7a77650db06",
        1000: "https://www.mercadopago.com.ar/checkout/v1/redirect?preference-id=201279444-c693bd88-7fd4-49d8-9f22-2b80151d184e"
    };
    var linksMensual = {
        50: "http://mpago.la/2XdoxpF",
        200: "http://mpago.la/2k6JFz6",
        400: "http://mpago.la/1FE4px6",
        1000: "http://mpago.la/1EcA6f4"
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
